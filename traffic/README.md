# CSGT Online – Hệ thống quản lý phương tiện vi phạm giao thông

Frontend **Bootstrap 4** (template Electricca) · Backend **PHP thuần (PDO)** · CSDL **MySQL**

---

## 1. Cài đặt

1. Copy thư mục `traffic` vào `C:\xampp\htdocs\`, bật **Apache** và **MySQL** trong XAMPP.
2. MySQL có mật khẩu thì sửa 5 dòng `define('DB_...')` ở đầu file `core.php`.
3. Mở `http://localhost/traffic/install.php` và bấm **Cài đặt** (tạo CSDL, dữ liệu mẫu và tài khoản).
4. Trang người dân: `http://localhost/traffic/`. Trang quản trị: `http://localhost/traffic/admin/`.

| Vai trò | Tài khoản | Mật khẩu |
|---|---|---|
| Quản trị viên | `admin` | `admin123` |
| Cán bộ CSGT | `canbo` | `canbo123` |
| Người dân (xe 30A-123.45, 29B1-567.89) | `nguoidan` | `123456` |
| Người dân (xe 51G-888.99) | `binhtran` | `123456` |

> Chạy lại `install.php` sẽ **xóa toàn bộ dữ liệu** và nạp lại dữ liệu mẫu. Sau khi cài xong nên đổi tên file này.

---

## 2. Cấu trúc: 16 file PHP, mỗi chức năng một file

```
traffic/
├── core.php          Hộp đồ nghề: cấu hình, kết nối CSDL, bảo mật, đăng nhập, tính lãi, thanh toán
├── layout.php        Khung giao diện: header/footer người dân và quản trị
├── database.sql      Cấu trúc 8 bảng + dữ liệu mẫu
├── install.php       Cài đặt CSDL
│
├── index.php         Trang chủ + tra cứu theo biển số / mã biên bản
├── violation.php     Chi tiết biên bản + in thông báo + gửi khiếu nại
├── pay.php           Toàn bộ luồng thanh toán: QR, API trạng thái, cổng ví, biên lai
├── account.php       Đăng nhập, đăng ký, đăng xuất, sửa tài khoản
├── my.php            Xe của tôi + khiếu nại của tôi
├── scan.php          Quét QR bằng camera
│
└── admin/
    ├── index.php       Tổng quan, biểu đồ
    ├── vehicles.php    Thêm / sửa / xóa phương tiện
    ├── violations.php  Thêm / sửa / xóa biên bản, thu tiền mặt, xuất CSV
    ├── complaints.php  Xử lý khiếu nại
    ├── payments.php    Xác nhận / từ chối chuyển khoản
    ├── users.php       Tạo tài khoản, đổi vai trò, khóa, đặt lại mật khẩu
    └── settings.php    Tham số (hạn nộp, lãi suất, ngân hàng) + danh mục lỗi + nhật ký
```

Ngoài ra: `css/` (Bootstrap, CSS template, `app.css` của dự án), `js/` (jQuery, Bootstrap), `images/`, `uploads/` (ảnh và file người dùng tải lên).

---

## 3. Quy ước chung: đọc được 1 file là đọc được tất cả

**Mọi file chức năng đều có cùng 4 bước:**

```php
require_once __DIR__ . '/../core.php';     // ① nạp hộp đồ nghề
require_once __DIR__ . '/../layout.php';
require_staff();                           // ② kiểm tra quyền

if (is_post()) {                           // ③ người dùng BẤM NÚT (gửi form)
    verify_csrf();
    if (input('action') === 'save')   $errors = save_vehicle();
    if (input('action') === 'delete') delete_vehicle();
}

show_list();  /  show_form($errors);       // ④ người dùng XEM TRANG -> in HTML
```

- **Mở trang (GET)** thì bước ③ bị bỏ qua, chỉ chạy bước ④.
- **Bấm nút (POST)** thì form gửi kèm ô ẩn `action` cho biết bấm nút nào, và bước ③ gọi đúng hàm xử lý.
- **Hàm xử lý** theo cùng một khuôn: đọc `input()` → kiểm tra → ghi CSDL bằng `query()` → `log_activity()` → `flash()` → `redirect()`. Nếu dữ liệu sai thì trả về mảng lỗi, và bước ④ hiện lại form kèm lỗi.
- **Tên hàm cho biết việc nó làm:** `show_...` in giao diện, `save_...` thêm hoặc sửa, `delete_...` xóa.

