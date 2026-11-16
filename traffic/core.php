<?php
/**
 * CORE.PHP - "Hộp đồ nghề" dùng chung, mọi trang đều require file này ở dòng đầu tiên.
 *
 *  1. Cấu hình (kết nối MySQL, đường dẫn)
 *  2. Làm việc với CSDL: db(), query(), fetch_one(), fetch_all(), fetch_value()
 *  3. Nhận dữ liệu & điều hướng: input(), is_post(), redirect(), flash()
 *  4. Bảo mật: e() chống XSS, csrf_field()/verify_csrf() chống CSRF
 *  5. Đăng nhập & phân quyền: current_user(), require_login(), require_staff(), require_admin()
 *  6. Nghiệp vụ: calc_fine() tính lãi, complete_payment() ghi nhận thanh toán
 *  7. Tiện ích: upload_file(), generate_code(), paginate(), log_activity()...
 */

/* ============================== 1. CẤU HÌNH ============================== */

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'traffic_violation');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'CSGT Online');
define('APP_DIR', __DIR__);
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB

// Điện thoại không mở được "localhost": khi demo quét QR, điền IP LAN của máy, vd 'http://192.168.1.10'
define('PUBLIC_HOST', '');

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Đường dẫn gốc của web, vd "/traffic", để đổi tên thư mục vẫn chạy được
$appRoot = str_replace('\\', '/', realpath(__DIR__));
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
define('BASE_URL', rtrim(($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '', '/'));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================== 2. CƠ SỞ DỮ LIỆU ============================== */

/** Mở kết nối PDO một lần duy nhất cho mỗi request (biến static giữ lại kết nối) */
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $ex) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:30px"><h3>Không kết nối được cơ sở dữ liệu</h3><p>'
                . htmlspecialchars($ex->getMessage()) . '</p><p>Kiểm tra cấu hình ở đầu file <code>core.php</code> hoặc chạy <a href="'
                . BASE_URL . '/install.php">install.php</a>.</p></div>');
        }
    }
    return $pdo;
}

