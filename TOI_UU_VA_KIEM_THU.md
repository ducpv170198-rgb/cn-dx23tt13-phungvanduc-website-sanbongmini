# Bản tối ưu 24/11/2026
Nguồn: sanbong-mini(6).rar. Không thay đổi migration hoặc dữ liệu MySQL.

## Cập nhật
- Chặn hủy lịch tại API thực tế BookingSettlementController khi đã đến giờ bắt đầu; kiểm tra trong transaction sau khóa bản ghi.
- Bỏ phương thức cancel không dùng trong BookingAdminController, bỏ lệnh inspire, sửa test chuyển hướng trang chủ.
- Chuyển tiếp URL HTML cũ sang Vue. Giữ vendor, public/build, middleware đồng bộ lịch và các lệnh bảo trì.
- Bổ sung test AdminCancellationTimeTest (chưa chạy được).

## Các mục loại khỏi gói
- `public/account.html`
- `public/admin-connected.html`
- `public/customer-connected.html`
- `public/tim-doi.html`
- `public/fieldly-api.js`
- `resources/views/welcome.blade.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `resources/js/bootstrap.js`
- `public/icon-192.png`
- `tests/Unit/ExampleTest.php`
- `.env`
- `bootstrap/cache/packages.php`
- `bootstrap/cache/services.php`
- `storage/logs/laravel.log`
- `storage/framework/views/2a001a84e9250116e61f1ecaaf7ed2f3.php`
- `storage/framework/sessions/P5OKdXMAuY5LTA8GBc1VF7c0pjVhQNJmvoI0Rmzz`
- `storage/framework/cache/data/44/d1/44d142505dd1a3b497197e2f459d2aec779e9ed6`
- `storage/framework/cache/data/c2/4f/c24f92750673b2169c8cb7bfbc999cfa8457dc7c`
- `node_modules/`

## Kiểm thử
- 77 file PHP ứng dụng/cấu hình/migration/test kiểm tra cú pháp đạt; hai file classmap cũng đạt.
- Node: 33/33 test đạt.
- Manifest Vite: tất cả tài nguyên tồn tại.
- PHPUnit chưa chạy được do thiếu dom, mbstring, xmlwriter; route:list cũng bị chặn bởi thiếu mbstring. Chưa kiểm thử MySQL hoặc Laragon thực tế.

## Cập nhật máy đang sử dụng
1. Sao lưu thư mục cũ và database. Giải nén vào thư mục mới, không chép đè lên thư mục cũ vì các file dư sẽ còn lại.
2. Chép file .env cũ sang thư mục mới (giữ APP_KEY, DB_DATABASE và cấu hình hiện tại); đặt APP_URL=http://sanbong-mini.test và QUEUE_CONNECTION=sync.
3. Đổi tên thư mục cũ để sao lưu, đưa thư mục mới vào C:/laragon/www/sanbong-mini. Apache DocumentRoot phải là public (xem LARAGON_VHOST.conf.example).
4. Laragon dùng PHP >=8.4.1 với các extension Laravel cần. Start All, truy cập http://sanbong-mini.test/app. Không cần npm build, artisan serve hoặc schedule:work để sử dụng nghiệp vụ chính.
5. Email nhắc lịch khi không có truy cập vẫn cần scheduler. Không chạy migration/seed trên database đang dùng chỉ để cập nhật bản này.
6. Khi cần sửa Vue, chạy npm ci rồi npm run build. Chỉ chạy PHPUnit với database kiểm thử riêng.
