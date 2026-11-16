<?php
/**
 * QUẢN LÝ NGƯỜI DÙNG (chỉ admin)
 *   GET  users.php?q=        -> show_list()
 *   GET  users.php?new=1     -> show_form()        tạo tài khoản cán bộ / người dân
 *   POST action=create       -> create_user()      kiểm tra -> password_hash -> INSERT
 *   POST action=role         -> change_role()      UPDATE users SET role
 *   POST action=toggle       -> toggle_active()    khóa / mở khóa (is_active = 1 - is_active)
 *   POST action=reset        -> reset_password()   sinh mật khẩu ngẫu nhiên, hiện cho admin
 */
require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../layout.php';
require_admin();

$errors = [];
if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'create') {
        $errors = create_user();
    } else {
        $target = fetch_one('SELECT * FROM users WHERE id = ?', [(int) input('id')]);
        if (!$target) {
            flash('danger', 'Không tìm thấy người dùng.');
        } elseif ((int) $target['id'] === (int) current_user()['id']) {
            flash('warning', 'Bạn không thể tự đổi quyền / khóa tài khoản của chính mình.'); // tránh tự khóa mất quyền admin
        } elseif ($action === 'role') {
            change_role($target);
        } elseif ($action === 'toggle') {
            toggle_active($target);
        } elseif ($action === 'reset') {
            reset_password($target);
        }
        redirect('admin/users.php');
    }
}
($errors || input('new') !== '') ? show_form($errors) : show_list();

/* ======================= XỬ LÝ ======================= */

