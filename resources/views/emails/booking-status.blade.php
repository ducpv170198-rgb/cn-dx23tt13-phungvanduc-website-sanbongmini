<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><title>Fieldly</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#17362b">
<h2>Fieldly – Thông báo đặt sân</h2>
@if($kind === 'pending')<p>Yêu cầu đặt sân của bạn đã được tiếp nhận và đang chờ xác nhận.</p>
@elseif($kind === 'confirmed')<p>Đơn đặt sân của bạn đã được xác nhận.</p>
@elseif($kind === 'cancelled')<p>Đơn đặt sân của bạn đã bị hủy. Vui lòng liên hệ chủ sân nếu cần hỗ trợ.</p>
@elseif($kind === 'reminder')<p>Đây là email nhắc lịch thi đấu sắp tới.</p>@endif
<p><strong>Mã đặt sân:</strong> {{ $booking['booking_code'] }}</p>
<p><strong>Khách hàng:</strong> {{ $booking['customer_name'] }}</p>
<p><strong>Sân:</strong> {{ $booking['pitch_name'] }}</p>
<p><strong>Bắt đầu:</strong> {{ $booking['start_at'] }}</p>
<p><strong>Kết thúc:</strong> {{ $booking['end_at'] }}</p>
<p><strong>Trạng thái:</strong> {{ $booking['status'] }}</p>
<p>Trân trọng,<br>Fieldly</p>
</body></html>
