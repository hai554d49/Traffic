<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
if (is_post()) {
    verify_csrf();
    $data = [
        'username'  => input('username'),
        'full_name' => input('full_name'),
        'email'     => input('email'),
        'phone'     => input('phone'),
        'cccd'      => input('cccd'),
    ];
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $data['username'])) {
        $errors[] = 'Tên đăng nhập 4-30 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.';
    } elseif (fetch_value('SELECT COUNT(*) FROM users WHERE username = ?', [$data['username']])) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }
    if ($data['full_name'] === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    if (!preg_match('/^\d{12}$/', $data['cccd'])) {
        $errors[] = 'Số CCCD phải gồm 12 chữ số (dùng để tra cứu xe đứng tên bạn).';
    } elseif (fetch_value('SELECT COUNT(*) FROM users WHERE cccd = ?', [$data['cccd']])) {
        $errors[] = 'Số CCCD đã được đăng ký bởi tài khoản khác.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
    } elseif ($password !== ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$errors) {
        query('INSERT INTO users (username, password, full_name, email, phone, cccd, role) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $data['username'], password_hash($password, PASSWORD_DEFAULT), $data['full_name'],
            $data['email'] ?: null, $data['phone'] ?: null, $data['cccd'], 'citizen',
        ]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = db()->lastInsertId();
        log_activity('Đăng ký tài khoản', $data['username']);
        flash('success', 'Đăng ký thành công! Chào mừng ' . e($data['full_name']) . '.');
        redirect('my_vehicles.php');
    }
}

$pageTitle = 'Đăng ký';
$activeMenu = 'register';
require __DIR__ . '/includes/header.php';
?>
<div class="page-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card card-app">
                    <div class="card-header"><i class="fa fa-user-plus"></i> Đăng ký tài khoản người dân</div>
                    <div class="card-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Tên đăng nhập *</label>
                                    <input name="username" class="form-control" value="<?= old('username') ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Họ và tên *</label>
                                    <input name="full_name" class="form-control" value="<?= old('full_name') ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Số CCCD *</label>
                                    <input name="cccd" class="form-control" value="<?= old('cccd') ?>" maxlength="12" required>
                                    <small class="text-muted">Hệ thống tự liên kết các xe đứng tên CCCD này.</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Số điện thoại</label>
                                    <input name="phone" class="form-control" value="<?= old('phone') ?>">
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= old('email') ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Mật khẩu *</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Nhập lại mật khẩu *</label>
                                    <input type="password" name="password_confirm" class="form-control" required>
                                </div>
                            </div>
                            <button class="btn btn-app btn-block">Đăng ký</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