### Các hàm dùng chung trong core.php (nên thuộc)

| Hàm | Việc làm |
|---|---|
| `db()` | Mở kết nối PDO tới MySQL, một lần cho mỗi request |
| `query($sql, $params)` | Chạy SQL có dấu `?`; giá trị gửi riêng nên chống được SQL injection |
| `fetch_one` / `fetch_all` / `fetch_value` | Lấy 1 dòng / nhiều dòng / 1 giá trị (vd `COUNT(*)`) |
| `input('ten')` | Đọc ô nhập từ `$_POST`, nếu không có thì lấy từ `$_GET` |
| `is_post()` | Kiểm tra người dùng có bấm nút gửi form không |
| `verify_csrf()` | Kiểm tra mã bí mật trong form khớp với session (chống CSRF) |
| `e($text)` | Mã hóa ký tự HTML trước khi in ra (chống XSS) |
| `redirect('trang.php')` | Chuyển trình duyệt sang trang khác rồi dừng file |
| `flash('success', '...')` | Lưu thông báo để hiện ở trang kế tiếp |
| `current_user()` | Người đang đăng nhập (đọc `$_SESSION['user_id']` rồi tra DB) |
| `require_login` / `require_staff` / `require_admin` | Chặn người không đủ quyền |
| `calc_fine($v)` | Tính tiền phạt + tiền chậm nộp tới hôm nay |
| `create_payment()` | Tạo một dòng giao dịch trong bảng `payments` |
| `complete_payment($id)` | Ghi nhận thanh toán thành công (transaction) |
| `upload_file()` | Kiểm tra và lưu file tải lên |
| `log_activity()` | Ghi nhật ký vào bảng `activity_logs` |

---

## 4. Thứ tự xây dựng và lý do

| # | Làm gì | Vì sao làm ở bước này |
|---|---|---|
| 1 | **Phân tích yêu cầu** thành danh sách chức năng và vai trò (admin, cán bộ, người dân) | Biết cần lưu những gì trước khi thiết kế bảng |
| 2 | **Thiết kế CSDL** (`database.sql`): 8 bảng, khóa ngoại, trạng thái ENUM, dữ liệu mẫu | Mọi chức năng đều đọc/ghi các bảng này. Sai bảng thì phải sửa lại toàn bộ code |
| 3 | **core.php**: kết nối CSDL, hàm truy vấn, bảo mật, đăng nhập | Các trang sau đều dùng; viết một lần để khỏi lặp code |
| 4 | **layout.php**: chuyển HTML template thành header/footer dùng chung | Có khung giao diện thì trang nào cũng chỉ cần viết phần nội dung |
| 5 | **install.php** + tài khoản mẫu | Để chạy thử được ngay từ các bước tiếp theo |
| 6 | **account.php** (đăng ký, đăng nhập) | Phân quyền cần có người dùng trước; đây cũng là form đơn giản nhất để thử khuôn POST |
| 7 | **admin/vehicles.php** (CRUD phương tiện) | Biên bản phải gắn với một xe, nên cần có xe trước |
| 8 | **admin/settings.php** (danh mục lỗi, mức phạt, hạn nộp, lãi suất) | Biên bản phải chọn một loại lỗi |
| 9 | **admin/violations.php** (lập biên bản) + **calc_fine()** | Chức năng trung tâm, cần xe và loại lỗi có sẵn |
| 10 | **index.php, violation.php, my.php** (người dân tra cứu, xem) | Đã có dữ liệu biên bản để hiển thị |
| 11 | **Khiếu nại** (violation.php → admin/complaints.php) | Dựa trên biên bản và tài khoản người dân |
| 12 | **Thanh toán QR** (pay.php, admin/payments.php) | Phức tạp nhất: cần biên bản, tính lãi, transaction |
| 13 | **Tổng quan, xuất CSV, nhật ký, quét QR** | Tính năng bổ sung, làm khi phần chính đã chạy ổn |

