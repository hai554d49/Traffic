<?php
/**
 * Cấu hình chung của ứng dụng.
 * Sửa thông tin kết nối MySQL cho phù hợp với máy của bạn (XAMPP mặc định: root / không mật khẩu).
 */

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'traffic_violation');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'CSGT Online');

/*
 * Địa chỉ dùng trong mã QR thanh toán. Điện thoại không truy cập được "localhost",
 * nên khi demo quét QR bằng điện thoại hãy điền IP LAN của máy tính, ví dụ: 'http://192.168.1.10'.
 * Để trống = tự lấy theo địa chỉ đang truy cập.
 */
define('PUBLIC_HOST', '');

// Thư mục lưu file upload (ảnh vi phạm, minh chứng khiếu nại)
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Tự xác định đường dẫn gốc của ứng dụng (vd: /traffic) để link hoạt động trong mọi thư mục con
$appRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$baseUrl = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
define('BASE_URL', rtrim($baseUrl, '/'));
