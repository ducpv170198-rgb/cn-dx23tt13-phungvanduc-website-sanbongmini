# Tổng hợp kết quả PHPUnit và MySQL

Ngày ghi nhận: 24/11/2026

## Môi trường

- Hệ điều hành: Windows, môi trường Laragon.
- Phần cứng: Intel Core i7-8700, RAM 16 GB.
- PHP: 8.4.27.
- MySQL: 8.0.30.
- Dữ liệu: cơ sở dữ liệu QA, dữ liệu mẫu/giả lập; không được thực hiện giao dịch ngân hàng thật.

## Kết quả

| Nhóm kiểm thử | Lệnh | Kết quả ghi nhận |
|---|---|---|
| PHPUnit Feature tests | `php artisan test` | 21/21 ca đạt; 93 assertions; 1,11 giây |
| Tích hợp MySQL | `php kiemthu.php` | 14/14 ca đạt |

## Tính chất của bản ghi

Đây là bản tổng hợp các số liệu kiểm thử đã ghi nhận trong báo cáo đồ án, không phải log đầu ra nguyên văn. Bản ghi JSON gốc của lần chạy MySQL hiện không có trong hồ sơ nguồn; vì vậy tệp này không tái tạo hoặc thay thế dữ liệu JSON đó. Việc chạy lại cần môi trường tương thích và cơ sở dữ liệu QA riêng.