function create_user()
{
    $username = input('username');
    $role = input('role');
    $cccd = input('cccd');
    $password = $_POST['password'] ?? '';

    $errors = [];
    if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
        $errors[] = 'Tên đăng nhập 4-30 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.';
    } elseif (fetch_value('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }
    if (input('full_name') === '') $errors[] = 'Vui lòng nhập họ tên.';
    if (!isset(role_labels()[$role])) $errors[] = 'Vai trò không hợp lệ.';
    if ($cccd !== '' && !preg_match('/^\d{12}$/', $cccd)) $errors[] = 'Số CCCD phải gồm 12 chữ số.';
    if (strlen($password) < 6) $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
    if ($errors) return $errors;

    query('INSERT INTO users (username, password, full_name, email, phone, cccd, role) VALUES (?, ?, ?, ?, ?, ?, ?)', [
        $username, password_hash($password, PASSWORD_DEFAULT), input('full_name'), input('email') ?: null, input('phone') ?: null, $cccd ?: null, $role,
    ]);
    log_activity('Tạo tài khoản', $username . ' (' . $role . ')');
    flash('success', 'Đã tạo tài khoản ' . e($username) . '.');
    redirect('admin/users.php');
}

function change_role(array $target)
{
    if (!isset(role_labels()[input('role')])) {
        return;
    }
    query('UPDATE users SET role = ? WHERE id = ?', [input('role'), $target['id']]);
    log_activity('Đổi vai trò', $target['username'] . ' -> ' . input('role'));
    flash('success', 'Đã đổi vai trò của ' . e($target['username']) . '.');
}

function toggle_active(array $target)
{
    query('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$target['id']]);
    log_activity($target['is_active'] ? 'Khóa tài khoản' : 'Mở khóa tài khoản', $target['username']);
    flash('success', ($target['is_active'] ? 'Đã khóa' : 'Đã mở khóa') . ' tài khoản ' . e($target['username']) . '.');
}

function reset_password(array $target)
{
    $newPass = bin2hex(random_bytes(4)); // 8 ký tự ngẫu nhiên
    query('UPDATE users SET password = ? WHERE id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $target['id']]);
    log_activity('Đặt lại mật khẩu', $target['username']);
    flash('success', 'Mật khẩu mới của <b>' . e($target['username']) . '</b>: <code>' . $newPass . '</code>');
}

/* ======================= GIAO DIỆN ======================= */

function show_list()
{
    $keyword = input('q');
    $where = '1=1';
    $params = [];
    if ($keyword !== '') {
        $where = '(username LIKE ? OR full_name LIKE ? OR email LIKE ? OR cccd LIKE ?)';
        $params = array_fill(0, 4, '%' . $keyword . '%');
    }
    $pg = paginate((int) fetch_value("SELECT COUNT(*) FROM users WHERE $where", $params), 15);
    $users = fetch_all("SELECT * FROM users WHERE $where ORDER BY FIELD(role, 'admin', 'officer', 'citizen'), created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
    $meId = (int) current_user()['id'];

    admin_header('Quản lý người dùng', 'users');
    ?>
    <form class="filter-bar form-row align-items-end" method="get">
        <div class="col-md-6 mb-2"><input name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Tên đăng nhập, họ tên, email, CCCD"></div>
        <div class="col-md-6 mb-2"><button class="btn btn-dark"><i class="fa fa-search"></i> Tìm</button>
            <a href="users.php?new=1" class="btn btn-app"><i class="fa fa-user-plus"></i> Thêm tài khoản</a></div>
    </form>
    <div class="table-wrap"><table class="table table-hover">
        <thead><tr><th>Tài khoản</th><th>Họ tên</th><th>Liên hệ</th><th>CCCD</th><th>Vai trò</th><th>Trạng thái</th><th style="width:200px"></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (int) $u['id'] === $meId; ?>
            <tr>
                <td><b><?= e($u['username']) ?></b><br><small class="text-muted"><?= fmt_date($u['created_at']) ?></small></td>
                <td><?= e($u['full_name']) ?></td>
                <td><small><?= e($u['email']) ?><br><?= e($u['phone']) ?></small></td>
                <td><?= e($u['cccd']) ?></td>
                <td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="role">
                    <select name="role" class="form-control form-control-sm" onchange="this.form.submit()" <?= $self ? 'disabled' : '' ?>>
                        <?php foreach (role_labels() as $k => $label): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></form></td>
                <td><?= $u['is_active'] ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-secondary">Đã khóa</span>' ?></td>
                <td class="actions">
                    <?php if ($self): ?><small class="text-muted">(Bạn)</small><?php else: ?>
                        <form method="post" class="d-inline" data-confirm="<?= $u['is_active'] ? 'Khóa' : 'Mở khóa' ?> tài khoản <?= e($u['username']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="toggle">
                            <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>"><i class="fa fa-<?= $u['is_active'] ? 'lock' : 'unlock' ?>"></i> <?= $u['is_active'] ? 'Khóa' : 'Mở' ?></button>
                        </form>
                        <form method="post" class="d-inline" data-confirm="Đặt lại mật khẩu cho <?= e($u['username']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="reset">
                            <button class="btn btn-sm btn-outline-secondary"><i class="fa fa-key"></i> Reset MK</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="mt-3"><?= render_pagination($pg) ?></div>
    <?php
    admin_footer();
}

function show_form(array $errors)
{
    admin_header('Thêm tài khoản', 'users');
    ?>
    <div class="card card-app" style="max-width: 760px"><div class="card-header">Thêm tài khoản</div><div class="card-body">
        <?= render_errors($errors) ?>
        <form method="post" action="users.php">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group col-md-6"><label>Tên đăng nhập *</label><input name="username" class="form-control" value="<?= old('username') ?>" required></div>
                <div class="form-group col-md-6"><label>Mật khẩu *</label><input type="password" name="password" class="form-control" required></div>
                <div class="form-group col-md-6"><label>Họ tên *</label><input name="full_name" class="form-control" value="<?= old('full_name') ?>" required></div>
                <div class="form-group col-md-6"><label>Vai trò</label><select name="role" class="form-control">
                    <?php foreach (role_labels() as $k => $label): ?><option value="<?= $k ?>" <?= input('role', 'officer') === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                </select></div>
                <div class="form-group col-md-4"><label>Email</label><input type="email" name="email" class="form-control" value="<?= old('email') ?>"></div>
                <div class="form-group col-md-4"><label>Điện thoại</label><input name="phone" class="form-control" value="<?= old('phone') ?>"></div>
                <div class="form-group col-md-4"><label>CCCD</label><input name="cccd" class="form-control" maxlength="12" value="<?= old('cccd') ?>"></div>
            </div>
            <button class="btn btn-app"><i class="fa fa-save"></i> Tạo tài khoản</button>
            <a href="users.php" class="btn btn-secondary">Quay lại</a>
        </form>
    </div></div>
    <?php
    admin_footer();
}
