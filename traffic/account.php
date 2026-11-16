<?php
/**
 * TÀI KHOẢN
 *   account.php?do=login     -> do_login()     (POST) / form đăng nhập
 *   account.php?do=register  -> do_register()  (POST) / form đăng ký
 *   account.php?do=profile   -> do_profile()   (POST) / form sửa thông tin, đổi mật khẩu
 *   account.php?do=logout    -> do_logout()
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';

$do = input('do', 'login');
$errors = [];

if ($do === 'logout') {
    do_logout();
}
if ($do === 'profile') {
    require_login();
} elseif (is_logged_in()) {
    redirect(is_staff() ? 'admin/index.php' : 'index.php'); // đã đăng nhập thì không cần login/register
}

if (is_post()) {
    verify_csrf();
    if ($do === 'login') $errors = do_login();
    if ($do === 'register') $errors = do_register();
    if ($do === 'profile') $errors = do_profile();
}

page_header(['login' => 'Đăng nhập', 'register' => 'Đăng ký', 'profile' => 'Tài khoản'][$do] ?? 'Tài khoản', $do);
echo '<div class="page-content"><div class="container"><div class="row justify-content-center">';
if ($do === 'register') {
    register_form($errors);
} elseif ($do === 'profile') {
    profile_form($errors);
} else {
    login_form($errors);
}
echo '</div></div></div>';
page_footer();

/* ======================= XỬ LÝ ======================= */

/** Tìm user theo username -> password_verify -> lưu user_id vào session -> chuyển trang */
function do_login()
{
    $user = fetch_one('SELECT * FROM users WHERE username = ?', [input('username')]);
    if (!$user || !password_verify($_POST['password'] ?? '', $user['password'])) {
        return ['Tên đăng nhập hoặc mật khẩu không đúng.'];
    }
    if (!$user['is_active']) {
        return ['Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.'];
    }
    session_regenerate_id(true); // đổi mã phiên, chống session fixation
    $_SESSION['user_id'] = $user['id'];
    log_activity('Đăng nhập', $user['username']);

    // Quay lại trang trước khi bị bắt đăng nhập; chỉ cho đường dẫn nội bộ (chống open redirect)
    $back = $_POST['redirect'] ?? '';
    if ($back !== '' && $back[0] === '/' && strpos($back, '//') !== 0) {
        header('Location: ' . $back);
        exit;
    }
    redirect(in_array($user['role'], ['admin', 'officer'], true) ? 'admin/index.php' : 'index.php');
}

