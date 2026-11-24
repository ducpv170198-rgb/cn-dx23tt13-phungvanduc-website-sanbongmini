<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingPricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(Request $request, BookingPricingService $pricing)
    {
        $data = $request->validate([
            'pitch_id' => ['required', 'integer', 'exists:pitches,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'note' => ['nullable', 'string'],
            'services' => ['sometimes', 'array'],
            'services.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        if ($start->second || $end->second) {
            throw ValidationException::withMessages(['start_at' => 'Thời gian phải theo mốc phút.']);
        }

        $booking = DB::transaction(function () use ($data, $start, $end, $request, $pricing) {
            // Always lock pitch first. All booking writers must follow this lock protocol.
            $pitch = DB::table('pitches')->where('id', $data['pitch_id'])
                ->where('status', 'active')->lockForUpdate()->first();
            if (!$pitch) {
                throw ValidationException::withMessages(['pitch_id' => 'Sân không khả dụng.']);
            }
            $overlap = DB::table('bookings')->where('pitch_id', $pitch->id)
                ->whereIn('status', ['pending', 'confirmed', 'completed'])
                ->where('start_at', '<', $end)->where('end_at', '>', $start)->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_at' => 'Khung giờ đã được đặt.']);
            }
            $pitchPrice = $pricing->pitchPrice((int) $pitch->id, $start, $end);
            $serviceLines = [];
            $serviceTotal = 0;
            $requested = collect($data['services'] ?? [])->sortBy('service_id');
            foreach ($requested as $item) {
                $service = DB::table('services')->where('id', $item['service_id'])
                    ->where('is_active', true)->lockForUpdate()->first();
                if (!$service) {
                    throw ValidationException::withMessages(['services' => 'Dịch vụ không khả dụng.']);
                }
                $qty = (int) $item['quantity'];
                if ($service->stock !== null && $service->stock < $qty) {
                    throw ValidationException::withMessages(['services' => "Không đủ tồn kho: {$service->name}."]);
                }
                $unit = (string) $service->price;
                if (!preg_match('/^\d+(?:\.0{1,2})?$/', $unit)) {
                    throw ValidationException::withMessages(['services' => 'Đơn giá dịch vụ phải là VND nguyên.']);
                }
                $lineTotal = (int) $unit * $qty;
                $serviceTotal += $lineTotal;
                $serviceLines[] = ['service_id' => $service->id, 'name' => $service->name,
                    'quantity' => $qty, 'unit_price' => (int) $unit, 'line_total' => $lineTotal];
                if ($service->stock !== null) {
                    DB::table('services')->where('id', $service->id)->decrement('stock', $qty);
                }
            }
            $total = $pitchPrice + $serviceTotal;
            $now = now();
            // Một mã dùng chung cho đơn đặt sân và hóa đơn tương ứng.
            $sharedCode = 'FD' . $now->format('ymd') . strtoupper(Str::random(12));
            $bookingId = DB::table('bookings')->insertGetId([
                'booking_code' => $sharedCode,
                'pitch_id' => $pitch->id, 'customer_id' => $request->user('sanctum')?->id,
                'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'start_at' => $start, 'end_at' => $end, 'status' => 'pending', 'source' => 'web',
                'note' => $data['note'] ?? null, 'pitch_price' => $pitchPrice,
                'service_total' => $serviceTotal, 'total_amount' => $total,
                'created_by' => $request->user('sanctum')?->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $invoiceId = DB::table('invoices')->insertGetId([
                'invoice_code' => $sharedCode,
                'booking_id' => $bookingId, 'subtotal' => $total, 'discount' => 0,
                'total' => $total, 'payment_status' => 'unpaid',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId, 'description' => 'Thuê sân: ' . $pitch->name,
                'quantity' => 1, 'unit_price' => $pitchPrice, 'line_total' => $pitchPrice,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($serviceLines as $line) {
                DB::table('booking_services')->insert([
                    'booking_id' => $bookingId, 'service_id' => $line['service_id'],
                    'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                // Keep a trace of stock used by a booking (only for finite stock).
                $stockAfter = DB::table('services')->where('id', $line['service_id'])->value('stock');
                if ($stockAfter !== null) {
                    DB::table('service_stock_movements')->insert([
                        'service_id' => $line['service_id'], 'booking_id' => $bookingId,
                        'user_id' => $request->user('sanctum')?->id,
                        'action' => 'booking', 'quantity_change' => -$line['quantity'],
                        'stock_before' => (int) $stockAfter + $line['quantity'],
                        'stock_after' => (int) $stockAfter,
                        'note' => 'Trừ kho cho đơn ' . $sharedCode,
                        'created_at' => $now,
                    ]);
                }
                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId, 'description' => $line['name'],
                    'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return DB::table('bookings')->find($bookingId);
        }, 3);
        app(\App\Services\BookingEmailService::class)->queue((int) $booking->id, 'pending');
        // Chỉ hiển thị VietQR sau khi đơn đã được tạo thành công.
        // Không đánh dấu đã thanh toán hoặc tự xác nhận đơn.
        $deposit = null;
        $depositNotice = null;
        $depositThreshold = (int) config('fieldly_payment.deposit_threshold_vnd', 500000);
        $depositAmount = (int) config('fieldly_payment.deposit_amount_vnd', 100000);
        if ((int) $booking->total_amount >= $depositThreshold) {
            $bankBin = trim((string) config('fieldly_payment.bank_bin', ''));
            $account = trim((string) config('fieldly_payment.account_number', ''));
            $accountName = trim((string) config('fieldly_payment.account_name', ''));
            if ($depositThreshold >= 0 && $depositAmount > 0
                && preg_match('/^\d{6}$/', $bankBin)
                && preg_match('/^\d{4,24}$/', $account)
                && $accountName !== '') {
                $reference = 'FD' . $booking->id;
                $amount = min((int) $booking->total_amount, $depositAmount);
                $qrUrl = 'https://img.vietqr.io/image/' . rawurlencode($bankBin)
                    . '-' . rawurlencode($account) . '-compact2.png?'
                    . http_build_query([
                        'amount' => $amount,
                        'addInfo' => $reference,
                        'accountName' => $accountName,
                    ], '', '&', PHP_QUERY_RFC3986);
                $deposit = [
                    'amount_vnd' => $amount,
                    'account_number' => $account,
                    'account_name' => $accountName,
                    'transfer_reference' => $reference,
                    'qr_image_url' => $qrUrl,
                ];
            } else {
                // Không im lặng ẩn VietQR: thông báo để chủ sân sửa cấu hình .env.
                $depositNotice = 'Đơn này cần đặt cọc nhưng chủ sân chưa cấu hình VietQR hợp lệ. Vui lòng liên hệ quản lý để lấy thông tin chuyển khoản.';
            }
        }

        return response()->json([
            'message' => 'Đã tạo đơn, chờ quản lý xác nhận.',
            'data' => $booking,
            'deposit' => $deposit,
            'deposit_notice' => $depositNotice,
        ], 201);
    }
}
