<?php
namespace App\Console\Commands;
use App\Services\BookingEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class QueueBookingReminders extends Command {
 protected $signature='fieldly:queue-reminders';
 protected $description='Queue reminder emails for confirmed bookings starting in 55-65 minutes';
 public function handle(BookingEmailService $emails): int {
  $start=now()->addMinutes(55); $end=now()->addMinutes(65);
  DB::table('bookings')->where('status','confirmed')->whereNotNull('customer_email')
   ->where('start_at','>=',$start)->where('start_at','<',$end)
   ->orderBy('id')->chunkById(100,function($bookings) use($emails) {
    foreach($bookings as $b) $emails->queue($b->id,'reminder');
   });
  return self::SUCCESS;
 }
}