/** Kiểm tra dữ liệu -> password_hash -> INSERT users -> đăng nhập luôn */
function do_register()
{
    $d = ['username' => input('username'), 'full_name' => input('full_name'), 'email' => input('email'), 'phone' => input('phone'), 'cccd' => input('cccd')];
    $password = $_POST['password'] ?? '';

    $errors = [];
    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $d['username'])) {
        $errors[] = 'Tên đăng nhập 4-30 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.';
    } elseif (fetch_value('SELECT COUNT(*) FROM users WHERE username = ?', [$d['username']])) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }
    if ($d['full_name'] === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (!preg_match('/^\d{12}$/', $d['cccd'])) {
        $errors[] = 'Số CCCD phải gồm 12 chữ số (dùng để tra cứu xe đứng tên bạn).';
    } elseif (fetch_value('SELECT COUNT(*) FROM users WHERE cccd = ?', [$d['cccd']])) {
        $errors[] = 'Số CCCD đã được đăng ký bởi tài khoản khác.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
    } elseif ($password !== ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }
    if ($errors) return $errors;

    query('INSERT INTO users (username, password, full_name, email, phone, cccd, role) VALUES (?, ?, ?, ?, ?, ?, ?)', [
        $d['username'], password_hash($password, PASSWORD_DEFAULT), $d['full_name'], $d['email'] ?: null, $d['phone'] ?: null, $d['cccd'], 'citizen',
    ]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = db()->lastInsertId();
    log_activity('Đăng ký tài khoản', $d['username']);
    flash('success', 'Đăng ký thành công! Chào mừng ' . e($d['full_name']) . '.');
    redirect('my.php');
}

/** UPDATE họ tên, email, SĐT; nếu nhập mật khẩu mới thì kiểm tra mật khẩu cũ rồi UPDATE password */
function do_profile()
{
    $user = current_user();
    $fullName = input('full_name');
    $email = input('email');
    $newPassword = $_POST['new_password'] ?? '';

    $errors = [];
    if ($fullName === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if ($newPassword !== '') {
        if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
            $errors[] = 'Mật khẩu hiện tại không đúng.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Mật khẩu mới tối thiểu 6 ký tự.';
        }
    }
    if ($errors) return $errors;

    query('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?', [$fullName, $email ?: null, input('phone') ?: null, $user['id']]);
    if ($newPassword !== '') {
        query('UPDATE users SET password = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
    }
    flash('success', 'Đã cập nhật thông tin tài khoản.');
    redirect('account.php?do=profile');
}

function do_logout()
{
    if (is_logged_in()) {
        log_activity('Đăng xuất', current_user()['username']);
    }
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('info', 'Bạn đã đăng xuất.');
    redirect('index.php');
}

/* ======================= GIAO DIỆN ======================= */

function login_form(array $errors)
{
    ?>
    <div class="col-md-6 col-lg-5"><div class="card card-app">
        <div class="card-header"><i class="fa fa-sign-in"></i> Đăng nhập hệ thống</div>
        <div class="card-body">
            <?= render_errors($errors) ?>
            <form method="post" action="<?= url('account.php?do=login') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e(input('redirect')) ?>">
                <div class="form-group"><label>Tên đăng nhập</label><input name="username" class="form-control" value="<?= old('username') ?>" required autofocus></div>
                <div class="form-group"><label>Mật khẩu</label><input type="password" name="password" class="form-control" required></div>
                <button class="btn btn-app btn-block">Đăng nhập</button>
            </form>
            <hr>
            <p class="text-center mb-0">Chưa có tài khoản? <a class="text-app" href="<?= url('account.php?do=register') ?>">Đăng ký ngay</a></p>
        </div>
    </div></div>
    <?php
}

function register_form(array $errors)
{
    ?>
    <div class="col-lg-7"><div class="card card-app">
        <div class="card-header"><i class="fa fa-user-plus"></i> Đăng ký tài khoản người dân</div>
        <div class="card-body">
            <?= render_errors($errors) ?>
            <form method="post" action="<?= url('account.php?do=register') ?>">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group col-md-6"><label>Tên đăng nhập *</label><input name="username" class="form-control" value="<?= old('username') ?>" required></div>
                    <div class="form-group col-md-6"><label>Họ và tên *</label><input name="full_name" class="form-control" value="<?= old('full_name') ?>" required></div>
                    <div class="form-group col-md-6"><label>Số CCCD *</label><input name="cccd" class="form-control" value="<?= old('cccd') ?>" maxlength="12" required>
                        <small class="text-muted">Hệ thống tự liên kết các xe đứng tên CCCD này.</small></div>
                    <div class="form-group col-md-6"><label>Số điện thoại</label><input name="phone" class="form-control" value="<?= old('phone') ?>"></div>
                    <div class="form-group col-md-12"><label>Email</label><input type="email" name="email" class="form-control" value="<?= old('email') ?>"></div>
                    <div class="form-group col-md-6"><label>Mật khẩu *</label><input type="password" name="password" class="form-control" required></div>
                    <div class="form-group col-md-6"><label>Nhập lại mật khẩu *</label><input type="password" name="password_confirm" class="form-control" required></div>
                </div>
                <button class="btn btn-app btn-block">Đăng ký</button>
            </form>
        </div>
    </div></div>
    <?php
}

function profile_form(array $errors)
{
    $user = current_user();
    ?>
    <div class="col-lg-7"><div class="card card-app">
        <div class="card-header"><i class="fa fa-id-card"></i> Thông tin tài khoản</div>
        <div class="card-body">
            <?= render_errors($errors) ?>
            <form method="post" action="<?= url('account.php?do=profile') ?>">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group col-md-6"><label>Tên đăng nhập</label><input class="form-control" value="<?= e($user['username']) ?>" disabled></div>
                    <div class="form-group col-md-6"><label>Vai trò</label><input class="form-control" value="<?= e(role_labels()[$user['role']]) ?>" disabled></div>
                    <div class="form-group col-md-6"><label>Họ và tên</label><input name="full_name" class="form-control" value="<?= old('full_name', $user['full_name']) ?>" required></div>
                    <div class="form-group col-md-6"><label>Số CCCD</label><input class="form-control" value="<?= e($user['cccd']) ?>" disabled></div>
                    <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" class="form-control" value="<?= old('email', $user['email']) ?>"></div>
                    <div class="form-group col-md-6"><label>Số điện thoại</label><input name="phone" class="form-control" value="<?= old('phone', $user['phone']) ?>"></div>
                </div>
                <h6 class="mt-3 font-weight-bold">Đổi mật khẩu <small class="text-muted">(bỏ trống nếu không đổi)</small></h6>
                <div class="form-row">
                    <div class="form-group col-md-6"><input type="password" name="current_password" class="form-control" placeholder="Mật khẩu hiện tại"></div>
                    <div class="form-group col-md-6"><input type="password" name="new_password" class="form-control" placeholder="Mật khẩu mới"></div>
                </div>
                <button class="btn btn-app">Lưu thay đổi</button>
            </form>
        </div>
    </div></div>
    <?php
}
