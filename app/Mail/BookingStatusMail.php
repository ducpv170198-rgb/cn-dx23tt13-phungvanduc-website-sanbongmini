<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class BookingStatusMail extends Mailable {
 use Queueable, SerializesModels;
 public function __construct(public array $booking, public string $kind) {}
 public function build(): static {
  $subjects=['pending'=>'Yêu cầu đặt sân đã được tiếp nhận','confirmed'=>'Đơn đặt sân đã được xác nhận','cancelled'=>'Thông báo hủy đơn đặt sân','reminder'=>'Nhắc lịch đá bóng'];
  return $this->subject($subjects[$this->kind] ?? 'Thông báo đặt sân')
    ->view('emails.booking-status')->with(['booking'=>$this->booking,'kind'=>$this->kind]);
 }
}
