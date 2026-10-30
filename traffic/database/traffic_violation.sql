-- =====================================================================
--  CƠ SỞ DỮ LIỆU: HỆ THỐNG QUẢN LÝ PHƯƠNG TIỆN VI PHẠM GIAO THÔNG
--  MySQL 5.7+ / MariaDB 10.2+
--  Cách dùng: chạy http://localhost/<thu-muc>/install.php (khuyến nghị)
--  hoặc import file này bằng phpMyAdmin rồi chạy install.php để tạo tài khoản.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS violations;
DROP TABLE IF EXISTS violation_types;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Người dùng: quản trị viên, cán bộ CSGT, người dân
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NULL,
    phone       VARCHAR(20)  NULL,
    cccd        VARCHAR(20)  NULL COMMENT 'Số CCCD - dùng để liên kết với phương tiện',
    role        ENUM('admin','officer','citizen') NOT NULL DEFAULT 'citizen',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_cccd (cccd)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cấu hình hệ thống (hạn nộp, lãi suất, tài khoản nhận tiền...)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    label         VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Phương tiện
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    plate_number   VARCHAR(20)  NOT NULL UNIQUE,
    vehicle_type   ENUM('xe_may','o_to','xe_tai','xe_khach','khac') NOT NULL DEFAULT 'xe_may',
    brand          VARCHAR(100) NULL,
    color          VARCHAR(50)  NULL,
    owner_name     VARCHAR(100) NOT NULL,
    owner_cccd     VARCHAR(20)  NULL,
    owner_phone    VARCHAR(20)  NULL,
    owner_address  VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicles_cccd (owner_cccd)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Danh mục lỗi vi phạm & mức phạt
