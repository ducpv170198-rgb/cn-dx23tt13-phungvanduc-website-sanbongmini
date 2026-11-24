# Báo cáo tiến độ tuần 03

**Ngày cập nhật:** 24/11/2026  
**Đề tài:** Xây dựng website đặt sân bóng đá mini

## Công việc đã thực hiện

- Rà soát nhánh `main` và đối chiếu README, mã nguồn, báo cáo kiểm thử cùng Phụ lục D.
- Sửa lệnh kiểm thử JavaScript để `npm run test:frontend` hoạt động với Node.js 24; khai báo Node.js tối thiểu `>=22` trong `package.json`.
- Chạy lại 4 tệp kiểm thử JavaScript trên Node.js 24.22.3: **33/33 ca đạt**. Log được lưu trong `docs/kiemthu/frontend-node-24.22.3.txt`.
- Đối chiếu `routes/console.php`: lệnh hoàn tất đơn được đăng ký chạy mỗi phút và lệnh nhắc lịch được đăng ký chạy mỗi 5 phút. Chưa xác nhận việc Scheduler và SMTP hoạt động đầu cuối.
- Bổ sung báo cáo tiến độ tuần 03 để ghi lại công việc và kết quả có thể tái lập.

## Tồn tại

- Còn 15/28 kịch bản nghiệp vụ chưa nghiệm thu; bộ kiểm thử JavaScript không chứng minh các luồng API về báo giá, tồn kho, tiền cọc, xác nhận đơn, đặt định kỳ hoặc phân quyền đăng ký.
- Không chạy lại PHPUnit và kiểm thử MySQL trong môi trường này vì không có PHP, Composer hoặc MySQL. JSON gốc của lần MySQL trước chưa được commit; báo cáo chưa coi bản chép tóm tắt là log gốc.
- Chưa có kiểm thử giao diện E2E hoặc đo tải qua Apache/máy chủ web.
- Phân tích nghiệp vụ vẫn dựa trên quy trình giả định. Khảo sát/phỏng vấn chủ sân thực tế không nằm trong phạm vi đã thống nhất.

## Việc cần làm tiếp

- Chạy các ca PHP và MySQL còn thiếu trên môi trường Laragon với CSDL QA riêng; lưu log/JSON gốc vào `docs/kiemthu/`.
- Bổ sung ca kiểm thử nghiệp vụ cho báo giá, ngưỡng cọc, xác nhận lặp, thiếu tồn kho, chuỗi đặt trùng và đăng ký không leo thang quyền.
- Kiểm thử Scheduler, SMTP và giao diện E2E; chỉ cập nhật trạng thái nghiệm thu sau khi có kết quả chạy.
- Bổ sung tài liệu học thuật về bài toán đặt lịch và kiểm thử đồng thời vào báo cáo.
