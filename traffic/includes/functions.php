<?php
/**
 * Các hàm tiện ích dùng chung.
 */

/* ===================== CƠ SỞ DỮ LIỆU ===================== */

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
            die('<div style="font-family:sans-serif;padding:30px"><h3>Không kết nối được cơ sở dữ liệu</h3>'
                . '<p>' . htmlspecialchars($ex->getMessage()) . '</p>'
                . '<p>Kiểm tra <code>config/config.php</code> hoặc chạy <a href="' . BASE_URL . '/install.php">install.php</a> để tạo CSDL.</p></div>');
        }
    }
    return $pdo;
}

/** Chạy câu lệnh có tham số và trả về PDOStatement */
function query($sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetch_one($sql, array $params = [])
{
    $row = query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetch_all($sql, array $params = [])
{
    return query($sql, $params)->fetchAll();
}

function fetch_value($sql, array $params = [])
{
    return query($sql, $params)->fetchColumn();
}

/* ===================== HTML / ĐIỀU HƯỚNG ===================== */

function e($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL tuyệt đối (dùng cho mã QR để quét bằng điện thoại) */
function absolute_url($path = '')
{
    if (PUBLIC_HOST !== '') {
        $host = rtrim(PUBLIC_HOST, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $host . url($path);
}

function is_localhost_url()
{
    return PUBLIC_HOST === '' && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $_SERVER['HTTP_HOST'] ?? '');
}

function redirect($path)
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
            . $f['message']
            . '<button type="button" class="close" data-dismiss="alert">&times;</button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function input($key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

/** Giữ lại giá trị cũ của form khi có lỗi */
function old($key, $default = '')
{
    return e($_POST[$key] ?? $default);
}

/* ===================== CSRF ===================== */

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

function verify_csrf()
{
    if (!hash_equals(csrf_token(), $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('Phiên làm việc đã hết hạn, vui lòng tải lại trang và thử lại.');
    }
}

/* ===================== CẤU HÌNH HỆ THỐNG ===================== */

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

/* ===================== ĐỊNH DẠNG ===================== */

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

/** Chuẩn hóa biển số để so sánh: "30a-123.45" => "30A12345" */
function normalize_plate($plate)
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $plate));
}

/** Biểu thức SQL chuẩn hóa cột biển số tương ứng với normalize_plate() */
function sql_plate($column)
{
    return "REPLACE(REPLACE(REPLACE(UPPER($column), '-', ''), '.', ''), ' ', '')";
}

/* ===================== NHÃN TRẠNG THÁI ===================== */

function vehicle_types()
{
    return [
        'xe_may'   => 'Xe máy',
        'o_to'     => 'Ô tô',
        'xe_tai'   => 'Xe tải',
        'xe_khach' => 'Xe khách',
        'khac'     => 'Khác',
    ];
}

function vehicle_type_label($type)
{
    if ($type === 'tat_ca') {
        return 'Tất cả';
    }
    return vehicle_types()[$type] ?? $type;
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
    return [
        'cho_xu_ly' => ['Chờ xử lý', 'warning'],
        'chap_nhan' => ['Chấp nhận', 'success'],
        'tu_choi'   => ['Từ chối', 'danger'],
    ];
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
    return [
        'qr_vi_dien_tu' => 'Quét QR (ví điện tử)',
        'chuyen_khoan'  => 'Chuyển khoản VietQR',
        'tien_mat'      => 'Tiền mặt tại trụ sở',
    ];
}

function badge($status, array $map)
{
    $item = $map[$status] ?? [$status, 'light'];
    return '<span class="badge badge-' . $item[1] . '">' . e($item[0]) . '</span>';
}

/* ===================== TÍNH TIỀN PHẠT & LÃI CHẬM NỘP ===================== */

/**
 * Tính số tiền phải nộp của một vi phạm tại ngày hiện tại.
 *  - Trước hoặc đúng hạn: chỉ nộp tiền phạt.
 *  - Quá hạn: cộng thêm tiền chậm nộp = tiền phạt x lãi suất(%/ngày) x số ngày quá hạn,
 *    tối đa không vượt quá late_fee_cap_percent % tiền phạt.
 *  - Khi đang khiếu nại, tiền chậm nộp tạm ngừng tính (hạn nộp được gia hạn khi xử lý xong).
 */
function calc_fine(array $violation, $onDate = null)
{
    $fine = (int) $violation['fine_amount'];
    $today = new DateTime($onDate ?: 'today');
    $due = new DateTime($violation['due_date']);

    $lateDays = 0;
    if ($today > $due && in_array($violation['status'], ['chua_nop'], true)) {
        $lateDays = (int) $due->diff($today)->days;
    }

    $rate = (float) setting('late_fee_rate', 0.05);
    $cap = (float) setting('late_fee_cap_percent', 100);
    $lateFee = (int) round($fine * $rate / 100 * $lateDays);
    $lateFee = min($lateFee, (int) round($fine * $cap / 100));

    $daysLeft = (int) $today->diff($due)->format('%r%a');

    return [
        'fine'      => $fine,
        'late_days' => $lateDays,
        'late_fee'  => $lateFee,
        'total'     => $fine + $lateFee,
        'overdue'   => $lateDays > 0,
        'days_left' => $daysLeft,
        'rate'      => $rate,
    ];
}

/** Lấy vi phạm kèm thông tin xe và lỗi theo mã biên bản */
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

/* ===================== THANH TOÁN ===================== */

/** Ghi nhận thanh toán thành công và cập nhật trạng thái vi phạm */
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
        // Hủy các giao dịch đang chờ khác của cùng vi phạm
        query("UPDATE payments SET status = 'het_han' WHERE violation_id = ? AND id <> ? AND status IN ('cho_thanh_toan','cho_xac_nhan')",
            [$violation['id'], $paymentId]);
        $pdo->commit();
        log_activity('Thanh toán', 'Giao dịch ' . $payment['transaction_code'] . ' cho biên bản ' . $violation['code']);
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function expire_old_payments()
{
    query("UPDATE payments SET status = 'het_han' WHERE status = 'cho_thanh_toan' AND expires_at IS NOT NULL AND expires_at < NOW()");
}

/** Ảnh QR chuyển khoản chuẩn VietQR (quét được bằng app ngân hàng thật) */
function vietqr_image_url($amount, $content)
{
    return 'https://img.vietqr.io/image/' . rawurlencode(setting('bank_id')) . '-' . rawurlencode(setting('bank_account_no'))
        . '-compact2.png?amount=' . (int) $amount
        . '&addInfo=' . rawurlencode($content)
        . '&accountName=' . rawurlencode(setting('bank_account_name'));
}

/* ===================== KHÁC ===================== */

/** Sinh mã duy nhất, vd: VP2610281234 */
function generate_code($prefix, $table, $column = 'code')
{
    do {
        $code = $prefix . date('ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    } while (fetch_value("SELECT COUNT(*) FROM $table WHERE $column = ?", [$code]));
    return $code;
}

/**
 * Upload file an toàn. Trả về đường dẫn tương đối (uploads/...) hoặc null nếu không chọn file.
 * Ném Exception nếu file không hợp lệ.
 */
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

function log_activity($action, $detail = '')
{
    $user = current_user();
    query('INSERT INTO activity_logs (user_id, action, detail, ip_address) VALUES (?, ?, ?, ?)',
        [$user['id'] ?? null, $action, mb_substr($detail, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null]);
}

/** Tính thông tin phân trang */
function paginate($total, $perPage = 10)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
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
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '">'
            . '<a class="page-link" href="?' . e(http_build_query($query)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}