**Nguyên tắc:** làm theo thứ tự phụ thuộc dữ liệu, cái gì được dùng bởi cái khác thì làm trước (xe → loại lỗi → biên bản → khiếu nại / thanh toán). Mỗi bước xong đều chạy thử được ngay.

---

## 5. Luồng hoạt động của từng thao tác

Ký hiệu: **→** là gọi hàm tiếp theo, `SQL` là câu lệnh chạy trên MySQL.

### 5.1. Admin THÊM phương tiện

1. Bấm **"Thêm phương tiện"** → trình duyệt mở `admin/vehicles.php?new=1` (GET)
   - `require_staff()`: chưa đăng nhập hoặc không phải cán bộ thì bị chặn
   - Không phải POST, có `new=1` → **`show_form([])`** in form trống. Form có 2 ô ẩn: `action=save`, `id=0`
2. Điền form, bấm **"Lưu"** → trình duyệt gửi **POST** tới `admin/vehicles.php`
   - `verify_csrf()`: kiểm tra mã chống giả mạo
   - `input('action') === 'save'` → **`save_vehicle()`**
     1. `input('id')` = 0 nghĩa là **thêm mới**
     2. `input(...)` đọc từng ô: biển số, loại xe, chủ xe...
     3. **`validate_vehicle()`** kiểm tra: biển số đúng mẫu (regex) và không trùng (`SELECT COUNT(*) FROM vehicles WHERE ...`), loại xe hợp lệ, CCCD 12 số, SĐT hợp lệ
        - Có lỗi → trả mảng lỗi → **`show_form($errors)`** hiện lại form, giữ nguyên chữ đã gõ, kèm ô lỗi đỏ. **Kết thúc.**
     4. Hợp lệ → `query("INSERT INTO vehicles (...) VALUES (?, ?, ...)")`
     5. `log_activity('Thêm phương tiện', ...)` → `INSERT INTO activity_logs`
     6. `flash('success', 'Đã thêm phương tiện ...')` → lưu thông báo vào session
     7. `redirect('admin/vehicles.php')` → trình duyệt chuyển trang
3. Trình duyệt mở lại `admin/vehicles.php` (GET) → **`show_list()`**: `SELECT ... FROM vehicles ... LIMIT 10` → in bảng, kèm thông báo xanh "Đã thêm".

### 5.2. Admin SỬA phương tiện

Giống hệt 5.1, chỉ khác:
- Bấm nút ✏ → `vehicles.php?id=5` → `show_form()` chạy `SELECT * FROM vehicles WHERE id = 5` rồi điền sẵn dữ liệu cũ. Ô ẩn `id=5`.
- Trong `save_vehicle()`, `id = 5` khác 0 nên chạy `UPDATE vehicles SET ... WHERE id = 5` thay cho INSERT.

### 5.3. Admin XÓA phương tiện

1. Bấm 🗑 → JavaScript hỏi `confirm()` (thuộc tính `data-confirm`) → bấm OK thì form gửi **POST** `action=delete, id=5`
2. `verify_csrf()` → **`delete_vehicle()`**
   - `is_admin()`? Cán bộ thường thì không được xóa
   - `SELECT COUNT(*) FROM violations WHERE vehicle_id = 5`: xe đã có biên bản thì không cho xóa (khóa ngoại RESTRICT)
   - Được xóa → `DELETE FROM vehicles WHERE id = 5` → `log_activity` → `flash` → `redirect`

### 5.4. Lập / sửa / xóa biên bản (`admin/violations.php`)

- **Lập:** `?new=1` → `show_form()`. JavaScript tự điền mức phạt khi chọn lỗi và tự tính hạn nộp = ngày vi phạm + 10 ngày.
  Bấm Lưu → POST `action=save` → **`save_violation()`**: kiểm tra (xe tồn tại, lỗi tồn tại, thời gian không ở tương lai, hạn nộp sau ngày vi phạm...) → `upload_file('evidence_image')` lưu ảnh vào `uploads/evidence/` → `generate_code('VP')` sinh mã biên bản → `INSERT INTO violations` (kèm `officer_id` = người đang đăng nhập) → `log_activity` → `flash` → `redirect`
