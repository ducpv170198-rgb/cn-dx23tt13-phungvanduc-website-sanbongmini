# Báo cáo tiến độ tuần 03

**Ngày cập nhật:** 27/11/2026
**Đề tài:** Xây dựng website đặt sân bóng đá mini

## Công việc đã thực hiện

- Rà soát README, mã nguồn và nội dung báo cáo kiểm thử để thống nhất phạm vi chức năng và kết quả.
- Chạy `php artisan test` trên Windows/Laragon, PHP 8.4.27: **21 ca đạt, 93 assertions**; kết quả được lưu bằng ảnh chụp trong `docs/kiemthu/evidence/`.
- Chạy `php kiemthu.php` trên Windows/Laragon với MySQL 8.0.30 và cơ sở dữ liệu QA: **14/14 ca đạt**. Bản ghi JSON gốc được lưu tại `docs/kiemthu/mysql-2026-11-27.json`; ảnh chụp kết quả được lưu cùng thư mục minh chứng.
- Đối chiếu Phụ lục C với bộ kiểm thử: 28 kịch bản được chia thành 10 ca có kết quả tự động và 18 ca kiểm tra thủ công. Các ca thủ công được báo cáo đạt nhưng chưa lưu ảnh hoặc biên bản kết quả theo từng ca.
- Giữ kết quả JavaScript riêng: 4 tệp kiểm thử, 33/33 ca đạt trên Node.js 24.22.3; `package.json` yêu cầu Node.js từ phiên bản 22.

## Tồn tại

- Chưa có khảo sát hoặc phỏng vấn chủ sân; quy trình và yêu cầu nghiệp vụ hiện được xây dựng theo mô hình giả định, tài liệu công khai và phạm vi đề tài.
- 18 kịch bản kiểm tra thủ công chưa có bằng chứng riêng theo từng ca, gồm các luồng báo giá, tồn kho, tiền cọc, xác nhận đơn, đặt chuỗi và phân quyền đăng ký.
- Chưa có kiểm thử giao diện E2E hoặc phép đo tải qua Apache/máy chủ web. VietQR mới hỗ trợ hướng dẫn chuyển khoản; hệ thống chưa tự đối soát với ngân hàng.

## Việc cần làm tiếp

- Lưu ảnh chụp hoặc biên bản ngắn cho từng ca kiểm thử thủ công quan trọng, gồm dữ liệu đầu vào và kết quả thực tế.
- Tổ chức trao đổi với chủ sân để xác nhận quy tắc báo giá, tiền cọc, hủy và hoàn tiền; cập nhật ngày, vai trò người trao đổi và nội dung đã xác nhận vào mục 1.3 khi có kết quả.
- Kiểm tra Scheduler, SMTP và luồng giao diện E2E; chỉ bổ sung kết luận sau khi có kết quả chạy.
- Tiếp tục rà soát tính nhất quán giữa repo, báo cáo, bảng kịch bản và minh chứng.
