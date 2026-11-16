<?php
/**
 * CẤU HÌNH HỆ THỐNG (chỉ admin) - 3 tab
 *   settings.php                 -> tab Tham số: hạn nộp, lãi suất, tài khoản ngân hàng   (POST action=save_settings -> save_settings())
 *   settings.php?tab=types       -> tab Danh mục lỗi & mức phạt (thêm/sửa/xóa)           (POST action=save_type / delete_type)
 *   settings.php?tab=logs        -> tab Nhật ký hoạt động (chỉ xem)
 */
require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../layout.php';
require_admin();

const NUMERIC_SETTINGS = ['payment_deadline_days', 'late_fee_rate', 'late_fee_cap_percent', 'qr_expire_minutes'];

$errors = [];
if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'save_settings') $errors = save_settings();
    if ($action === 'save_type') $errors = save_type();
    if ($action === 'delete_type') delete_type();
}

$tab = input('tab', 'params');
admin_header('Cấu hình hệ thống', 'settings');
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach (['params' => 'Tham số', 'types' => 'Danh mục lỗi', 'logs' => 'Nhật ký hoạt động'] as $k => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active font-weight-bold' : 'text-dark' ?>" href="?tab=<?= $k ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
</ul>
<?php
if ($tab === 'types') {
    tab_types($errors);
} elseif ($tab === 'logs') {
    tab_logs();
} else {
    tab_params($errors);
}
admin_footer();

/* ======================= XỬ LÝ ======================= */

/** Kiểm tra từng ô rồi UPDATE settings theo từng khóa. calc_fine() đọc lại nên lãi các biên bản chưa nộp đổi ngay */
function save_settings()
{
    $values = $_POST['settings'] ?? [];
    $settings = fetch_all('SELECT * FROM settings');
    $errors = [];
    foreach ($settings as $s) {
        $value = trim((string) ($values[$s['setting_key']] ?? ''));
        if ($value === '') {
            $errors[] = 'Vui lòng nhập "' . $s['label'] . '".';
        } elseif (in_array($s['setting_key'], NUMERIC_SETTINGS, true) && (!is_numeric($value) || $value < 0)) {
            $errors[] = '"' . $s['label'] . '" phải là số không âm.';
        }
    }
    if ($errors) return $errors;

    foreach ($settings as $s) {
        query('UPDATE settings SET setting_value = ? WHERE setting_key = ?', [trim($values[$s['setting_key']]), $s['setting_key']]);
    }
    log_activity('Cập nhật cấu hình');
    flash('success', 'Đã lưu cấu hình. Tiền chậm nộp của các biên bản chưa nộp được tính lại theo cấu hình mới.');
    redirect('admin/settings.php');
}

function save_type()
{
    $id = (int) input('id');
    $d = ['name' => input('name'), 'vehicle_type' => input('vehicle_type'), 'fine_amount' => (int) input('fine_amount'),
          'legal_basis' => input('legal_basis'), 'description' => input('description')];
    $errors = [];
    if ($d['name'] === '') $errors[] = 'Vui lòng nhập tên lỗi vi phạm.';
    if ($d['vehicle_type'] !== 'tat_ca' && !isset(vehicle_types()[$d['vehicle_type']])) $errors[] = 'Loại phương tiện không hợp lệ.';
    if ($d['fine_amount'] <= 0) $errors[] = 'Mức phạt phải lớn hơn 0.';
    if ($errors) return $errors;

    $values = [$d['name'], $d['vehicle_type'], $d['fine_amount'], $d['legal_basis'] ?: null, $d['description'] ?: null];
    if ($id) {
        query('UPDATE violation_types SET name = ?, vehicle_type = ?, fine_amount = ?, legal_basis = ?, description = ? WHERE id = ?', array_merge($values, [$id]));
    } else {
        query('INSERT INTO violation_types (name, vehicle_type, fine_amount, legal_basis, description) VALUES (?, ?, ?, ?, ?)', $values);
    }
    log_activity($id ? 'Sửa loại vi phạm' : 'Thêm loại vi phạm', $d['name']);
    flash('success', $id ? 'Đã cập nhật loại vi phạm.' : 'Đã thêm loại vi phạm.');
    redirect('admin/settings.php?tab=types');
}

/** Không xóa được lỗi đã dùng trong biên bản (khóa ngoại RESTRICT) */
function delete_type()
{
    $t = fetch_one('SELECT * FROM violation_types WHERE id = ?', [(int) input('id')]);
    if ($t && fetch_value('SELECT COUNT(*) FROM violations WHERE violation_type_id = ?', [$t['id']])) {
        flash('warning', 'Không thể xóa vì lỗi này đã được sử dụng trong biên bản.');
    } elseif ($t) {
        query('DELETE FROM violation_types WHERE id = ?', [$t['id']]);
        log_activity('Xóa loại vi phạm', $t['name']);
        flash('success', 'Đã xóa loại vi phạm.');
    }
    redirect('admin/settings.php?tab=types');
}

/* ======================= GIAO DIỆN ======================= */

function tab_params(array $errors)
{
    $settings = fetch_all('SELECT * FROM settings ORDER BY setting_key');
    $exFine = 1000000;
    $exDays = 20;
    $exLate = min(round($exFine * setting('late_fee_rate') / 100 * $exDays), round($exFine * setting('late_fee_cap_percent') / 100));
    ?>
    <div class="row">
        <div class="col-lg-7"><div class="card card-app"><div class="card-header">Tham số</div><div class="card-body">
            <?= render_errors($errors) ?>
            <form method="post" action="settings.php">
                <?= csrf_field() ?><input type="hidden" name="action" value="save_settings">
                <?php foreach ($settings as $s): ?>
                    <div class="form-group"><label><?= e($s['label']) ?></label>
                        <input name="settings[<?= e($s['setting_key']) ?>]" class="form-control" value="<?= e($_POST['settings'][$s['setting_key']] ?? $s['setting_value']) ?>"
                            <?= in_array($s['setting_key'], NUMERIC_SETTINGS, true) ? 'type="number" step="any" min="0"' : '' ?>></div>
                <?php endforeach; ?>
                <button class="btn btn-app"><i class="fa fa-save"></i> Lưu cấu hình</button>
            </form>
        </div></div></div>
        <div class="col-lg-5">
            <div class="card card-app"><div class="card-header">Cách tính tiền chậm nộp</div><div class="card-body">
                <p><code>Tiền chậm nộp = Tiền phạt × <?= e(setting('late_fee_rate')) ?>% × Số ngày quá hạn</code></p>
                <p>Tối đa không quá <?= e(setting('late_fee_cap_percent')) ?>% số tiền phạt. Trong thời gian khiếu nại, tiền chậm nộp tạm dừng và hạn nộp được gia hạn tương ứng.</p>
                <div class="amount-box"><b>Ví dụ:</b> phạt <?= money($exFine) ?>, quá hạn <?= $exDays ?> ngày<br>Chậm nộp = <?= money($exLate) ?><br>Tổng phải nộp = <b><?= money($exFine + $exLate) ?></b></div>
            </div></div>
            <div class="card card-app"><div class="card-header">Xem trước mã VietQR</div><div class="card-body text-center">
                <img src="<?= e(vietqr_image_url(100000, 'VPTEST')) ?>" alt="VietQR" class="img-fluid" style="max-width:220px">
                <p class="small text-muted mt-2 mb-0">Mã QR chuyển khoản 100.000đ tới tài khoản đã cấu hình. Quét bằng app ngân hàng để kiểm tra.</p>
            </div></div>
        </div>
    </div>
    <?php
}

function tab_types(array $errors)
{
    $id = (int) input('id');
    $editing = $id ? fetch_one('SELECT * FROM violation_types WHERE id = ?', [$id]) : null;
    $form = $errors ? $_POST : ($editing ?: ['name' => '', 'vehicle_type' => 'tat_ca', 'fine_amount' => '', 'legal_basis' => '', 'description' => '']);
    $applyTypes = ['tat_ca' => 'Tất cả'] + vehicle_types();
    $types = fetch_all('SELECT vt.*, (SELECT COUNT(*) FROM violations v WHERE v.violation_type_id = vt.id) AS used FROM violation_types vt ORDER BY vt.vehicle_type, vt.name');
    ?>
    <div class="row">
        <div class="col-lg-8"><div class="table-wrap"><table class="table table-hover">
            <thead><tr><th>Lỗi vi phạm</th><th>Áp dụng</th><th class="text-right">Mức phạt</th><th class="text-center">Đã dùng</th><th style="width:100px"></th></tr></thead>
            <tbody>
            <?php foreach ($types as $t): ?>
                <tr class="<?= $id === (int) $t['id'] ? 'table-warning' : '' ?>">
                    <td><?= e($t['name']) ?><br><small class="text-muted"><?= e($t['legal_basis']) ?></small></td>
                    <td><?= e($applyTypes[$t['vehicle_type']]) ?></td>
                    <td class="text-right"><?= money($t['fine_amount']) ?></td>
                    <td class="text-center"><?= $t['used'] ?></td>
                    <td class="actions">
                        <a href="?tab=types&id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-pencil"></i></a>
                        <form method="post" class="d-inline" data-confirm="Xóa loại vi phạm này?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_type"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" <?= $t['used'] ? 'disabled title="Đang được sử dụng"' : '' ?>><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div></div>
        <div class="col-lg-4"><div class="card card-app">
            <div class="card-header"><?= $editing ? 'Sửa loại vi phạm' : 'Thêm loại vi phạm' ?></div>
            <div class="card-body">
                <?= render_errors($errors) ?>
                <form method="post" action="settings.php?tab=types">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_type"><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label>Tên lỗi *</label><textarea name="name" rows="2" class="form-control" required><?= e($form['name']) ?></textarea></div>
                    <div class="form-group"><label>Áp dụng cho</label><select name="vehicle_type" class="form-control">
                        <?php foreach ($applyTypes as $k => $label): ?><option value="<?= $k ?>" <?= $form['vehicle_type'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="form-group"><label>Mức phạt (VNĐ) *</label><input type="number" min="1000" step="1000" name="fine_amount" class="form-control" value="<?= e($form['fine_amount']) ?>" required></div>
                    <div class="form-group"><label>Căn cứ pháp lý</label><input name="legal_basis" class="form-control" value="<?= e($form['legal_basis']) ?>"></div>
                    <div class="form-group"><label>Ghi chú</label><textarea name="description" rows="2" class="form-control"><?= e($form['description']) ?></textarea></div>
                    <button class="btn btn-app"><i class="fa fa-save"></i> Lưu</button>
                    <?php if ($editing): ?><a href="?tab=types" class="btn btn-secondary">Hủy</a><?php endif; ?>
                </form>
            </div>
        </div></div>
    </div>
    <?php
}

function tab_logs()
{
    $pg = paginate((int) fetch_value('SELECT COUNT(*) FROM activity_logs'), 25);
    $logs = fetch_all("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}");
    ?>
    <div class="table-wrap"><table class="table table-sm table-hover">
        <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Chi tiết</th><th>IP</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted py-4">Chưa có hoạt động.</td></tr><?php endif; ?>
        <?php foreach ($logs as $l): ?>
            <tr><td class="text-nowrap"><?= fmt_datetime($l['created_at']) ?></td><td><?= e($l['username'] ?: 'Khách') ?></td><td><?= e($l['action']) ?></td>
                <td><small><?= e($l['detail']) ?></small></td><td><small class="text-muted"><?= e($l['ip_address']) ?></small></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="mt-3"><?= render_pagination($pg) ?></div>
    <?php
}
