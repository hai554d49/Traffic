# CSGT Online – Hệ thống quản lý phương tiện vi phạm giao thông

Đồ án Lập trình Web: **Frontend Bootstrap 4** (dựa trên template *Electricca*) + **Backend PHP thuần (PDO)** + **MySQL**.

## 1. Cài đặt (XAMPP)

1. Copy thư mục `traffic` (thư mục chứa file README này) vào `C:\xampp\htdocs\`, được `C:\xampp\htdocs\traffic`.
   Thư mục này đã có sẵn CSS/JS/ảnh cần thiết từ template nên chạy độc lập, không cần thư mục template bên ngoài.
2. Mở XAMPP Control Panel, bật **Apache** và **MySQL**.
3. Nếu MySQL của bạn có mật khẩu, sửa trong `config/config.php`.
4. Truy cập **http://localhost/traffic/install.php** và bấm *Cài đặt cơ sở dữ liệu*.
   (Hoặc import `database/traffic_violation.sql` bằng phpMyAdmin, **sau đó vẫn phải chạy install.php** để tạo tài khoản, vì mật khẩu được mã hóa bằng `password_hash`.)
5. Vào **http://localhost/traffic/** (trang người dân) hoặc **/traffic/admin/** (trang quản trị).

Yêu cầu: PHP ≥ 7.4, MySQL 5.7+ / MariaDB 10.2+.

| Vai trò | Tài khoản | Mật khẩu |
|---|---|---|
| Quản trị viên | `admin` | `admin123` |
| Cán bộ CSGT | `canbo` | `canbo123` |
| Người dân (chủ xe 30A-123.45, 29B1-567.89) | `nguoidan` | `123456` |
| Người dân (chủ xe 51G-888.99) | `binhtran` | `123456` |

> Sau khi cài xong nên xóa/đổi tên `install.php`.

## 2. Chức năng

### Bắt buộc theo đề bài
- **Thêm / sửa / xóa**: vi phạm (biên bản), phương tiện, danh mục lỗi & mức phạt, người dùng.
- **Khiếu nại**: người dân gửi khiếu nại kèm file minh chứng → cán bộ chấp nhận (hủy biên bản hoặc giảm mức phạt) / từ chối, có phản hồi.
- **Quét QR thanh toán**:
  - *QR ví điện tử*: QR chứa link cổng thanh toán (mô phỏng) kèm token bí mật, hết hạn sau 15 phút. Quét bằng điện thoại → xác nhận → trang trên máy tính **tự cập nhật** (polling AJAX) và chuyển sang biên lai.
  - *QR chuyển khoản VietQR*: QR chuẩn ngân hàng Việt Nam (quét được bằng app ngân hàng thật), điền sẵn số tiền + nội dung; cán bộ đối soát và xác nhận.
  - Trang **Quét QR** dùng camera/ảnh để đọc mã QR in trên thông báo vi phạm.
- **Hạn nộp & lãi chậm nộp**: hạn nộp mặc định 10 ngày kể từ ngày vi phạm; quá hạn tính
  `tiền chậm nộp = tiền phạt × 0,05% × số ngày quá hạn` (tối đa 100% tiền phạt). Các tham số chỉnh trong *Quản trị → Cấu hình*.

### Bổ sung
- Phân quyền 3 vai trò: quản trị viên / cán bộ / người dân.
- Đăng ký bằng CCCD → tự liên kết các xe đứng tên (*Xe của tôi*).
- Tra cứu công khai theo biển số / mã biên bản (che bớt tên chủ xe với người lạ).
- Đang khiếu nại thì **tạm dừng tính lãi**; nếu bị từ chối, hạn nộp được gia hạn đúng số ngày chờ xử lý.
- In **thông báo vi phạm** (kèm QR nộp phạt) và **biên lai điện tử** (kèm QR kiểm tra).
- Thu tiền mặt tại trụ sở, xác nhận/từ chối chuyển khoản.
- Dashboard thống kê + biểu đồ (Chart.js), lọc/tìm kiếm/phân trang, **xuất CSV**.
- Upload ảnh vi phạm, nhật ký hoạt động.
- Bảo mật: PDO prepared statements (chống SQL injection), `htmlspecialchars` (chống XSS), CSRF token,
  `password_hash`, chặn thực thi PHP trong `uploads/`.

## 3. Demo quét QR bằng điện thoại

Điện thoại không truy cập được `localhost`. Để demo:
1. Máy tính và điện thoại cùng mạng Wi-Fi; xem IP máy tính bằng lệnh `ipconfig` (vd `192.168.1.10`).
2. Trên máy tính mở `http://192.168.1.10/traffic/pay.php?code=VP00000002` (hoặc đặt `PUBLIC_HOST` trong `config/config.php`).
3. Quét QR bằng camera điện thoại → bấm *Xác nhận thanh toán* → màn hình máy tính tự chuyển sang biên lai.

Không có điện thoại: bấm nút *Mở cổng thanh toán trên máy này*.

## 4. Cấu trúc thư mục

```
├── admin/                  Trang quản trị
│   ├── includes/           Layout admin (header/footer)
│   ├── index.php           Dashboard
│   ├── violations.php      Danh sách vi phạm (xóa, thu tiền mặt)
│   ├── violation_form.php  Thêm / sửa vi phạm
│   ├── vehicles.php, vehicle_form.php
│   ├── violation_types.php Danh mục lỗi
│   ├── complaints.php, complaint_view.php
│   ├── payments.php        Đối soát thanh toán
│   ├── users.php, user_form.php, settings.php, logs.php, export.php
├── config/config.php       Cấu hình kết nối CSDL
├── database/traffic_violation.sql
├── includes/               init, hàm dùng chung, xác thực, layout người dân
├── css/ js/ images/        Tài nguyên template + app.css, admin.css, app.js
├── uploads/                File tải lên
├── index.php               Trang chủ
├── lookup.php              Tra cứu
├── violation.php           Chi tiết biên bản
├── pay.php                 Nộp phạt bằng QR
├── pay_gateway.php         Cổng thanh toán mô phỏng (mở khi quét QR)
├── payment_status.php      API kiểm tra trạng thái giao dịch (JSON)
├── receipt.php, notice.php Biên lai, thông báo vi phạm (in được)
├── scan.php                Quét QR bằng camera
├── complaint.php, my_complaints.php, my_vehicles.php
├── login.php, register.php, logout.php, profile.php
└── install.php             Cài đặt CSDL
```

## 5. Cơ sở dữ liệu

`users`, `vehicles`, `violation_types`, `violations`, `complaints`, `payments`, `settings`, `activity_logs`
(khóa ngoại InnoDB, mã hóa `utf8mb4`). Dữ liệu mẫu dùng ngày tương đối so với ngày cài đặt nên luôn có đủ
trường hợp: còn hạn, quá hạn, đang khiếu nại, đã nộp, đã hủy.

> Mức phạt trong dữ liệu mẫu chỉ mang tính tham khảo cho mục đích học tập.
