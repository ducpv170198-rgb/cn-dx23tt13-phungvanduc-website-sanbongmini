<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingSettlementController extends Controller
{
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function cancel(Request $request, int $id)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        // Always lock booking, then invoice, then service rows in ascending service_id.
        $result = DB::transaction(function () use ($id, $data) {
            $booking = DB::table('bookings')->where('id', $id)->lockForUpdate()->first();
            if (!$booking) abort(404);
            if ($booking->status === 'cancelled') {
                return ['already_cancelled' => true, 'booking_id' => $id];
            }
            if ($booking->status === 'completed') $this->fail('status', 'Không thể hủy đơn đã hoàn tất.');
            if (\Illuminate\Support\Carbon::parse($booking->start_at)->lte(now())) {
                $this->fail('start_at', 'Không thể hủy khi giờ đá đã bắt đầu.');
            }
            $invoice = DB::table('invoices')->where('booking_id', $id)->lockForUpdate()->first();
            if (!$invoice) $this->fail('invoice', 'Đơn chưa có hóa đơn.');
            $netPaid = (int) DB::table('payments')->where('invoice_id', $invoice->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END), 0) AS net")
                ->value('net');
            // Paid orders require an explicit refund workflow; do not silently discard money.
            if ($netPaid > 0) $this->fail('payment', 'Đơn đã thu tiền; hãy hoàn tiền trước khi hủy.');
            if ($booking->stock_restored_at === null) {
                $lines = DB::table('booking_services')->where('booking_id', $id)
                    ->orderBy('service_id')->get();
                foreach ($lines as $line) {
                    $service = DB::table('services')->where('id', $line->service_id)->lockForUpdate()->first();
                    if (!$service) $this->fail('services', 'Không tìm thấy dịch vụ để hoàn kho.');
                    if ($service->stock !== null) {
                        DB::table('services')->where('id', $line->service_id)->increment('stock', $line->quantity);
                        DB::table('service_stock_movements')->insert([
                            'service_id' => $line->service_id, 'booking_id' => $id,
                            'user_id' => request()->user()?->id,
                            'action' => 'booking_cancel', 'quantity_change' => (int) $line->quantity,
                            'stock_before' => (int) $service->stock,
                            'stock_after' => (int) $service->stock + (int) $line->quantity,
                            'note' => 'Hoàn kho khi hủy đơn ' . $booking->booking_code,
                            'created_at' => now(),
                        ]);
                    }
                }
            }
            DB::table('bookings')->where('id', $id)->update([
                'status' => 'cancelled', 'cancelled_at' => now(),
                'stock_restored_at' => $booking->stock_restored_at ?? now(),
                'note' => trim(($booking->note ? $booking->note . "\n" : '') . 'Lý do hủy: ' . ($data['reason'] ?? 'Không cung cấp')),
                'updated_at' => now(),
            ]);
            return ['already_cancelled' => false, 'booking_id' => $id];
        }, 3);
        return response()->json($result);
    }

    public function recordPayment(Request $request, int $invoiceId)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:receipt,refund'],
            'method' => ['required', 'in:cash,vietqr'],
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $result = DB::transaction(function () use ($invoiceId, $data, $request) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) abort(404);
            $existing = DB::table('payments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ((int)$existing->invoice_id !== $invoiceId || $existing->kind !== $data['kind'] ||
                    $existing->method !== $data['method'] || (int)$existing->amount !== (int)$data['amount'] ||
                    ($existing->reference ?? null) !== ($data['reference'] ?? null)) {
                    $this->fail('idempotency_key', 'Khóa đã được dùng với nội dung khác.');
                }
                return ['id' => $existing->id, 'replayed' => true];
            }
            $booking = DB::table('bookings')->where('id', $invoice->booking_id)->first();
            if (!$booking || $booking->status === 'cancelled') $this->fail('invoice', 'Đơn không thể nhận giao dịch mới.');
            $net = (int) DB::table('payments')->where('invoice_id', $invoiceId)
                ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END), 0) AS net")
                ->value('net');
            $amount = (int) $data['amount'];
            $total = (int) $invoice->total;
            if ($data['kind'] === 'receipt' && $net + $amount > $total) $this->fail('amount', 'Số tiền thu vượt tổng hóa đơn.');
            if ($data['kind'] === 'refund' && $amount > $net) $this->fail('amount', 'Số tiền hoàn vượt số tiền thực thu.');
            $id = DB::table('payments')->insertGetId([
                'invoice_id' => $invoiceId, 'idempotency_key' => $data['idempotency_key'],
                'kind' => $data['kind'], 'method' => $data['method'], 'amount' => $amount,
                'reference' => $data['reference'] ?? null, 'recorded_by' => $request->user()?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $newNet = $net + ($data['kind'] === 'receipt' ? $amount : -$amount);
            DB::table('invoices')->where('id', $invoiceId)->update([
                'payment_status' => $newNet === 0 ? ($data['kind'] === 'refund' ? 'refunded' : 'unpaid') :
                    ($newNet >= $total ? 'paid' : 'deposit_paid'),
                'paid_at' => $newNet >= $total ? now() : null,
                'updated_at' => now(),
            ]);
            return ['id' => $id, 'replayed' => false, 'net_paid' => $newNet];
        }, 3);
        $bookingId = DB::table('invoices')->where('id', $invoiceId)->value('booking_id');
        if ($bookingId !== null) app(\App\Services\BookingLifecycle::class)->completePaid((int) $bookingId);
        return response()->json($result, $result['replayed'] ? 200 : 201);
    }


    // Ghi nhận phần tiền còn lại đã thực nhận, kể cả trước giờ đá.
    // Chỉ áp dụng cho đơn đã xác nhận; kiểm tra lại số dư trong transaction.
    public function settleAfterMatch(Request $request, int $invoiceId)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:100'],
            'method' => ['required', 'in:cash,vietqr'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);
        $result = DB::transaction(function () use ($invoiceId, $data, $request) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->first();
            if (!$invoice) abort(404);
            $existing = DB::table('payments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ((int) $existing->invoice_id !== $invoiceId || $existing->kind !== 'receipt'
                    || $existing->method !== $data['method'] || (int) $existing->amount !== (int) $data['amount']) {
                    $this->fail('idempotency_key', 'Mã giao dịch đã dùng với nội dung khác.');
                }
                return ['id' => $existing->id, 'replayed' => true];
            }
            $booking = DB::table('bookings')->where('id', $invoice->booking_id)->first();
            if (!$booking || !in_array($booking->status, ['confirmed', 'completed'], true)) {
                $this->fail('booking', 'Đơn phải được xác nhận và không bị hủy.');
            }
            $net = (int) DB::table('payments')->where('invoice_id', $invoiceId)
                ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount WHEN kind = 'refund' THEN -amount ELSE 0 END), 0) AS net")
                ->value('net');
            $remaining = (int) $invoice->total - $net;
            if ($remaining <= 0) $this->fail('amount', 'Hóa đơn đã được thanh toán đủ.');
            if ((int) $data['amount'] !== $remaining) {
                $this->fail('amount', 'Số tiền còn lại đã thay đổi. Hãy tải lại hóa đơn.');
            }
            $id = DB::table('payments')->insertGetId([
                'invoice_id' => $invoiceId,
                'idempotency_key' => $data['idempotency_key'],
                'kind' => 'receipt', 'method' => $data['method'],
                'amount' => $remaining, 'reference' => null,
                'recorded_by' => $request->user()?->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('invoices')->where('id', $invoiceId)->update([
                'payment_status' => 'paid', 'paid_at' => now(), 'updated_at' => now(),
            ]);
            return ['id' => $id, 'replayed' => false, 'net_paid' => (int) $invoice->total, 'remaining' => 0];
        }, 3);
        $bookingId = DB::table('invoices')->where('id', $invoiceId)->value('booking_id');
        if ($bookingId !== null) app(\App\Services\BookingLifecycle::class)->completePaid((int) $bookingId);
        return response()->json($result, $result['replayed'] ? 200 : 201);
    }

    public function cashflow(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        $rows = DB::table('payments')
            ->selectRaw("DATE(created_at) AS day, SUM(CASE WHEN kind = 'receipt' THEN amount ELSE 0 END) AS receipts, SUM(CASE WHEN kind = 'refund' THEN amount ELSE 0 END) AS refunds, SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END) AS net_cashflow")
            ->whereBetween('created_at', [\Carbon\Carbon::parse($data['from'])->startOfDay(), \Carbon\Carbon::parse($data['to'])->endOfDay()])
            ->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        return response()->json(['rows' => $rows]);
    }
}
