<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect(is_staff() ? 'admin/index.php' : 'index.php');
}

$error = '';
if (is_post()) {
    verify_csrf();
    $user = fetch_one('SELECT * FROM users WHERE username = ?', [input('username')]);
    if (!$user || !password_verify($_POST['password'] ?? '', $user['password'])) {
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    } elseif (!$user['is_active']) {
        $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        log_activity('Đăng nhập', $user['username']);

        // Chỉ cho phép chuyển hướng nội bộ (tránh open redirect)
        $back = $_POST['redirect'] ?? '';
        if ($back !== '' && $back[0] === '/' && strpos($back, '//') !== 0) {
            header('Location: ' . $back);
            exit;
        }
        redirect(in_array($user['role'], ['admin', 'officer'], true) ? 'admin/index.php' : 'index.php');
    }
}

$pageTitle = 'Đăng nhập';
$activeMenu = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="page-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-app">
                    <div class="card-header"><i class="fa fa-sign-in"></i> Đăng nhập hệ thống</div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="redirect" value="<?= e(input('redirect')) ?>">
                            <div class="form-group">
                                <label>Tên đăng nhập</label>
                                <input type="text" name="username" class="form-control" value="<?= old('username') ?>" required autofocus>
                            </div>
                            <div class="form-group">
                                <label>Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button class="btn btn-app btn-block">Đăng nhập</button>
                        </form>
                        <hr>
                        <p class="text-center mb-0">Chưa có tài khoản? <a class="text-app" href="<?= url('register.php') ?>">Đăng ký ngay</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
