<?php
namespace App\Jobs;
use App\Mail\BookingStatusMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
class SendBookingEmail implements ShouldQueue {
 use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 public int $tries=3;
 public function __construct(public int $deliveryId) {}
 public function handle(): void {
  $d=DB::table('booking_email_deliveries')->where('id',$this->deliveryId)->first();
  if (!$d || $d->status==='sent') return;
  $b=DB::table('bookings')->join('pitches','pitches.id','=','bookings.pitch_id')
    ->select('bookings.*','pitches.name as pitch_name')->where('bookings.id',$d->booking_id)->first();
  if (!$b || !$b->customer_email || $b->customer_email!==$d->recipient) return;
  if ($d->kind==='reminder' && !in_array($b->status,['confirmed'],true)) return;
  if ($d->kind==='confirmed' && $b->status!=='confirmed') return;
  if ($d->kind==='pending' && $b->status!=='pending') return;
  if ($d->kind==='cancelled' && $b->status!=='cancelled') return;
  Mail::to($d->recipient)->send(new BookingStatusMail((array)$b,$d->kind));
  DB::table('booking_email_deliveries')->where('id',$d->id)->update(['status'=>'sent','sent_at'=>now(),'updated_at'=>now()]);
 }
}
