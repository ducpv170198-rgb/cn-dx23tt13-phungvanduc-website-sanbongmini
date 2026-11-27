# Ma trận 28 kịch bản kiểm thử nghiệp vụ

Trạng thái được đồng bộ với Phụ lục C của báo cáo. Kết quả 18 ca thủ công dựa trên xác nhận của người thực hiện; chưa có ảnh chụp hoặc biên bản riêng trong hồ sơ. Các ca tự động có tham chiếu tới TN hoặc PHPUnit trong phần kết quả mong đợi của báo cáo.

| Mã | Kịch bản | Trạng thái | Hồ sơ kiểm thử |
|---|---|---|---|
| TC01 | Tải lịch sân 5 cho ngày mai | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC02 | Tải lịch với ngày trong quá khứ hoặc quá 3 tháng | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC03 | Báo giá sân 5A ngày thường 16:30–18:00 | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC04 | Báo giá cùng khung giờ vào chủ nhật | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC05 | Đặt sân qua nửa đêm (22:00–00:30) | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC06 | Đặt khung giờ trong quá khứ | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC07 | Đặt Sân 5A 18:00–19:00 khi đã có đơn pending 18:30–19:30 | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC08 | Đặt liền kề: 19:00–20:00 sau đơn 18:00–19:00 cùng sân | Đạt (tự động) | Tự động: TN02 |
| TC09 | Hai yêu cầu đặt cùng sân, cùng giờ gửi đồng thời | Đạt (tự động) | Tự động: TN01, TN03 |
| TC10 | Đặt 3 chai Nước thể thao khi tồn kho còn 2 | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC11 | Đặt Sân 7A ngày thường 18:00–19:30 | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC12 | Đặt đơn tổng dưới 500.000 đ | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC13 | Quản lý duyệt đơn 540.000 đ chưa cọc | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC14 | Duyệt lại đơn đã confirmed | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC15 | Ghi nhận thu 2 lần với cùng idempotency_key và cùng nội dung | Đạt (tự động) | Tự động: TN04 |
| TC16 | Dùng lại idempotency_key với số tiền khác | Đạt (tự động) | Tự động: TN09 |
| TC17 | Thu số tiền làm tổng thu vượt tổng hóa đơn | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC18 | Hủy đơn đã cọc mà chưa hoàn tiền | Đạt (tự động) | Tự động: TN10 |
| TC19 | Hủy đơn pending có 2 chai nước (chưa thu tiền) | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC20 | Khách hủy đơn của người khác | Đạt (tự động) | Tự động: PHPUnit: MemberBookingsTest |
| TC21 | Tài khoản customer gọi /api/admin/dashboard | Đạt (tự động) | Tự động: PHPUnit: AdminDashboardValidationTest |
| TC22 | Gọi API admin không có token | Đạt (tự động) | Tự động: TN11: không token |
| TC23 | Tạo chuỗi 4 tuần, tuần thứ 3 đã có đơn trùng | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC24 | Hủy các buổi tương lai khi một buổi đã thu tiền | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC25 | Đơn confirmed, paid, đã qua giờ kết thúc; chạy bookings:complete-paid | Đạt (tự động) | Tự động: TN13; hóa đơn paid được thiết lập trực tiếp làm fixture, không kiểm thử thanh toán thật |
| TC26 | Đăng ký với email đã tồn tại; gửi trường role=admin khi đăng ký | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
| TC27 | Một tài khoản gửi hai lời mời tới cùng tin tìm đối | Đạt (tự động) | Tự động: PHPUnit: FriendlyMemberInvitationsTest |
| TC28 | Chủ tin chấp nhận một lời mời | Đạt (thủ công, chưa lưu bằng chứng) | Thủ công; chưa lưu bằng chứng riêng |
