# Kết quả kiểm thử

## JavaScript

Log nguyên văn của lệnh `npm run test:frontend` nằm tại [frontend-node-24.22.3.txt](frontend-node-24.22.3.txt). Lần chạy ngày 24/11/2026 sử dụng Linux x86_64 và Node.js 24.22.3; cả 4 tệp kiểm thử, tổng cộng 33/33 ca, đều đạt. `package.json` khai báo `engines.node >=22`.

Bản checkout dùng khi chạy ghi nhận mã cục bộ `317921e961ad64f998b8eaedc29eb3fa2f8e8eac`. Cây mã nguồn giống hệt được công bố trên nhánh `main` tại commit [05ffeb59fa0edbdad4342beec1d6fba4479e9c77](https://github.com/ducpv170198-rgb/cn-dx23tt13-phungvanduc-website-sanbongmini/commit/05ffeb59fa0edbdad4342beec1d6fba4479e9c77).

## PHPUnit và MySQL

Kết quả tổng hợp, môi trường chạy và lệnh tái lập được trình bày trong [backend-summary.md](backend-summary.md). PHPUnit ghi nhận 21/21 ca đạt với 93 assertions; kiểm thử tích hợp MySQL ghi nhận 14/14 ca đạt. Các lần chạy này sử dụng Windows/Laragon, PHP 8.4.27 và MySQL 8.0.30 trên máy Intel Core i7-8700, RAM 16 GB.

Bản ghi JSON gốc của lần chạy MySQL không có trong hồ sơ hiện lưu trữ. Tệp tổng hợp công bố các số liệu được ghi nhận, không phải bản sao của log JSON.

## Phạm vi và nghiệm thu kịch bản

Kết quả kiểm thử thành phần được tổng hợp riêng với nghiệm thu kịch bản nghiệp vụ. Tại thời điểm cập nhật, 0/28 kịch bản Phụ lục C có đầy đủ bằng chứng nghiệm thu: 14 ca chưa chạy, 6 ca mới có bằng chứng liên quan, 8 ca có kết quả kiểm thử tự động ở một phần chức năng nhưng chưa kiểm chứng đầy đủ theo kịch bản. Các luồng báo giá (TC03–TC04), tồn kho không đủ (TC10), ngưỡng cọc (TC11–TC12), xác nhận đơn (TC13–TC14), chuỗi đặt trùng (TC23) và chống leo thang quyền (TC26) chưa chạy.

Các ca JavaScript kiểm tra tiện ích và vòng đời giao diện; chúng không thay thế kiểm thử API hoặc E2E. Phép đo tải API được thực hiện qua Laravel HTTP Kernel, không qua máy chủ web hoặc mạng. Không coi các kết quả này là nghiệm thu đầu cuối.