-- ---------------------------------------------------------------------
CREATE TABLE violation_types (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    vehicle_type  ENUM('tat_ca','xe_may','o_to','xe_tai','xe_khach','khac') NOT NULL DEFAULT 'tat_ca',
    fine_amount   BIGINT UNSIGNED NOT NULL,
    legal_basis   VARCHAR(255) NULL,
    description   TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Vi phạm (biên bản)
-- ---------------------------------------------------------------------
CREATE TABLE violations (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(20) NOT NULL UNIQUE,
    vehicle_id         INT NOT NULL,
    violation_type_id  INT NOT NULL,
    violation_time     DATETIME NOT NULL,
    location           VARCHAR(255) NOT NULL,
    description        TEXT NULL,
    fine_amount        BIGINT UNSIGNED NOT NULL,
    due_date           DATE NOT NULL COMMENT 'Hạn nộp phạt, quá hạn sẽ tính tiền chậm nộp',
    status             ENUM('chua_nop','khieu_nai','da_nop','da_huy') NOT NULL DEFAULT 'chua_nop',
    evidence_image     VARCHAR(255) NULL,
    officer_id         INT NULL,
    paid_at            DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_violations_status (status),
    INDEX idx_violations_due (due_date),
    CONSTRAINT fk_violation_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_violation_type    FOREIGN KEY (violation_type_id) REFERENCES violation_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_violation_officer FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Khiếu nại
-- ---------------------------------------------------------------------
CREATE TABLE complaints (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(20) NOT NULL UNIQUE,
    violation_id       INT NOT NULL,
    user_id            INT NULL,
    complainant_name   VARCHAR(100) NOT NULL,
    complainant_phone  VARCHAR(20)  NULL,
    content            TEXT NOT NULL,
    evidence_file      VARCHAR(255) NULL,
    status             ENUM('cho_xu_ly','chap_nhan','tu_choi') NOT NULL DEFAULT 'cho_xu_ly',
    response           TEXT NULL,
    handled_by         INT NULL,
    handled_at         DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_violation FOREIGN KEY (violation_id) REFERENCES violations(id) ON DELETE CASCADE,
    CONSTRAINT fk_complaint_user      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaint_handler   FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Thanh toán
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code  VARCHAR(30) NOT NULL UNIQUE,
    violation_id      INT NOT NULL,
    user_id           INT NULL,
    fine_amount       BIGINT UNSIGNED NOT NULL,
    late_days         INT NOT NULL DEFAULT 0,
    late_fee          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_amount      BIGINT UNSIGNED NOT NULL,
    method            ENUM('qr_vi_dien_tu','chuyen_khoan','tien_mat') NOT NULL,
    status            ENUM('cho_thanh_toan','cho_xac_nhan','thanh_cong','that_bai','het_han') NOT NULL DEFAULT 'cho_thanh_toan',
    token             VARCHAR(64) NULL UNIQUE COMMENT 'Mã bí mật nhúng trong QR thanh toán',
    expires_at        DATETIME NULL,
    paid_at           DATETIME NULL,
    confirmed_by      INT NULL,
    note              VARCHAR(255) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_status (status),
    CONSTRAINT fk_payment_violation FOREIGN KEY (violation_id) REFERENCES violations(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_user      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_payment_confirmer FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Nhật ký hoạt động
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    action      VARCHAR(100) NOT NULL,
    detail      VARCHAR(500) NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DỮ LIỆU MẪU
-- =====================================================================

INSERT INTO settings (setting_key, setting_value, label) VALUES
('payment_deadline_days', '10',    'Số ngày được phép nộp phạt kể từ ngày vi phạm'),
('late_fee_rate',         '0.05',  'Lãi suất chậm nộp (% / ngày trên số tiền phạt)'),
('late_fee_cap_percent',  '100',   'Tiền chậm nộp tối đa (% so với tiền phạt)'),
('qr_expire_minutes',     '15',    'Thời gian hiệu lực của mã QR thanh toán (phút)'),
('bank_id',               'MB',    'Mã ngân hàng nhận tiền (VietQR: MB, VCB, BIDV, ICB...)'),
('bank_account_no',       '0123456789', 'Số tài khoản nhận tiền phạt'),
('bank_account_name',     'KHO BAC NHA NUOC DEMO', 'Tên chủ tài khoản'),
('hotline',               '1900 1234', 'Đường dây nóng');

-- Danh mục lỗi (mức phạt tham khảo, phục vụ mục đích học tập)
INSERT INTO violation_types (id, name, vehicle_type, fine_amount, legal_basis, description) VALUES
(1,  'Không chấp hành hiệu lệnh của đèn tín hiệu giao thông', 'o_to',   20000000, 'Nghị định 168/2024/NĐ-CP', 'Vượt đèn đỏ, đèn vàng'),
(2,  'Không chấp hành hiệu lệnh của đèn tín hiệu giao thông', 'xe_may', 5000000,  'Nghị định 168/2024/NĐ-CP', 'Vượt đèn đỏ, đèn vàng'),
(3,  'Điều khiển xe chạy quá tốc độ quy định từ 5 km/h đến dưới 10 km/h', 'o_to', 1000000, 'Nghị định 168/2024/NĐ-CP', NULL),
(4,  'Điều khiển xe chạy quá tốc độ quy định từ 10 km/h đến 20 km/h', 'o_to', 6000000, 'Nghị định 168/2024/NĐ-CP', NULL),
(5,  'Không đội mũ bảo hiểm khi điều khiển xe mô tô, xe gắn máy', 'xe_may', 500000, 'Nghị định 168/2024/NĐ-CP', NULL),
(6,  'Trong máu hoặc hơi thở có nồng độ cồn chưa vượt quá 0,25 mg/l khí thở', 'xe_may', 2500000, 'Nghị định 168/2024/NĐ-CP', NULL),
(7,  'Trong máu hoặc hơi thở có nồng độ cồn chưa vượt quá 0,25 mg/l khí thở', 'o_to', 8000000, 'Nghị định 168/2024/NĐ-CP', NULL),
(8,  'Dừng, đỗ xe không đúng nơi quy định', 'o_to', 600000, 'Nghị định 168/2024/NĐ-CP', NULL),
(9,  'Sử dụng điện thoại di động khi đang điều khiển xe', 'xe_may', 1000000, 'Nghị định 168/2024/NĐ-CP', NULL),
(10, 'Chở hàng vượt trọng tải cho phép trên 10% đến 30%', 'xe_tai', 3000000, 'Nghị định 168/2024/NĐ-CP', NULL),
(11, 'Đi vào đường cấm, khu vực cấm', 'tat_ca', 1000000, 'Nghị định 168/2024/NĐ-CP', NULL);

INSERT INTO vehicles (id, plate_number, vehicle_type, brand, color, owner_name, owner_cccd, owner_phone, owner_address) VALUES
(1, '30A-123.45', 'o_to',   'Toyota Vios',     'Trắng', 'Nguyễn Văn An',   '001090012345', '0912345678', 'Cầu Giấy, Hà Nội'),
(2, '29B1-567.89','xe_may', 'Honda Vision',    'Đỏ',    'Nguyễn Văn An',   '001090012345', '0912345678', 'Cầu Giấy, Hà Nội'),
(3, '51G-888.99', 'o_to',   'Mazda CX-5',      'Xanh',  'Trần Thị Bình',   '079195004321', '0987654321', 'Quận 1, TP. Hồ Chí Minh'),
(4, '59X2-246.81','xe_may', 'Yamaha Exciter',  'Đen',   'Lê Hoàng Cường',  '079200007777', '0909111222', 'Thủ Đức, TP. Hồ Chí Minh'),
(5, '43C-135.79', 'xe_tai', 'Hyundai Mighty',  'Xanh',  'Phạm Minh Đức',   '048088003333', '0935666777', 'Hải Châu, Đà Nẵng'),
(6, '15A-777.77', 'o_to',   'VinFast VF8',     'Đỏ',    'Hoàng Thu Hà',    '031098001111', '0977888999', 'Ngô Quyền, Hải Phòng'),
(7, '30E-246.80', 'o_to',   'Honda CR-V',      'Bạc',   'Vũ Quang Huy',    '001087009999', '0966555444', 'Đống Đa, Hà Nội'),
(8, '29H1-111.22','xe_may', 'Honda SH',        'Trắng', 'Đặng Thị Lan',    '001099002222', '0944333222', 'Hoàn Kiếm, Hà Nội');

-- Ngày được tính tương đối theo ngày hiện tại để dữ liệu mẫu luôn có đủ trạng thái
INSERT INTO violations (id, code, vehicle_id, violation_type_id, violation_time, location, description, fine_amount, due_date, status, paid_at) VALUES
(1,  'VP00000001', 1, 1, NOW() - INTERVAL 45 DAY, 'Ngã tư Cầu Giấy - Xuân Thủy, Hà Nội', 'Camera phạt nguội ghi nhận', 20000000, CURDATE() - INTERVAL 35 DAY, 'chua_nop', NULL),
(2,  'VP00000002', 2, 5, NOW() - INTERVAL 3 DAY,  'Đường Trần Duy Hưng, Hà Nội', NULL, 500000, CURDATE() + INTERVAL 7 DAY, 'chua_nop', NULL),
(3,  'VP00000003', 3, 4, NOW() - INTERVAL 20 DAY, 'Đại lộ Võ Văn Kiệt, TP.HCM', 'Tốc độ ghi nhận 75km/h tại khu vực giới hạn 60km/h', 6000000, CURDATE() - INTERVAL 10 DAY, 'khieu_nai', NULL),
(4,  'VP00000004', 4, 6, NOW() - INTERVAL 30 DAY, 'Đường Nguyễn Huệ, TP.HCM', NULL, 2500000, CURDATE() - INTERVAL 20 DAY, 'da_nop', NOW() - INTERVAL 25 DAY),
(5,  'VP00000005', 5, 10, NOW() - INTERVAL 60 DAY, 'Quốc lộ 1A, Đà Nẵng', 'Quá tải 18%', 3000000, CURDATE() - INTERVAL 50 DAY, 'da_nop', NOW() - INTERVAL 30 DAY),
(6,  'VP00000006', 6, 8, NOW() - INTERVAL 5 DAY,  'Đường Lạch Tray, Hải Phòng', NULL, 600000, CURDATE() + INTERVAL 5 DAY, 'chua_nop', NULL),
(7,  'VP00000007', 7, 3, NOW() - INTERVAL 15 DAY, 'Đường Láng, Hà Nội', NULL, 1000000, CURDATE() - INTERVAL 5 DAY, 'chua_nop', NULL),
(8,  'VP00000008', 8, 9, NOW() - INTERVAL 1 DAY,  'Phố Huế, Hà Nội', NULL, 1000000, CURDATE() + INTERVAL 9 DAY, 'chua_nop', NULL),
(9,  'VP00000009', 3, 8, NOW() - INTERVAL 40 DAY, 'Quận 3, TP.HCM', NULL, 600000, CURDATE() - INTERVAL 30 DAY, 'da_huy', NULL),
(10, 'VP00000010', 1, 11, NOW() - INTERVAL 8 DAY, 'Phố Tràng Tiền, Hà Nội', 'Đi vào phố đi bộ giờ cấm', 1000000, CURDATE() + INTERVAL 2 DAY, 'chua_nop', NULL),
(11, 'VP00000011', 4, 2, NOW() - INTERVAL 90 DAY, 'Ngã tư Hàng Xanh, TP.HCM', NULL, 5000000, CURDATE() - INTERVAL 80 DAY, 'da_nop', NOW() - INTERVAL 85 DAY),
(12, 'VP00000012', 6, 7, NOW() - INTERVAL 70 DAY, 'Đường Văn Cao, Hải Phòng', NULL, 8000000, CURDATE() - INTERVAL 60 DAY, 'da_nop', NOW() - INTERVAL 40 DAY);

INSERT INTO payments (transaction_code, violation_id, fine_amount, late_days, late_fee, total_amount, method, status, paid_at, created_at) VALUES
('GD00000001', 4,  2500000, 0,  0,      2500000, 'qr_vi_dien_tu', 'thanh_cong', NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY),
('GD00000002', 5,  3000000, 20, 30000,  3030000, 'chuyen_khoan',  'thanh_cong', NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY),
('GD00000003', 11, 5000000, 0,  0,      5000000, 'tien_mat',      'thanh_cong', NOW() - INTERVAL 85 DAY, NOW() - INTERVAL 85 DAY),
('GD00000004', 12, 8000000, 20, 80000,  8080000, 'qr_vi_dien_tu', 'thanh_cong', NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 40 DAY);

INSERT INTO complaints (code, violation_id, complainant_name, complainant_phone, content, status, response, handled_at, created_at) VALUES
('KN00000001', 3, 'Trần Thị Bình', '0987654321', 'Tại thời điểm vi phạm xe của tôi đang ở bãi gửi xe, có thể bị trùng biển số. Đề nghị kiểm tra lại hình ảnh camera.', 'cho_xu_ly', NULL, NULL, NOW() - INTERVAL 12 DAY),
('KN00000002', 9, 'Trần Thị Bình', '0987654321', 'Khu vực này có biển cho phép dừng đỗ từ 18h, tôi đỗ lúc 19h.', 'chap_nhan', 'Đã kiểm tra, biển báo cho phép đỗ sau 18h. Hủy biên bản.', NOW() - INTERVAL 33 DAY, NOW() - INTERVAL 38 DAY);
