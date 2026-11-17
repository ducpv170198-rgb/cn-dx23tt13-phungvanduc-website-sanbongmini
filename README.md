# Website Đặt Sân Bóng Mini (Laravel 12)

Hệ thống quản lý và đặt lịch thuê sân bóng đá mini trực tuyến, hỗ trợ khách hàng tra cứu khung giờ trống theo thời gian thực, đặt sân nhanh chóng và giúp chủ sân quản lý lịch trình, dịch vụ đi kèm cũng như thống kê doanh thu hiệu quả.

---

## 1. Thông tin sinh viên thực hiện
* **Họ và tên:** Phùng Văn Đức
* **Mã lớp:** DX23TT13
* **Giảng viên hướng dẫn:** Nguyễn Nhứt Lam
* **Loại đồ án:** Thực tập Đồ án chuyên ngành
* **Repository:** [cn-dx23tt13-phungvanduc-website-sanbongmini](https://github.com/ducpv170198-rgb/cn-dx23tt13-phungvanduc-website-sanbongmini)

---

## 2. Công nghệ & Môi trường phát triển
* **Backend:** PHP 8.2+, Laravel 12 Framework
* **Frontend:** Blade Template, Bootstrap 5 / Tailwind CSS, JavaScript
* **Cơ sở dữ liệu:** MySQL
* **Môi trường phát triển (Local Server):** Laragon (Apache/Nginx, PHP, MySQL, HeidiSQL)
* **Quản lý mã nguồn & Thư viện:** Git, GitHub, Composer, NPM

---

## 3. Các tính năng chính & Tiến độ thực hiện

### 👤 Phân hệ Khách hàng (User)
- [ ] Đăng ký, đăng nhập, quên mật khẩu và cập nhật thông tin cá nhân.
- [ ] Tìm kiếm sân bóng theo khu vực, loại sân (sân 5, sân 7) và khung giờ mong muốn.
- [ ] Xem bảng trạng thái sân trống theo thời gian thực và gửi yêu cầu đặt sân.
- [ ] Chọn dịch vụ đi kèm khi đặt sân (thuê bóng, áo tập, nước uống).
- [ ] Xem lịch sử đặt sân, trạng thái duyệt đơn và hủy lịch (theo quy định thời gian).

### 🛠️ Phân hệ Quản trị viên / Chủ sân (Admin)
- [ ] Quản lý thông tin cụm sân bóng (thêm, sửa, xóa, cập nhật hình ảnh và trạng thái bảo trì).
- [ ] Thiết lập bảng giá linh hoạt theo khung giờ (giờ hành chính / giờ cao điểm / cuối tuần).
- [ ] Quản lý đơn đặt sân: Xác nhận, từ chối hoặc cập nhật trạng thái thanh toán của khách hàng.
- [ ] Quản lý danh mục dịch vụ đi kèm và kho hàng cơ bản.
- [ ] Thống kê số lượt đặt sân và doanh thu theo ngày, tháng, năm.

---

## 4. Cấu trúc thư mục dự án
```text
cn-dx23tt13-phungvanduc-website-sanbongmini/
├── app/                  # Chứa Models, Controllers, Middlewares xử lý logic
├── database/             # Chứa Migrations, Seeders và Factories khởi tạo CSDL
├── progress-report/      # Thư mục lưu trữ báo cáo tiến độ đồ án hàng tuần
├── public/               # Tài nguyên công khai (CSS, JS, hình ảnh sân bóng)
├── resources/views/      # Giao diện người dùng (Blade Templates)
├── routes/web.php        # Định tuyến các đường dẫn URL của website
└── .env.example          # Tệp cấu hình biến môi trường mẫu
```

---

## 5. Hướng dẫn cài đặt và chạy dự án (Local)

### Cách 1: Chạy trực tiếp trên môi trường Laragon (Khuyên dùng trên Windows)
1. Đặt thư mục dự án vào đường dẫn gốc của Laragon: `C:\laragon\www\cn-dx23tt13-phungvanduc-website-sanbongmini`.
2. Mở Quick Terminal tại thư mục dự án và chạy lệnh cài đặt thư viện:
   ```bash
   composer install
   npm install && npm run build
   ```
3. Tạo tệp cấu hình môi trường và sinh khóa ứng dụng:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Khởi động **Laragon** (nhấn *Start All*), mở **HeidiSQL** để tạo cơ sở dữ liệu mới (ví dụ: `db_sanbongmini`), sau đó cấu hình kết nối trong tệp `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=db_sanbongmini
   DB_USERNAME=root
   DB_PASSWORD=
   ```
5. Khởi chạy các bảng cơ sở dữ liệu và dữ liệu mẫu:
   ```bash
   php artisan migrate --seed
   ```
6. Truy cập website qua tên miền ảo của Laragon:
   `http://cn-dx23tt13-phungvanduc-website-sanbongmini.test`

### Cách 2: Chạy bằng Artisan Development Server tiêu chuẩn
Nếu không sử dụng Virtual Host của Laragon, bạn có thể khởi chạy bằng lệnh:
```bash
php artisan serve
```
Sau đó mở trình duyệt và truy cập địa chỉ: `http://127.0.0.1:8000`

---

## 6. Quy định quản lý & Báo cáo tiến độ
* Mã nguồn được cập nhật định kỳ hàng tuần thông qua các commit tuân thủ chuẩn **Conventional Commits** (`feat:`, `fix:`, `docs:`, `chore:`).
* Báo cáo tiến độ chi tiết từng tuần được lưu trữ tại thư mục `/progress-report` dưới định dạng `tuan-XX.md` để giảng viên tiện theo dõi và chấm điểm.
