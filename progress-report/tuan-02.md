**Sinh viên:** Phùng Văn Đức  
**Mã lớp:** DX23TT13  
**Tên đồ án:** Website Đặt Sân Bóng Mini  
**Giảng viên hướng dẫn:** TS.Nguyễn Nhứt Lam

## TUẦN 02 – XÂY DỰNG CÁC CHỨC NĂNG CỐT LÕI

**Thời gian:** 23/11/2026 – 29/11/2026  

### 1. Công việc đã thực hiện

- Xây dựng backend bằng Laravel 12, gồm REST API, xác thực Sanctum và phân quyền admin/staff/customer.
- Thiết kế và tạo migration cơ sở dữ liệu MySQL; bổ sung dữ liệu mẫu cho sân, bảng giá và dịch vụ.
- Cài đặt chức năng đặt sân có kiểm tra trùng lịch bằng giao dịch và khóa dòng; tính giá theo khung giờ.
- Cài đặt luồng đặt cọc VietQR, ghi nhận thu/hoàn tiền theo cơ chế lũy đẳng, lập hóa đơn và cập nhật tồn kho dịch vụ.
- Xây dựng giao diện Vue 3 cho đặt sân, đơn của khách hàng, chức năng giao hữu và trang quản trị.
- Viết 21 ca PHPUnit, 33 ca JavaScript và script kiểm thử tích hợp MySQL `kiemthu.php`.

### 2. Kết quả kiểm thử

- PHPUnit: 21/21 ca đạt.
- JavaScript: 33/33 ca đạt.
- Kiểm thử tích hợp MySQL: 14/14 ca đạt.
- Môi trường và giới hạn của các lần kiểm thử được tổng hợp trong báo cáo đồ án, mục 4.5.3 và Phụ lục D.

### 3. Khó khăn, vướng mắc

- Cần tiếp tục đối chiếu các ca kiểm thử nghiệp vụ với kết quả mong đợi và lưu minh chứng theo từng ca.
- Cần rà soát tính nhất quán giữa mã nguồn, tài liệu hướng dẫn và báo cáo.

### 4. Kế hoạch tuần tiếp theo

- Viết lại README, bổ sung hướng dẫn cài đặt và chạy kiểm thử.
- Chạy thêm các kịch bản về tiền, hủy đơn và phân quyền (TC13–TC24), đồng thời lưu minh chứng.
- Sửa giao diện quản trị trên điện thoại, tập trung lỗi tràn ngang ở độ rộng 390 px.
- Hoàn thiện báo cáo theo nhận xét về bìa, giãn dòng 1,5 và tài liệu tham khảo IEEE.
