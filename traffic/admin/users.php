<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $target = fetch_one('SELECT * FROM users WHERE id = ?', [(int) input('id')]);
    $action = input('action');
    if (!$target) {
        flash('danger', 'Không tìm thấy người dùng.');
    } elseif ((int) $target['id'] === (int) current_user()['id']) {
        flash('warning', 'Bạn không thể thay đổi tài khoản của chính mình tại đây.');
    } elseif ($action === 'role' && isset(role_labels()[input('role')])) {
        query('UPDATE users SET role = ? WHERE id = ?', [input('role'), $target['id']]);
        log_activity('Đổi vai trò', $target['username'] . ' -> ' . input('role'));
        flash('success', 'Đã đổi vai trò của ' . e($target['username']) . '.');
    } elseif ($action === 'toggle') {
        query('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$target['id']]);
        log_activity($target['is_active'] ? 'Khóa tài khoản' : 'Mở khóa tài khoản', $target['username']);
        flash('success', ($target['is_active'] ? 'Đã khóa' : 'Đã mở khóa') . ' tài khoản ' . e($target['username']) . '.');
    } elseif ($action === 'reset') {
        $newPass = substr(bin2hex(random_bytes(4)), 0, 8);
        query('UPDATE users SET password = ? WHERE id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $target['id']]);
        log_activity('Đặt lại mật khẩu', $target['username']);
        flash('success', 'Mật khẩu mới của <b>' . e($target['username']) . '</b>: <code>' . $newPass . '</code>');
    }
    redirect('admin/users.php');
}

$keyword = input('q');
$params = [];
$where = '1=1';
if ($keyword !== '') {
    $where = '(username LIKE ? OR full_name LIKE ? OR email LIKE ? OR cccd LIKE ?)';
    $params = array_fill(0, 4, '%' . $keyword . '%');
}
$pg = paginate((int) fetch_value("SELECT COUNT(*) FROM users WHERE $where", $params), 15);
$users = fetch_all("SELECT * FROM users WHERE $where ORDER BY FIELD(role, 'admin', 'officer', 'citizen'), created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$pageTitle = 'Quản lý người dùng';
$activeMenu = 'users';
require __DIR__ . '/includes/header.php';
?>
<form class="filter-bar form-row align-items-end" method="get">
    <div class="col-md-6 mb-2"><input name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Tên đăng nhập, họ tên, email, CCCD"></div>
    <div class="col-md-6 mb-2">
        <button class="btn btn-dark"><i class="fa fa-search"></i> Tìm</button>
        <a href="user_form.php" class="btn btn-app"><i class="fa fa-user-plus"></i> Thêm tài khoản</a>
    </div>
</form>
<div class="table-wrap">
    <table class="table table-hover">
        <thead><tr><th>Tài khoản</th><th>Họ tên</th><th>Liên hệ</th><th>CCCD</th><th>Vai trò</th><th>Trạng thái</th><th style="width:200px"></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (int) $u['id'] === (int) current_user()['id']; ?>
            <tr>
                <td><b><?= e($u['username']) ?></b><br><small class="text-muted"><?= fmt_date($u['created_at']) ?></small></td>
                <td><?= e($u['full_name']) ?></td>
                <td><small><?= e($u['email']) ?><br><?= e($u['phone']) ?></small></td>
                <td><?= e($u['cccd']) ?></td>
                <td>
                    <form method="post" class="form-inline">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="role">
                        <select name="role" class="form-control form-control-sm" onchange="this.form.submit()" <?= $self ? 'disabled' : '' ?>>
                            <?php foreach (role_labels() as $k => $label): ?>
                                <option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td><?= $u['is_active'] ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-secondary">Đã khóa</span>' ?></td>
                <td class="actions">
                    <?php if (!$self): ?>
                        <form method="post" class="d-inline" data-confirm="<?= $u['is_active'] ? 'Khóa' : 'Mở khóa' ?> tài khoản <?= e($u['username']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>"><i class="fa fa-<?= $u['is_active'] ? 'lock' : 'unlock' ?>"></i> <?= $u['is_active'] ? 'Khóa' : 'Mở' ?></button>
                        </form>
                        <form method="post" class="d-inline" data-confirm="Đặt lại mật khẩu cho <?= e($u['username']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="reset">
                            <button class="btn btn-sm btn-outline-secondary"><i class="fa fa-key"></i> Reset MK</button>
                        </form>
                    <?php else: ?><small class="text-muted">(Bạn)</small><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= render_pagination($pg) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
