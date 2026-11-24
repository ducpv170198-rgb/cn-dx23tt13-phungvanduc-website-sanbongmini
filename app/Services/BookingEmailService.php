<?php
namespace App\Services;
use App\Jobs\SendBookingEmail;
use Illuminate\Support\Facades\DB;
class BookingEmailService {
 public function queue(int $bookingId,string $kind): bool {
  if (!in_array($kind,['pending','confirmed','cancelled','reminder'],true)) throw new \InvalidArgumentException('Unknown email kind');
  $b=DB::table('bookings')->where('id',$bookingId)->first();
  if (!$b || !$b->customer_email || !filter_var($b->customer_email,FILTER_VALIDATE_EMAIL)) return false;
  // One notification per booking and kind. Retry failed jobs via queue worker.
  $key='booking:'.$bookingId.':'.$kind;
  $inserted=DB::table('booking_email_deliveries')->insertOrIgnore([
    'booking_id'=>$bookingId,'kind'=>$kind,'recipient'=>$b->customer_email,
    'dedupe_key'=>$key,'status'=>'queued','created_at'=>now(),'updated_at'=>now()
  ]);
  if ($inserted) {
   $id=DB::table('booking_email_deliveries')->where('dedupe_key',$key)->value('id');
   try {
    SendBookingEmail::dispatch($id)->afterCommit();
   } catch (\Throwable $e) {
    report($e);
   }
  }
  return (bool)$inserted;
 }
}
