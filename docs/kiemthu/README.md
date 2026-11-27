# Kết quả kiểm thử

## Kịch bản nghiệp vụ

Danh sách TC01–TC28 và trạng thái từng ca được công bố trong [ma trận kịch bản](cac-kich-ban-nghiep-vu.md). Theo kết quả kiểm tra được cung cấp, 28/28 ca đạt: 10 ca có kết quả kiểm thử tự động và 18 ca được kiểm tra thủ công. Các ca thủ công chưa có ảnh chụp hoặc biên bản riêng; trạng thái này được ghi rõ trong ma trận và báo cáo.

## JavaScript

Log nguyên văn của lệnh `npm run test:frontend` nằm tại [frontend-node-24.22.3.txt](frontend-node-24.22.3.txt). Lần chạy ngày 24/11/2026 sử dụng Linux x86_64 và Node.js 24.22.3; cả 4 tệp kiểm thử, tổng cộng 33/33 ca, đều đạt. `package.json` khai báo `engines.node >=22`.

Mã nguồn đã kiểm thử có cây mã trùng với commit công khai [05ffeb59fa0edbdad4342beec1d6fba4479e9c77](https://github.com/ducpv170198-rgb/cn-dx23tt13-phungvanduc-website-sanbongmini/commit/05ffeb59fa0edbdad4342beec1d6fba4479e9c77).

## PHPUnit và MySQL

Kết quả ngày 27/11/2026 được thực hiện trên Windows/Laragon, PHP 8.4.27 và MySQL 8.0.30 với dữ liệu QA giả lập. PHPUnit: 21/21 ca đạt, 93 assertions. Script tích hợp MySQL: 14/14 ca đạt. Ảnh chụp terminal và JSON nguyên gốc được lưu tại:

- [Ảnh PHPUnit](evidence/phpunit-2026-11-27.png)
- [Ảnh kiểm thử MySQL](evidence/mysql-2026-11-27.png)
- [JSON kết quả MySQL](mysql-2026-11-27.json)

Các giao dịch trong bộ dữ liệu QA không phải thanh toán ngân hàng thật. TN13 thiết lập hóa đơn ở trạng thái `paid` trực tiếp làm dữ liệu kiểm thử; ca này không kiểm chứng luồng thanh toán thực tế.

## Phạm vi

Các ca JavaScript kiểm tra tiện ích và vòng đời giao diện; chúng không thay thế kiểm thử API hoặc E2E. Phép đo tải API được thực hiện qua Laravel HTTP Kernel, không qua máy chủ web hoặc mạng. Kết quả và giới hạn được trình bày trong báo cáo đồ án, mục 4.5 và Phụ lục C–D.
