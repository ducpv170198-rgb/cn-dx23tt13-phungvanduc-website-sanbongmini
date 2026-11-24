# Log kiểm thử được công bố

## JavaScript

Tệp `frontend-node-24.22.3.txt` là log nguyên văn của lệnh `npm run test:frontend`, chạy ngày 24/11/2026 trên Node.js 24.22.3. Có 4 tệp kiểm thử với 33/33 ca đạt. Mã nguồn tại thời điểm chạy: `317921e961ad64f998b8eaedc29eb3fa2f8e8eac`.

Các ca này kiểm tra tiện ích và vòng đời giao diện; chúng không thay thế kiểm thử API, thanh toán, tồn kho hoặc kiểm thử giao diện E2E.

## PHPUnit và MySQL

Phụ lục D của báo cáo hiện ghi kết quả lần chạy trước: PHPUnit 21/21 và MySQL 14/14. Tệp JSON gốc của lần chạy MySQL vẫn nằm trong thư mục tạm `kiemthu_ketqua/` bị Git bỏ qua, nên chưa có trong repo. PHP, Composer và MySQL không có trong môi trường dùng để tạo log JavaScript này; các kết quả PHP/MySQL chưa được chạy lại tại đây.

Để công bố log MySQL mới, chạy `php kiemthu.php` trên CSDL QA riêng, kiểm tra tệp `kiemthu_ketqua/<mã lần chạy>/ketqua.json`, rồi chép đúng tệp đó vào thư mục này và commit. Không tạo log thay thế từ số liệu trong báo cáo.
