# Báo cáo tiến độ tuần 03

**Ngày cập nhật:** 24/11/2026  
**Đề tài:** Xây dựng website đặt sân bóng đá mini

## Công việc đã thực hiện

- Rà soát nhánh `main` và đối chiếu README, mã nguồn, báo cáo kiểm thử cùng Phụ lục D.
- Sửa lệnh kiểm thử JavaScript để `npm run test:frontend` hoạt động với Node.js 24; khai báo Node.js tối thiểu `>=22` trong `package.json`.
- Chạy 4 tệp kiểm thử JavaScript trên Node.js 24.22.3: **33/33 ca đạt**. Log được lưu trong `docs/kiemthu/frontend-node-24.22.3.txt`.
- Đối chiếu `routes/console.php`: lệnh hoàn tất đơn được đăng ký chạy mỗi phút và lệnh nhắc lịch được đăng ký chạy mỗi 5 phút. Chưa xác nhận việc Scheduler và SMTP hoạt động đầu cuối.
- Cập nhật báo cáo và README để phân biệt kết quả kiểm thử tự động với bằng chứng nghiệm thu từng kịch bản.
- Cập nhật các báo cáo tuần 02–03 để thống nhất trạng thái và kết quả đang công bố trong repo.

## Tồn tại

- **0/28 kịch bản Phụ lục C có đầy đủ bằng chứng nghiệm thu.** Bảng hiện ghi 14 ca chưa chạy, 6 ca có bằng chứng liên quan và 8 ca có kiểm thử tự động cho một phần chức năng nhưng chưa kiểm chứng đầy đủ theo kịch bản. Các luồng báo giá (TC03–TC04), tồn kho không đủ (TC10), ngưỡng cọc (TC11–TC12), xác nhận đơn (TC13–TC14), chuỗi đặt trùng (TC23) và chống leo thang quyền (TC26) chưa chạy.
- Không chạy lại PHPUnit và kiểm thử MySQL trong môi trường này vì không có PHP, Composer hoặc MySQL. Kết quả hiện có: PHPUnit 21/21, MySQL 14/14; JSON gốc của lần chạy MySQL không có trong hồ sơ repo, nên bản tổng hợp không được xem là log gốc.
- Chưa có kiểm thử giao diện E2E hoặc đo tải qua Apache/máy chủ web. Phép đo hiện tại đi qua Laravel HTTP Kernel.
- Phân tích yêu cầu chưa có buổi trao đổi với chủ sân thực tế; quy trình và chính sách nghiệp vụ trong báo cáo là mô hình giả định, chưa được xác nhận với người dùng thật.
- Chưa có bằng chứng xác nhận giảng viên hướng dẫn đã được mời làm Collaborator. Cần kiểm tra đúng tài khoản GitHub và trạng thái lời mời.
- Mã nguồn ban đầu được đưa vào repo trong một commit lớn. Các commit tài liệu và sửa lỗi gần đây theo Conventional Commits; các phần việc tiếp theo cần tiếp tục được chia thành commit nhỏ theo tuần.

## Việc cần làm tiếp

- Chạy các ca PHP và MySQL còn thiếu trên môi trường Laragon với CSDL QA riêng; lưu log gốc vào `docs/kiemthu/` khi có thể xuất được.
- Bổ sung và chạy kiểm thử nghiệp vụ cho báo giá, thiếu tồn kho, ngưỡng cọc, xác nhận đơn, chuỗi đặt trùng và quyền đăng ký.
- Kiểm thử Scheduler, SMTP và giao diện E2E; chỉ cập nhật trạng thái nghiệm thu sau khi có kết quả chạy.
- Trao đổi với chủ sân thực tế, ghi ngày, vai trò người tham gia và nội dung xác nhận; cập nhật mô hình yêu cầu theo kết quả.
- Xác minh tài khoản GitHub của giảng viên và hoàn tất lời mời Collaborator.
- Tiếp tục cập nhật báo cáo tiến độ theo tuần và tạo commit Conventional Commits cho từng nhóm thay đổi.
