# Tổng hợp kết quả PHPUnit và MySQL

Ngày chạy: 27/11/2026

## Môi trường

- Hệ điều hành: Windows, môi trường Laragon.
- Phần cứng: Intel Core i7-8700, RAM 16 GB.
- PHP: 8.4.27.
- MySQL: 8.0.30.
- Dữ liệu: cơ sở dữ liệu QA, dữ liệu mẫu/giả lập; không thực hiện giao dịch ngân hàng thật.

## Kết quả

| Nhóm kiểm thử | Lệnh | Kết quả ghi nhận | Minh chứng |
|---|---|---|---|
| PHPUnit Feature tests | `php artisan test` | 21/21 ca đạt; 93 assertions; 13,38 giây | [Ảnh chụp terminal](evidence/phpunit-2026-11-27.png) |
| Tích hợp MySQL | `php kiemthu.php` | 14/14 ca đạt | [Ảnh chụp terminal](evidence/mysql-2026-11-27.png), [JSON gốc](mysql-2026-11-27.json) |

JSON ghi nhận mã lần chạy `20261127_140348_5baf9b57` và CSDL QA `fieldly_qa_20261127_140348_5baf9b57`. Không dùng dữ liệu thanh toán ngân hàng thật.