- **Sửa:** `?id=5` → như trên nhưng chạy `UPDATE`. Có ảnh mới thì `delete_upload()` xóa ảnh cũ.
- **Xóa:** POST `action=delete` → **`delete_violation()`**: chỉ admin → `DELETE FROM violations`. MySQL tự xóa khiếu nại và giao dịch liên quan (khóa ngoại CASCADE) → `delete_upload()` xóa ảnh.
- **Thu tiền mặt:** POST `action=cash` → **`collect_cash()`** → `create_payment(..., 'tien_mat')` → **`complete_payment()`** (xem 5.9)
- **Lọc / tìm kiếm:** GET `?q=...&status=...` → `show_list()` → **`build_filter()`** ghép `WHERE 1=1 AND ...` → `paginate()` tính LIMIT/OFFSET
- **Xuất CSV:** GET `?export=1` → **`export_csv()`** dùng chung `build_filter()` rồi in file CSV

### 5.5. Tính tiền chậm nộp (mỗi lần hiển thị số tiền)

Không có nút bấm nào. Mỗi lần trang cần hiện số tiền, code gọi **`calc_fine($v)`**:
1. Hôm nay sau hạn nộp **và** biên bản đang `chua_nop` → số ngày trễ = `$due->diff($today)->days`
2. Tiền lãi = tiền phạt × `late_fee_rate` / 100 × số ngày trễ, tối đa bằng `late_fee_cap_percent` % tiền phạt
3. Trả về `fine`, `late_days`, `late_fee`, `total`

Tiền lãi **không được lưu** vào bảng `violations` vì nó tăng mỗi ngày. Chỉ khi thanh toán, số tiền mới được chép vào bảng `payments`.
Ví dụ: phạt 1.000.000 đ, trễ 20 ngày, lãi 0,05%/ngày thì tiền lãi = 1.000.000 × 0,05 / 100 × 20 = **10.000 đ**.

### 5.6. Đăng ký / đăng nhập / đăng xuất (`account.php`)

- **Đăng ký:** POST `?do=register` → **`do_register()`**: kiểm tra (username chưa có, CCCD 12 số chưa dùng, mật khẩu ≥ 6 ký tự và nhập lại khớp) → `password_hash()` mã hóa mật khẩu → `INSERT INTO users (role='citizen')` → lưu `$_SESSION['user_id']` (đăng nhập luôn) → `redirect('my.php')`
- **Đăng nhập:** POST `?do=login` → **`do_login()`**: `SELECT * FROM users WHERE username = ?` → `password_verify()` so mật khẩu → kiểm tra `is_active` → `session_regenerate_id()` → `$_SESSION['user_id'] = id` → `redirect`: cán bộ vào `admin/`, người dân về trang chủ
- **Từ đó về sau**, mỗi trang gọi `current_user()`: lấy `user_id` trong session → `SELECT * FROM users WHERE id = ? AND is_active = 1`. Đó là cách hệ thống "nhớ" bạn.
- **Đăng xuất:** `?do=logout` → **`do_logout()`**: xóa session → `redirect('index.php')`

### 5.7. Người dân tra cứu (`index.php`)

Gõ biển số, bấm Tra cứu → GET `index.php?q=30A-123.45` → **`lookup()`**
1. `find_violation_by_code($q)`: nếu là mã biên bản thì `redirect('violation.php?code=...')`
2. Không phải mã → `normalize_plate()` bỏ dấu, thành "30A12345" → `SELECT * FROM vehicles WHERE REPLACE(...) = ?`
3. Tìm thấy xe → `SELECT ... FROM violations WHERE vehicle_id = ?` → `violation_table()` in bảng, mỗi dòng gọi `calc_fine()`

### 5.8. Gửi và xử lý khiếu nại

