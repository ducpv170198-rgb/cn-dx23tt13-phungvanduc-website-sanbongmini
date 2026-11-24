# Website đặt sân bóng đá mini

Đồ án thực tập chuyên ngành – Khoa Công nghệ thông tin, Trường Đại học Trà Vinh.

| | |
|---|---|
| Sinh viên | Phùng Văn Đức – MSSV 170123795 – Lớp DX23TT13 (khóa 2023–2027) |
| Giảng viên hướng dẫn | TS. Nguyễn Nhứt Lam |
| Thời gian thực hiện | 15/11/2026 – 03/01/2027 |

## Giới thiệu

Hệ thống giúp một cụm sân bóng mini (sân 5 và sân 7) quản lý đặt sân trực tuyến: xem lịch trống theo lưới 30 phút, báo giá theo khung giờ, đặt cọc qua VietQR, thu/hoàn tiền, quản lý dịch vụ – tồn kho, thống kê doanh thu và tìm đối giao hữu.

Điểm nhấn kỹ thuật:
- Chống đặt trùng bằng giao dịch cơ sở dữ liệu và khóa dòng sân (`lockForUpdate`).
- Tính giá phía máy chủ (theo khung giờ, ngày thường/cuối tuần); tiền lưu bằng số nguyên VND.
- Ghi nhận thanh toán lũy đẳng (`idempotency_key`); hoàn lại tồn kho đúng một lần khi hủy đơn.
- Đặt sân cố định hằng tuần (tối đa 52 tuần) theo nguyên tắc "tất cả hoặc không gì cả".
- Email thông báo qua hàng đợi; phân quyền `customer` / `staff` / `admin`.

## Công nghệ

- Backend: PHP, Laravel 12, REST API, Laravel Sanctum (bearer token), MySQL
- Frontend: Vue 3, Vue Router 4, Axios, Vite (SPA tại đường dẫn `/app`)
- Kiểm thử: PHPUnit 11, `node:test`, script tích hợp MySQL `kiemthu.php`
- Môi trường phát triển: Laragon (Windows)

## Cấu trúc thư mục chính

```
app/Http/Controllers/Api   Các controller REST API
app/Services               Tính giá, gửi email đặt sân
app/Console/Commands       Lệnh artisan (hoàn tất đơn, nhắc lịch, tạo admin)
database/migrations        Lược đồ CSDL
database/seeders           Dữ liệu mẫu (sân, bảng giá, dịch vụ)
resources/js/vue           Giao diện Vue
routes/api.php             Định nghĩa endpoint
tests/Feature              PHPUnit
tests/frontend             Kiểm thử JavaScript
docs/kiemthu               Log kiểm thử được công bố
kiemthu.php                Kiểm thử tích hợp MySQL (đồng thời, hiệu năng)
progress-report/           Báo cáo tiến độ hằng tuần
```

## Yêu cầu

PHP 8.4+ (kèm `pdo_mysql`), Composer, Node.js 22+ và MySQL 8. Phiên bản Node tối thiểu được khai báo trong `package.json`.

## Cài đặt

```bash
git clone https://github.com/ducpv170198-rgb/cn-dx23tt13-phungvanduc-website-sanbongmini.git
cd cn-dx23tt13-phungvanduc-website-sanbongmini

composer install
npm install
cp .env.example .env
php artisan key:generate
```

Tạo CSDL `sanbong_mini` trong MySQL, chỉnh `DB_*` trong `.env`, rồi:

```bash
php artisan migrate --seed
npm run build        # hoặc: npm run dev
php artisan serve
```

Mở `http://127.0.0.1:8000/app`. Với Laragon, có thể dùng mẫu `LARAGON_VHOST.conf.example`.

### Cấu hình VietQR

Điền trong `.env` (nếu thiếu, API mã QR trả 503):

```
FIELDLY_BANK_BIN=
FIELDLY_BANK_ACCOUNT=
FIELDLY_BANK_ACCOUNT_NAME=
FIELDLY_DEPOSIT_THRESHOLD_VND=500000
FIELDLY_DEPOSIT_AMOUNT_VND=100000
```

### Tài khoản quản trị

```bash
php artisan sanbong:create-admin email@example.com
```

Lệnh tạo tài khoản quản lý; mật khẩu không được lưu trong mã nguồn.

### Hàng đợi và lịch chạy

Mặc định `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`. Khi triển khai thật, dùng `database` và chạy:

```bash
php artisan queue:work
php artisan schedule:work     # tự động chuyển đơn đã thanh toán đủ sang "hoàn tất"
```

## Chạy kiểm thử

```bash
php artisan test              # PHPUnit: 21 ca (6 lớp Feature test)
npm run test:frontend         # JavaScript: 4 file, 33 ca
php kiemthu.php               # tích hợp MySQL: đặt trùng đồng thời, lũy đẳng, hoàn kho, phân quyền, hiệu năng
```

Lưu ý về `kiemthu.php`:
- Chạy tại thư mục gốc dự án, cần MySQL cục bộ và `DB_CONNECTION=mysql` trong `.env`.
- Script tạo cơ sở dữ liệu riêng `fieldly_qa_<mã lần chạy>`, không đụng đến CSDL đang dùng.
- Kết quả ghi vào `kiemthu_ketqua/<mã lần chạy>/ketqua.json` (thư mục này không được commit).
- Log kiểm thử đã công bố được lưu tại `docs/kiemthu/`; log JavaScript và phạm vi các bằng chứng còn thiếu được ghi trong README của thư mục này.
- `kiemthu_ketqua/` vẫn là thư mục tạm bị Git bỏ qua. Khi cần nộp log PHP/MySQL, kiểm tra và sao chép đúng tệp của lần chạy cần công bố vào `docs/kiemthu/`, rồi commit tệp đó.

## Quy ước làm việc

- Commit nhỏ, theo Conventional Commits: `feat:`, `fix:`, `test:`, `docs:`, `refactor:`, `chore:`.
- Báo cáo tiến độ hằng tuần: `progress-report/tuan-XX.md`.

## Giới hạn hiện tại

Chưa có cổng thanh toán tự động/đối soát ngân hàng. Hiệu năng mới đo ở mức HTTP Kernel, không qua mạng.