/** Chạy câu SQL có dấu ? (prepared statement): câu lệnh và dữ liệu gửi riêng nên chống được SQL injection */
function query($sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Lấy 1 dòng (hoặc null) */
function fetch_one($sql, array $params = [])
{
    $row = query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Lấy nhiều dòng */
function fetch_all($sql, array $params = [])
{
    return query($sql, $params)->fetchAll();
}

/** Lấy 1 giá trị đơn, vd COUNT(*) */
function fetch_value($sql, array $params = [])
{
    return query($sql, $params)->fetchColumn();
}

/* ============================== 3. NHẬN DỮ LIỆU & ĐIỀU HƯỚNG ============================== */

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Đọc ô nhập: ưu tiên $_POST (form), sau đó $_GET (URL) */
function input($key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL đầy đủ http://host/... (dùng trong mã QR để điện thoại mở được) */
function absolute_url($path = '')
{
    if (PUBLIC_HOST !== '') {
        return rtrim(PUBLIC_HOST, '/') . url($path);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function is_localhost_url()
{
    return PUBLIC_HOST === '' && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $_SERVER['HTTP_HOST'] ?? '');
}

/** Bảo trình duyệt chuyển sang trang khác rồi dừng chạy file */
function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/** Lưu thông báo vào session để hiện ở trang kế tiếp (sau redirect) */
function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash()
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show">' . $f['message']
            . '<button type="button" class="close" data-dismiss="alert">&times;</button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/** Hiện danh sách lỗi kiểm tra dữ liệu */
function render_errors(array $errors)
{
    if (!$errors) {
        return '';
    }
    $html = '<div class="alert alert-danger"><ul class="mb-0 pl-3">';
    foreach ($errors as $er) {
        $html .= '<li>' . e($er) . '</li>';
    }
    return $html . '</ul></div>';
}

/* ============================== 4. BẢO MẬT ============================== */

/** In dữ liệu ra HTML an toàn: "<script>" thành "&lt;script&gt;" (chống XSS) */
function e($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/** Giá trị cũ của ô nhập (điền lại form khi có lỗi) */
function old($key, $default = '')
{
    return e($_POST[$key] ?? $default);
}

/** Mã chống CSRF: sinh ngẫu nhiên 1 lần, lưu trong session */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Gọi đầu mọi xử lý POST: mã trong form phải khớp mã trong session */
function verify_csrf()
{
    if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('Phiên làm việc đã hết hạn, vui lòng tải lại trang và thử lại.');
    }
}

/* ============================== 5. ĐĂNG NHẬP & PHÂN QUYỀN ============================== */
// Vai trò: admin (quản trị), officer (cán bộ CSGT), citizen (người dân)

/** Người đang đăng nhập: lấy user_id trong session rồi đọc lại từ DB (bị khóa thì coi như chưa đăng nhập) */
function current_user()
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = fetch_one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
            if (!$user) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function is_logged_in()
{
    return current_user() !== null;
}

function is_staff()
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'officer'], true);
}

function is_admin()
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login()
{
    if (!is_logged_in()) {
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('account.php?do=login&redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

function require_staff()
{
    require_login();
    if (!is_staff()) {
        http_response_code(403);
        die('Bạn không có quyền truy cập trang này.');
    }
}

function require_admin()
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Chức năng này chỉ dành cho quản trị viên.');
        redirect('admin/index.php');
    }
}

function role_labels()
{
    return ['admin' => 'Quản trị viên', 'officer' => 'Cán bộ CSGT', 'citizen' => 'Người dân'];
}

/* ============================== 6. NGHIỆP VỤ ============================== */

/** Đọc bảng settings 1 lần rồi nhớ lại (cache) */
function setting($key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (fetch_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * TÍNH TIỀN PHẢI NỘP của một biên bản tại ngày hôm nay.
 *   Tiền chậm nộp = tiền phạt × lãi suất(%/ngày) / 100 × số ngày quá hạn, tối đa late_fee_cap_percent % tiền phạt.
 *   Chỉ tính khi biên bản đang "chưa nộp" -> đang khiếu nại thì lãi tạm dừng.
 *   Lãi KHÔNG lưu vào DB vì thay đổi mỗi ngày; khi thanh toán mới chép vào bảng payments.
 */
function calc_fine(array $violation)
{
    $fine = (int) $violation['fine_amount'];
    $today = new DateTime('today');
    $due = new DateTime($violation['due_date']);

    $lateDays = 0;
    if ($today > $due && $violation['status'] === 'chua_nop') {
        $lateDays = (int) $due->diff($today)->days;
    }

    $rate = (float) setting('late_fee_rate', 0.05);
    $cap = (float) setting('late_fee_cap_percent', 100);
    $lateFee = min((int) round($fine * $rate / 100 * $lateDays), (int) round($fine * $cap / 100));

    return [
        'fine'      => $fine,
        'late_days' => $lateDays,
        'late_fee'  => $lateFee,
        'total'     => $fine + $lateFee,
        'overdue'   => $lateDays > 0,
        'days_left' => (int) $today->diff($due)->format('%r%a'),
        'rate'      => $rate,
    ];
}

/** Lấy biên bản theo mã, kèm thông tin xe (JOIN vehicles) và tên lỗi (JOIN violation_types) */
function find_violation_by_code($code)
{
    return fetch_one(
        'SELECT v.*, vh.plate_number, vh.vehicle_type, vh.brand, vh.color, vh.owner_name, vh.owner_cccd,
                vh.owner_phone, vh.owner_address, vt.name AS type_name, vt.legal_basis, u.full_name AS officer_name
         FROM violations v
         JOIN vehicles vh ON vh.id = v.vehicle_id
         JOIN violation_types vt ON vt.id = v.violation_type_id
         LEFT JOIN users u ON u.id = v.officer_id
         WHERE v.code = ?',
        [strtoupper(trim($code))]
    );
}

/** Tạo một giao dịch mới với số tiền tính tại thời điểm hiện tại, trả về id */
function create_payment(array $violation, $method, $status, $extra = [])
{
    $f = calc_fine($violation);
    $user = current_user();
    query('INSERT INTO payments (transaction_code, violation_id, user_id, fine_amount, late_days, late_fee, total_amount,
           method, status, token, expires_at, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        generate_code('GD', 'payments', 'transaction_code'), $violation['id'], $user['id'] ?? null,
        $f['fine'], $f['late_days'], $f['late_fee'], $f['total'], $method, $status,
        $extra['token'] ?? null, $extra['expires_at'] ?? null, $extra['note'] ?? null,
    ]);
    return (int) db()->lastInsertId();
}

/**
 * GHI NHẬN THANH TOÁN THÀNH CÔNG - dùng chung cho QR ví, chuyển khoản, tiền mặt.
 * Transaction: 2 bảng cùng được cập nhật hoặc cùng không.
 * FOR UPDATE: khóa dòng, 2 người bấm cùng lúc thì người sau phải chờ và bị từ chối -> không thu tiền 2 lần.
 */
function complete_payment($paymentId, $confirmedBy = null)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $payment = fetch_one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        $violation = $payment ? fetch_one('SELECT * FROM violations WHERE id = ? FOR UPDATE', [$payment['violation_id']]) : null;
        if (!$payment || !$violation || $violation['status'] !== 'chua_nop'
            || !in_array($payment['status'], ['cho_thanh_toan', 'cho_xac_nhan'], true)) {
            $pdo->rollBack();
            return false;
        }
        query("UPDATE payments SET status = 'thanh_cong', paid_at = NOW(), confirmed_by = ? WHERE id = ?", [$confirmedBy, $paymentId]);
        query("UPDATE violations SET status = 'da_nop', paid_at = NOW() WHERE id = ?", [$violation['id']]);
        query("UPDATE payments SET status = 'het_han' WHERE violation_id = ? AND id <> ? AND status IN ('cho_thanh_toan','cho_xac_nhan')",
            [$violation['id'], $paymentId]);
        $pdo->commit();
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    log_activity('Thanh toán', 'Giao dịch ' . $payment['transaction_code'] . ' cho biên bản ' . $violation['code']);
    return true;
}

/** Ảnh QR chuyển khoản chuẩn VietQR (quét được bằng app ngân hàng thật) */
function vietqr_image_url($amount, $content)
{
    return 'https://img.vietqr.io/image/' . rawurlencode(setting('bank_id')) . '-' . rawurlencode(setting('bank_account_no'))
        . '-compact2.png?amount=' . (int) $amount . '&addInfo=' . rawurlencode($content)
        . '&accountName=' . rawurlencode(setting('bank_account_name'));
}

/* ============================== 7. TIỆN ÍCH ============================== */

function money($amount)
{
    return number_format((float) $amount, 0, ',', '.') . ' ₫';
}

function fmt_date($date)
{
    return $date ? date('d/m/Y', strtotime($date)) : '';
}

function fmt_datetime($date)
{
    return $date ? date('d/m/Y H:i', strtotime($date)) : '';
}

/** "30a-123.45" => "30A12345" để so sánh biển số không phụ thuộc dấu */
function normalize_plate($plate)
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $plate));
}

