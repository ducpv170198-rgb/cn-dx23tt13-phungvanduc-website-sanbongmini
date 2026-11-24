<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingStatusController extends Controller
{
    public function show(Request $request)
    {
        $data = $request->validate([
            'booking_code' => ['required', 'string', 'max:50'],
            'customer_phone' => ['required', 'string', 'max:30'],
        ]);

        // Cần đồng thời mã đặt sân khó đoán và số điện thoại của người đặt.
        // Không trả dữ liệu cá nhân hoặc thông tin thanh toán qua endpoint công khai.
        $booking = DB::table('bookings')
            ->where('booking_code', $data['booking_code'])
            ->where('customer_phone', $data['customer_phone'])
            ->first(['booking_code', 'status', 'start_at', 'end_at', 'pitch_id']);

        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy đơn đặt sân tương ứng.'], 404);
        }

        $pitch = DB::table('pitches')->where('id', $booking->pitch_id)->value('name');
        return response()->json(['data' => [
            'booking_code' => $booking->booking_code,
            'status' => $booking->status,
            'pitch_name' => $pitch,
            'start_at' => $booking->start_at,
            'end_at' => $booking->end_at,
        ]])->header('Cache-Control', 'no-store, private');
    }
}
