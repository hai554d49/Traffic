<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$errors = [];
if (is_post()) {
    verify_csrf();
    $username = input('username');
    $fullName = input('full_name');
    $role = input('role');
    $cccd = input('cccd');
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
        $errors[] = 'Tên đăng nhập 4-30 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.';
    } elseif (fetch_value('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }
    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    if (!isset(role_labels()[$role])) {
        $errors[] = 'Vai trò không hợp lệ.';
    }
    if ($cccd !== '' && !preg_match('/^\d{12}$/', $cccd)) {
        $errors[] = 'Số CCCD phải gồm 12 chữ số.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
    }

    if (!$errors) {
        query('INSERT INTO users (username, password, full_name, email, phone, cccd, role) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $username, password_hash($password, PASSWORD_DEFAULT), $fullName,
            input('email') ?: null, input('phone') ?: null, $cccd ?: null, $role,
        ]);
        log_activity('Tạo tài khoản', $username . ' (' . $role . ')');
        flash('success', 'Đã tạo tài khoản ' . e($username) . '.');
        redirect('admin/users.php');
    }
}

$pageTitle = 'Thêm tài khoản';
$activeMenu = 'users';
require __DIR__ . '/includes/header.php';
?>
<div class="card card-app" style="max-width: 760px">
    <div class="card-header">Thêm tài khoản</div>
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="form-group col-md-6"><label>Tên đăng nhập *</label><input name="username" class="form-control" value="<?= old('username') ?>" required></div>
                <div class="form-group col-md-6"><label>Mật khẩu *</label><input type="password" name="password" class="form-control" required></div>
                <div class="form-group col-md-6"><label>Họ tên *</label><input name="full_name" class="form-control" value="<?= old('full_name') ?>" required></div>
                <div class="form-group col-md-6"><label>Vai trò</label>
                    <select name="role" class="form-control">
                        <?php foreach (role_labels() as $k => $label): ?>
                            <option value="<?= $k ?>" <?= input('role', 'officer') === $k ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4"><label>Email</label><input type="email" name="email" class="form-control" value="<?= old('email') ?>"></div>
                <div class="form-group col-md-4"><label>Điện thoại</label><input name="phone" class="form-control" value="<?= old('phone') ?>"></div>
                <div class="form-group col-md-4"><label>CCCD</label><input name="cccd" class="form-control" maxlength="12" value="<?= old('cccd') ?>"></div>
            </div>
            <button class="btn btn-app"><i class="fa fa-save"></i> Tạo tài khoản</button>
            <a href="users.php" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
