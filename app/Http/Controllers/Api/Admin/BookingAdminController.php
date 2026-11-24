<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('bookings')
            ->join('pitches', 'pitches.id', '=', 'bookings.pitch_id')
            ->leftJoin('invoices', 'invoices.booking_id', '=', 'bookings.id')
            ->select('bookings.*', 'pitches.name as pitch_name', 'pitches.type as pitch_type', 'invoices.payment_status')
            ->orderByDesc('bookings.start_at');

        if ($request->filled('date')) {
            $query->whereDate('bookings.start_at', $request->date);
        }
        if ($request->filled('status')) {
            $query->where('bookings.status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    public function confirm(Request $request, int $id)
    {
        // Nhấn xác nhận nghĩa là quản lý đã kiểm tra tiền cọc thực tế.
        // Mặc định VietQR; có thể truyền deposit_method=cash nếu nhận tiền mặt.
        $data = $request->validate([
            'deposit_method' => ['sometimes', 'in:vietqr,cash'],
        ]);
        $method = $data['deposit_method'] ?? 'vietqr';

        $result = DB::transaction(function () use ($id, $request, $method) {
            $booking = DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            if (!$booking) {
                throw ValidationException::withMessages(['booking' => 'Không tìm thấy đơn đặt sân.']);
            }
            if ($booking->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Chỉ đơn đang chờ mới được xác nhận.']);
            }

            $invoice = DB::table('invoices')->where('booking_id', $id)->lockForUpdate()->first();
            if (!$invoice) {
                throw ValidationException::withMessages(['invoice' => 'Đơn chưa có hóa đơn.']);
            }
            $total = (int) $invoice->total;
            $threshold = (int) config('fieldly_payment.deposit_threshold_vnd', 500000);
            $fixedDeposit = (int) config('fieldly_payment.deposit_amount_vnd', 100000);
            $required = $total >= $threshold ? min($total, $fixedDeposit) : 0;

            $netPaid = (int) DB::table('payments')->where('invoice_id', $invoice->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount WHEN kind = 'refund' THEN -amount ELSE 0 END), 0) AS net")
                ->value('net');
            $toRecord = max(0, $required - $netPaid);
            if ($toRecord > 0) {
                $key = 'confirm-deposit-booking-' . $id;
                // Khóa duy nhất tránh thu cọc lặp khi yêu cầu được gửi lại.
                $existing = DB::table('payments')->where('idempotency_key', $key)->first();
                if ($existing) {
                    throw ValidationException::withMessages(['payment' => 'Đã tồn tại giao dịch cọc tự động; vui lòng kiểm tra lại trước khi xác nhận.']);
                }
                DB::table('payments')->insert([
                    'invoice_id' => $invoice->id,
                    'idempotency_key' => $key,
                    'kind' => 'receipt',
                    'method' => $method,
                    'amount' => $toRecord,
                    'reference' => 'FD' . $id . 'I' . $invoice->id,
                    'recorded_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $newNet = $netPaid + $toRecord;
            DB::table('invoices')->where('id', $invoice->id)->update([
                'payment_status' => $newNet <= 0 ? 'unpaid' : ($newNet >= $total ? 'paid' : 'deposit_paid'),
                'paid_at' => $newNet >= $total ? now() : null,
                'updated_at' => now(),
            ]);
            DB::table('bookings')->where('id', $id)->update([
                'status' => 'confirmed',
                'updated_at' => now(),
            ]);
            return [
                'booking' => DB::table('bookings')->where('id', $id)->first(),
                'invoice_id' => $invoice->id,
                'deposit_required' => $required,
                'deposit_recorded_now' => $toRecord,
                'net_paid' => $newNet,
                'remaining' => max(0, $total - $newNet),
            ];
        }, 3);

        app(\App\Services\BookingEmailService::class)->queue((int) $result['booking']->id, 'confirmed');
        return response()->json(['message' => 'Đã xác nhận đơn và cập nhật tiền cọc.', 'data' => $result['booking'],
            'payment' => collect($result)->except('booking')]);
    }
}
