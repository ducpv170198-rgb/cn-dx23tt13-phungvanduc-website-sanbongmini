<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class BookingLifecycle
{
    public function completePaid(?int $bookingId = null): int
    {
        // start_at/end_at lưu giờ địa phương Việt Nam (DATETIME không kèm múi giờ).
        $now = now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        $query = DB::table('bookings')
            ->where('status', 'confirmed')
            ->where('end_at', '<=', $now)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.booking_id', 'bookings.id')
                    ->where('invoices.payment_status', 'paid');
            })
            ;
        if ($bookingId !== null) $query->where('bookings.id', $bookingId);
        return $query->update(['status' => 'completed', 'updated_at' => now('Asia/Ho_Chi_Minh')]);

    }
}