**Người dân gửi** (`violation.php`):
1. Bấm "Gửi khiếu nại" → `violation.php?complain=1&code=VP..` → `require_login()` → **`show_complaint_form()`**
2. Bấm Gửi → POST `action=complain` → **`submit_complaint()`**
   - `complaint_blocked()`: biên bản đã nộp hoặc đã hủy, hoặc đã có khiếu nại đang chờ → chặn
   - Kiểm tra họ tên, SĐT, nội dung ≥ 20 ký tự, đã tích cam kết
   - `upload_file('evidence_file')` → lưu vào `uploads/complaints/`
   - `INSERT INTO complaints (status='cho_xu_ly')`
   - `UPDATE violations SET status='khieu_nai'` → từ lúc này **`calc_fine()` ngừng tính lãi**
   - `UPDATE payments SET status='het_han'` → hủy các mã QR đang mở
   - `log_activity` → `flash` → `redirect('my.php?tab=complaints')`

**Cán bộ xử lý** (`admin/complaints.php`):
1. Mở `complaints.php?id=3` → **`show_detail()`** hiện nội dung khiếu nại và biên bản
2. Chọn kết quả, nhập trả lời, bấm Lưu → POST `action=handle` → **`handle_complaint()`**
   - Kiểm tra đã chọn kết quả, có nội dung trả lời, số tiền giảm hợp lệ
   - `beginTransaction()`
   - `UPDATE complaints SET status, response, handled_by, handled_at`
   - Chấp nhận, hủy → `UPDATE violations SET status='da_huy'`
   - Chấp nhận, giảm phạt → `UPDATE violations SET fine_amount = mới, status='chua_nop', due_date = gia hạn`
   - Từ chối → `UPDATE violations SET status='chua_nop', due_date = gia hạn`
     (gia hạn = cộng thêm số ngày đã chờ, tối thiểu còn 3 ngày: `GREATEST(DATE_ADD(...), CURDATE() + INTERVAL 3 DAY)`)
   - `commit()` → `log_activity` → `flash` → `redirect`
3. Người dân thấy kết quả ở `my.php?tab=complaints` → **`show_my_complaints()`**

### 5.9. Thanh toán bằng QR ví điện tử (`pay.php`)

```
MÁY TÍNH                                  SERVER                              ĐIỆN THOẠI
1. Bấm "Nộp phạt" → pay.php?code=VP..
                                 show_pay_page()
                                   calc_fine() → tổng tiền
                                   get_or_create_qr_session()
                                     → create_payment(status='cho_thanh_toan',
                                        token ngẫu nhiên, hết hạn sau 15 phút)
   ← HTML + mã QR chứa link pay.php?token=...
2. JavaScript mỗi 3 giây gọi pay.php?status=TOKEN
                                 api_status() → {"status":"cho_thanh_toan"}
                                                                     3. Quét QR → pay.php?token=...
                                                                        show_gateway() hiện số tiền
                                                                     4. Bấm "Xác nhận" (POST action=pay)
                                 gateway_submit()
                                   kiểm tra còn hạn
                                   complete_payment():
                                     BEGIN TRANSACTION
                                     SELECT ... FOR UPDATE (khóa dòng)
                                     UPDATE payments   → 'thanh_cong'
                                     UPDATE violations → 'da_nop'
                                     COMMIT
5. Lần hỏi tiếp theo:
                                 api_status() → {"status":"thanh_cong","receipt_url":...}
   JavaScript chuyển sang pay.php?receipt=GD.. → show_receipt() in biên lai
```

- **API** của hệ thống chính là `api_status()`: trả JSON thay vì HTML, để JavaScript đọc.
- **Transaction:** 2 bảng cùng được cập nhật hoặc cùng không, nên không có chuyện đã thu tiền mà biên bản vẫn "chưa nộp".
- **FOR UPDATE:** 2 người bấm cùng lúc thì người sau phải chờ, đọc lại thấy `da_nop` và bị từ chối, nên không thu tiền 2 lần.

### 5.10. Thanh toán chuyển khoản VietQR