/** Biểu thức SQL làm điều tương tự normalize_plate() với một cột */
function sql_plate($column)
{
    return "REPLACE(REPLACE(REPLACE(UPPER($column), '-', ''), '.', ''), ' ', '')";
}

/** Sinh mã duy nhất, vd VP2611151234 */
function generate_code($prefix, $table, $column = 'code')
{
    do {
        $code = $prefix . date('ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    } while (fetch_value("SELECT COUNT(*) FROM $table WHERE $column = ?", [$code]));
    return $code;
}

/** Upload file an toàn. Trả về đường dẫn "uploads/..." hoặc null nếu không chọn file; file sai thì ném Exception */
function upload_file($field, $subDir, array $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Tải file lên thất bại (mã lỗi ' . $file['error'] . ').');
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new Exception('File vượt quá dung lượng cho phép (5MB).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception('Chỉ chấp nhận file: ' . implode(', ', $allowedExt));
    }
    if ($ext !== 'pdf' && @getimagesize($file['tmp_name']) === false) {
        throw new Exception('File ảnh không hợp lệ.');
    }
    $dir = UPLOAD_DIR . '/' . $subDir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new Exception('Không lưu được file.');
    }
    return 'uploads/' . $subDir . '/' . $name;
}

function delete_upload($path)
{
    if ($path && is_file(APP_DIR . '/' . $path)) {
        @unlink(APP_DIR . '/' . $path);
    }
}

/** Ghi nhật ký vào bảng activity_logs */
function log_activity($action, $detail = '')
{
    $user = current_user();
    query('INSERT INTO activity_logs (user_id, action, detail, ip_address) VALUES (?, ?, ?, ?)',
        [$user['id'] ?? null, $action, mb_substr($detail, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]);
}

/** Phân trang: từ ?page= tính ra LIMIT / OFFSET */
function paginate($total, $perPage = 10)
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function render_pagination(array $p)
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $query = $_GET;
    $html = '<nav><ul class="pagination justify-content-center mb-0">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $query['page'] = $i;
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="?'
            . e(http_build_query($query)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

/* ---------- Nhãn hiển thị cho các giá trị ENUM ---------- */

function vehicle_types()
{
    return ['xe_may' => 'Xe máy', 'o_to' => 'Ô tô', 'xe_tai' => 'Xe tải', 'xe_khach' => 'Xe khách', 'khac' => 'Khác'];
}

function vehicle_type_label($type)
{
    return $type === 'tat_ca' ? 'Tất cả' : (vehicle_types()[$type] ?? $type);
}

function violation_statuses()
{
    return [
        'chua_nop'  => ['Chưa nộp phạt', 'warning'],
        'khieu_nai' => ['Đang khiếu nại', 'info'],
        'da_nop'    => ['Đã nộp phạt', 'success'],
        'da_huy'    => ['Đã hủy', 'secondary'],
    ];
}

function complaint_statuses()
{
    return ['cho_xu_ly' => ['Chờ xử lý', 'warning'], 'chap_nhan' => ['Chấp nhận', 'success'], 'tu_choi' => ['Từ chối', 'danger']];
}

function payment_statuses()
{
    return [
        'cho_thanh_toan' => ['Chờ thanh toán', 'secondary'],
        'cho_xac_nhan'   => ['Chờ xác nhận', 'warning'],
        'thanh_cong'     => ['Thành công', 'success'],
        'that_bai'       => ['Thất bại', 'danger'],
        'het_han'        => ['Hết hạn', 'dark'],
    ];
}

function payment_methods()
{
    return ['qr_vi_dien_tu' => 'Quét QR (ví điện tử)', 'chuyen_khoan' => 'Chuyển khoản VietQR', 'tien_mat' => 'Tiền mặt tại trụ sở'];
}

/** <span class="badge badge-warning">Chưa nộp phạt</span> */
function badge($status, array $map)
{
    $item = $map[$status] ?? [$status, 'light'];
    return '<span class="badge badge-' . $item[1] . '">' . e($item[0]) . '</span>';
}

/* ---------- Chạy ở mọi request: mã QR quá giờ chuyển sang "hết hạn" ---------- */
if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    query("UPDATE payments SET status = 'het_han' WHERE status = 'cho_thanh_toan' AND expires_at < NOW()");
}
