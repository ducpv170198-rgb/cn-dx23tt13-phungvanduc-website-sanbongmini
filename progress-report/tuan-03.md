# Báo cáo tiến độ tuần 03

**Thời gian:** 30/11/2026 - 06/12/2026  
**Đề tài:** Xây dựng website đặt sân bóng đá mini

## Công việc đã thực hiện

- Rà soát nhánh `main` và đối chiếu README, mã nguồn, báo cáo kiểm thử cùng Phụ lục D.
- Sửa lệnh kiểm thử JavaScript để `npm run test:frontend` hoạt động với Node.js 24; khai báo Node.js tối thiểu `>=22` trong `package.json`.
- Chạy 4 tệp kiểm thử JavaScript trên Node.js 24.22.3: **33/33 ca đạt**. Log được lưu trong `docs/kiemthu/frontend-node-24.22.3.txt`.
- Đối chiếu `routes/console.php`: lệnh hoàn tất đơn được đăng ký chạy mỗi phút và lệnh nhắc lịch được đăng ký chạy mỗi 5 phút. Chưa xác nhận việc Scheduler và SMTP hoạt động đầu cuối.
- Chuẩn hóa Phụ lục C còn 24 kịch bản TC01–TC24. Theo kết quả người thực hiện xác nhận, 10 ca đạt bằng kiểm thử tự động và 14 ca đạt bằng kiểm tra thủ công theo tiêu chí của từng ca.
- Cập nhật phần tổng kết và ma trận yêu cầu để dùng cùng mã TC với Phụ lục C.

## Tồn tại

- 14 ca kiểm tra thủ công chưa có log tự động hoặc ảnh chụp kết quả được lưu kèm repo. Các kết quả đó được ghi riêng là kiểm tra thủ công để phân biệt với 10 ca tự động.
- Kết quả tự động hiện có gồm PHPUnit 21/21 ca, MySQL 14/14 ca và JavaScript 33/33 ca trên 4 tệp. Bản ghi JSON gốc của lần chạy MySQL chưa được lưu trong repo; tài liệu tổng hợp không thay thế log gốc.
- Không chạy lại PHPUnit và kiểm thử MySQL trong môi trường hiện tại vì không có PHP, Composer hoặc MySQL.
- Chưa có kiểm thử giao diện E2E hoặc đo tải qua Apache/máy chủ web. Phép đo hiện tại đi qua Laravel HTTP Kernel.
- Phân tích yêu cầu chưa có buổi trao đổi với chủ sân thực tế; quy trình và chính sách nghiệp vụ trong báo cáo là mô hình giả định, chưa được xác nhận với người dùng thật.
- Chưa có bằng chứng xác nhận giảng viên hướng dẫn đã được mời làm Collaborator. Cần kiểm tra đúng tài khoản GitHub và trạng thái lời mời.
- Mã nguồn ban đầu được đưa vào repo trong một commit lớn. Các commit tài liệu và sửa lỗi gần đây theo Conventional Commits; các phần việc tiếp theo cần tiếp tục được chia thành commit nhỏ theo tuần.

## Việc cần làm tiếp

- Lưu biên bản hoặc ảnh kết quả cho các ca kiểm tra thủ công nếu cần đưa bằng chứng bổ sung vào repo.
- Kiểm thử Scheduler, SMTP và giao diện E2E; chỉ cập nhật trạng thái nghiệm thu sau khi có kết quả chạy.
- Trao đổi với chủ sân thực tế, ghi ngày, vai trò người tham gia và nội dung xác nhận; cập nhật mô hình yêu cầu theo kết quả.
- Xác minh tài khoản GitHub của giảng viên và hoàn tất lời mời Collaborator.
- Tiếp tục cập nhật báo cáo tiến độ theo tuần và tạo commit Conventional Commits cho từng nhóm thay đổi.
