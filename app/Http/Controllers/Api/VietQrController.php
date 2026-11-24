<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VietQrController extends Controller
{
    /** Show transfer instructions only; this endpoint NEVER confirms payment. */
    public function show(Request $request, int $bookingId)
    {
        $user = $request->user();
        $booking = DB::table('bookings')->where('id', $bookingId)->first();
        if (!$booking) abort(404);
        if (!$user || ($user->role !== 'admin' && $user->role !== 'staff' && (int) $booking->customer_id !== (int) $user->id)) {
            abort(403);
        }
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages(['booking' => 'Đơn đã hủy.']);
        }
        $invoice = DB::table('invoices')->where('booking_id', $bookingId)->first();
        if (!$invoice) abort(404);
        $bank = (string) config('fieldly_payment.bank_bin');
        $account = (string) config('fieldly_payment.account_number');
        $name = (string) config('fieldly_payment.account_name');
        $fixedDeposit = (int) config('fieldly_payment.deposit_amount_vnd', 100000);
        $depositThreshold = (int) config('fieldly_payment.deposit_threshold_vnd', 500000);
        if (!preg_match('/^[0-9]{6}$/', $bank) || !preg_match('/^[0-9]{4,24}$/', $account)
            || $name === '' || $fixedDeposit < 1 || $depositThreshold < 0) {
            return response()->json(['message' => 'Chủ sân chưa cấu hình thông tin chuyển khoản hợp lệ.'], 503);
        }
        $net = DB::table('payments')->where('invoice_id', $invoice->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt' THEN amount ELSE -amount END), 0) AS net")
            ->value('net');
        // VND amounts are whole numbers. Never trust client-supplied amount.
        $total = (int) $invoice->total;
        $paid = max(0, (int) $net);
        // Yêu cầu cọc khi tổng giá trị đơn TỪ ngưỡng trở lên (bao gồm bằng).
        $deposit = $total >= $depositThreshold ? min($total, $fixedDeposit) : 0;
        $remaining = max(0, $total - $paid);
        $amount = min($remaining, max(0, $deposit - $paid));
        // Đã đủ cọc thì không yêu cầu cọc lần nữa; phần còn lại thanh toán theo quy trình khác.
        $reference = 'FD' . $bookingId . 'I' . $invoice->id;
        $url = null;
        if ($amount > 0) {
            $url = 'https://img.vietqr.io/image/' . rawurlencode($bank) . '-' . rawurlencode($account)
                . '-compact2.png?' . http_build_query([
                    'amount' => $amount, 'addInfo' => $reference, 'accountName' => $name,
                ], '', '&', PHP_QUERY_RFC3986);
        }
        return response()->json([
            'booking_id' => $bookingId,
            'invoice_id' => $invoice->id,
            'deposit_required_vnd' => $deposit,
            'deposit_threshold_vnd' => $depositThreshold,
            'deposit_mandatory' => $deposit > 0 && $paid < $deposit,
            'amount_vnd' => $amount,
            'remaining_vnd' => $remaining,
            'transfer_reference' => $reference,
            'bank_bin' => $bank,
            'account_number' => $account,
            'account_name' => $name,
            'qr_image_url' => $url,
            'payment_verified' => false,
            'notice' => 'Mã QR chỉ hướng dẫn chuyển khoản. Chỉ cập nhật đã thanh toán sau khi xác minh giao dịch ngân hàng.',
        ]);
    }
}