1. Tab "Chuyển khoản VietQR" hiện ảnh QR từ `vietqr_image_url()`, chứa sẵn số tài khoản, số tiền và nội dung `NOP PHAT VP..`. Người dân quét bằng app ngân hàng và chuyển tiền.
2. Bấm "Tôi đã chuyển khoản" → POST `action=transfer` → **`report_transfer()`** → `create_payment(status='cho_xac_nhan')`
3. Cán bộ vào `admin/payments.php`, đối chiếu sao kê ngân hàng:
   - Bấm ✔ → **`confirm_payment()`** → **`complete_payment()`** (giống 5.9)
   - Bấm ✖ → **`reject_payment()`** → `UPDATE payments SET status='that_bai'`

### 5.11. Quản lý người dùng & cấu hình (chỉ admin)

- **Đổi vai trò:** chọn trong ô select (`onchange` tự gửi form) → POST `action=role` → **`change_role()`** → `UPDATE users SET role = ?`. Có hiệu lực ngay ở request sau vì `current_user()` đọc lại DB mỗi lần.
- **Khóa / mở:** **`toggle_active()`** → `UPDATE users SET is_active = 1 - is_active`
- **Reset mật khẩu:** **`reset_password()`** → sinh 8 ký tự ngẫu nhiên → `password_hash()` → `UPDATE` → hiện mật khẩu mới cho admin
- **Không tự sửa chính mình**, để admin không tự khóa mất quyền.
- **Lưu tham số:** `settings.php` POST `action=save_settings` → **`save_settings()`** → `UPDATE settings` từng khóa. Lần sau `calc_fine()` đọc mức lãi mới.
- **Danh mục lỗi:** `settings.php?tab=types` → **`save_type()`** (INSERT/UPDATE), **`delete_type()`** (không xóa được lỗi đã dùng).

---

## 6. Cơ sở dữ liệu

| Bảng | Lưu gì | Liên kết |
|---|---|---|
| `users` | Tài khoản, vai trò, CCCD | |
| `vehicles` | Xe và chủ xe | Liên kết với người dùng qua `owner_cccd` = `users.cccd` |
| `violation_types` | Danh mục lỗi và mức phạt | |
| `violations` | Biên bản: số tiền, hạn nộp, trạng thái | → vehicles (RESTRICT), → violation_types (RESTRICT), → users cán bộ (SET NULL) |
| `complaints` | Khiếu nại | → violations (CASCADE) |
| `payments` | Giao dịch, số tiền đã chép lúc nộp | → violations (CASCADE) |
| `settings` | Tham số dạng khóa–giá trị | |
| `activity_logs` | Nhật ký thao tác | |

Trạng thái biên bản: `chua_nop` → (`khieu_nai` →) `da_nop` hoặc `da_huy`.

---

## 7. Bảo mật

| Tấn công | Chặn bằng |
|---|---|
| SQL injection | `query()` dùng prepared statement (dấu `?`) |
| XSS | Mọi dữ liệu in ra đều qua `e()` |
| CSRF | `csrf_field()` trong form + `verify_csrf()` khi nhận POST |
| Lộ mật khẩu | `password_hash` / `password_verify` |
| Chiếm phiên | `session_regenerate_id()` khi đăng nhập |
| Vượt quyền | `require_staff()` / `require_admin()` + kiểm tra lại `is_admin()` trước khi xóa |
| Upload mã độc | `upload_file()` kiểm tra đuôi và nội dung ảnh, đổi tên ngẫu nhiên; `uploads/.htaccess` cấm chạy PHP |
| Thanh toán trùng | Transaction + `FOR UPDATE` trong `complete_payment()` |

---

## 8. Demo quét QR bằng điện thoại

Điện thoại không mở được `localhost`. Hãy cho máy tính và điện thoại dùng cùng Wi-Fi, xem IP máy bằng lệnh `ipconfig` (vd `192.168.1.10`), rồi mở trang thanh toán bằng `http://192.168.1.10/traffic/...` hoặc điền `PUBLIC_HOST` trong `core.php`.
Không có điện thoại thì bấm **"Mở cổng thanh toán trên máy này"**.

> Mức phạt trong dữ liệu mẫu chỉ mang tính tham khảo cho mục đích học tập.
