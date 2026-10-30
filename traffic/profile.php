<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$errors = [];

if (is_post()) {
    verify_csrf();
    $fullName = input('full_name');
    $email = input('email');
    $phone = input('phone');

    if ($fullName === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }

    $newPassword = $_POST['new_password'] ?? '';
    if ($newPassword !== '') {
        if (!password_verify($_POST['current_password'] ?? '', $user['password'])) {
            $errors[] = 'Mật khẩu hiện tại không đúng.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Mật khẩu mới tối thiểu 6 ký tự.';
        }
    }

    if (!$errors) {
        query('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?', [$fullName, $email ?: null, $phone ?: null, $user['id']]);
        if ($newPassword !== '') {
            query('UPDATE users SET password = ? WHERE id = ?', [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        }
        flash('success', 'Đã cập nhật thông tin tài khoản.');
        redirect('profile.php');
    }
}

$pageTitle = 'Tài khoản';
require __DIR__ . '/includes/header.php';
?>
<div class="page-content">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card card-app">
                    <div class="card-header"><i class="fa fa-id-card"></i> Thông tin tài khoản</div>
                    <div class="card-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Tên đăng nhập</label>
                                    <input class="form-control" value="<?= e($user['username']) ?>" disabled>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Vai trò</label>
                                    <input class="form-control" value="<?= e(role_labels()[$user['role']]) ?>" disabled>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Họ và tên</label>
                                    <input name="full_name" class="form-control" value="<?= old('full_name', $user['full_name']) ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Số CCCD</label>
                                    <input class="form-control" value="<?= e($user['cccd']) ?>" disabled>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= old('email', $user['email']) ?>">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Số điện thoại</label>
                                    <input name="phone" class="form-control" value="<?= old('phone', $user['phone']) ?>">
                                </div>
                            </div>
                            <h6 class="mt-3 font-weight-bold">Đổi mật khẩu <small class="text-muted">(bỏ trống nếu không đổi)</small></h6>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <input type="password" name="current_password" class="form-control" placeholder="Mật khẩu hiện tại">
                                </div>
                                <div class="form-group col-md-6">
                                    <input type="password" name="new_password" class="form-control" placeholder="Mật khẩu mới">
                                </div>
                            </div>
                            <button class="btn btn-app">Lưu thay đổi</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
