<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerBookingController extends Controller
{
    /**
     * Chỉ trả đơn có customer_id của thành viên đăng nhập.
     * Không ghép đơn khách vãng lai bằng tên, email hay điện thoại.
     */
    private function ownedBookings(int $userId)
    {
        $payments = DB::table('payments')
            ->select('invoice_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount WHEN kind = 'refund' THEN -amount ELSE 0 END), 0) AS net_paid")
            ->selectRaw('COUNT(*) AS payment_transactions')
            ->groupBy('invoice_id');

        return DB::table('bookings as b')
            ->leftJoin('pitches as p', 'p.id', '=', 'b.pitch_id')
            ->leftJoin('invoices as i', 'i.booking_id', '=', 'b.id')
            ->leftJoinSub($payments, 'pay', fn ($join) => $join->on('pay.invoice_id', '=', 'i.id'))
            ->where('b.customer_id', $userId)
            ->select(
                'b.id', 'b.booking_code', 'b.pitch_id', 'b.start_at', 'b.end_at',
                'b.status', 'b.source', 'b.customer_name', 'b.customer_phone',
                'b.pitch_price', 'b.service_total', 'b.total_amount', 'b.created_at',
                'p.name as pitch_name', 'p.type as pitch_type',
                'i.id as invoice_id', 'i.invoice_code', 'i.payment_status',
                'i.total as invoice_total'
            )
            ->selectRaw('GREATEST(0, COALESCE(pay.net_paid, 0)) AS paid_amount')
            ->selectRaw('COALESCE(pay.payment_transactions, 0) AS payment_transactions')
            ->selectRaw("CASE WHEN b.status = 'cancelled' THEN 0 ELSE GREATEST(0, COALESCE(i.total, b.total_amount) - GREATEST(0, COALESCE(pay.net_paid, 0))) END AS remaining_amount");
    }

    private function withActions(object $booking): object
    {
        $booking->can_cancel = $booking->status === 'pending'
            && (int) $booking->payment_transactions === 0
            && Carbon::parse($booking->start_at)->isFuture();
        return $booking;
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,completed,cancelled'],
            'search' => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $this->ownedBookings((int) $request->user()->id);
        if (!empty($filters['status'])) {
            $query->where('b.status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . addcslashes(trim($filters['search']), '%_\\') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('b.booking_code', 'like', $search)
                    ->orWhere('p.name', 'like', $search);
            });
        }

        $result = $query->orderByDesc('b.created_at')->orderByDesc('b.id')
            ->paginate((int) ($filters['per_page'] ?? 10));
        $result->getCollection()->transform(fn ($item) => $this->withActions($item));
        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $id)
    {
        $booking = $this->ownedBookings((int) $request->user()->id)
            ->where('b.id', $id)->first();
        if (!$booking) abort(404);

        $items = $booking->invoice_id
            ? DB::table('invoice_items')->where('invoice_id', $booking->invoice_id)
                ->orderBy('id')->get(['description', 'quantity', 'unit_price', 'line_total'])
            : collect();
        $payments = $booking->invoice_id
            ? DB::table('payments')->where('invoice_id', $booking->invoice_id)
                ->orderByDesc('id')->get(['kind', 'method', 'amount', 'created_at'])
            : collect();

        return response()->json([
            'data' => $this->withActions($booking),
            'items' => $items,
            'payments' => $payments,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cancel(Request $request, int $id)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $result = DB::transaction(function () use ($request, $id) {
            $booking = DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            if (!$booking || (int) $booking->customer_id !== (int) $request->user()->id) abort(404);
            if ($booking->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Chỉ có thể hủy yêu cầu đang chờ xác nhận.']);
            }
            if (Carbon::parse($booking->start_at)->lte(now())) {
                throw ValidationException::withMessages(['start_at' => 'Không thể hủy khi giờ đá đã bắt đầu.']);
            }
            $invoice = DB::table('invoices')->where('booking_id', $id)->lockForUpdate()->first();
            if (!$invoice) throw ValidationException::withMessages(['invoice' => 'Không tìm thấy hóa đơn.']);
            // Có bất kỳ giao dịch thu/hoàn nào thì chuyển cho quản trị viên xử lý,
            // kể cả khi tổng thu ròng đã bằng 0 sau hoàn tiền.
            if (DB::table('payments')->where('invoice_id', $invoice->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Đơn đã phát sinh giao dịch, vui lòng liên hệ chủ sân để xử lý hoàn tiền.']);
            }
            $lines = DB::table('booking_services')->where('booking_id', $id)->orderBy('service_id')->get();
            if ($booking->stock_restored_at === null) {
                foreach ($lines as $line) {
                    $service = DB::table('services')->where('id', $line->service_id)->lockForUpdate()->first();
                    if (!$service) throw ValidationException::withMessages(['services' => 'Không thể hoàn tồn kho.']);
                    if ($service->stock !== null) {
                        DB::table('services')->where('id', $line->service_id)->increment('stock', $line->quantity);
                        // Đồng bộ lịch sử kho với cơ chế đặt sân/hủy đơn hiện tại.
                        DB::table('service_stock_movements')->insert([
                            'service_id' => $line->service_id, 'booking_id' => $id,
                            'user_id' => $request->user()->id,
                            'action' => 'cancel_restore', 'quantity_change' => (int) $line->quantity,
                            'stock_before' => (int) $service->stock,
                            'stock_after' => (int) $service->stock + (int) $line->quantity,
                            'note' => 'Thành viên hủy đơn ' . $booking->booking_code,
                            'created_at' => now(),
                        ]);
                    }
                }
            }
            DB::table('bookings')->where('id', $id)->update([
                'status' => 'cancelled', 'cancelled_at' => now(),
                'stock_restored_at' => $booking->stock_restored_at ?? now(),
                'note' => trim(($booking->note ? $booking->note . "\n" : '') . 'Khách hủy: ' . ($request->input('reason') ?: 'Không ghi lý do')),
                'updated_at' => now(),
            ]);
            return ['id' => $id, 'status' => 'cancelled'];
        }, 3);
        return response()->json($result);
    }
}
