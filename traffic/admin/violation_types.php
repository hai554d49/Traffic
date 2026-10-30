<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$id = (int) input('id');
$editing = $id ? fetch_one('SELECT * FROM violation_types WHERE id = ?', [$id]) : null;
$form = $editing ?: ['name' => '', 'vehicle_type' => 'tat_ca', 'fine_amount' => '', 'legal_basis' => '', 'description' => ''];
$applyTypes = ['tat_ca' => 'Tất cả'] + vehicle_types();
$errors = [];

if (is_post()) {
    verify_csrf();
    if (input('action') === 'delete') {
        $t = fetch_one('SELECT * FROM violation_types WHERE id = ?', [(int) input('delete_id')]);
        if ($t && fetch_value('SELECT COUNT(*) FROM violations WHERE violation_type_id = ?', [$t['id']])) {
            flash('warning', 'Không thể xóa vì lỗi này đã được sử dụng trong biên bản.');
        } elseif ($t) {
            query('DELETE FROM violation_types WHERE id = ?', [$t['id']]);
            log_activity('Xóa loại vi phạm', $t['name']);
            flash('success', 'Đã xóa loại vi phạm.');
        }
        redirect('admin/violation_types.php');
    }

    $form = [
        'name'         => input('name'),
        'vehicle_type' => input('vehicle_type'),
        'fine_amount'  => (int) input('fine_amount'),
        'legal_basis'  => input('legal_basis'),
        'description'  => input('description'),
    ];
    if ($form['name'] === '') {
        $errors[] = 'Vui lòng nhập tên lỗi vi phạm.';
    }
    if (!isset($applyTypes[$form['vehicle_type']])) {
        $errors[] = 'Loại phương tiện không hợp lệ.';
    }
    if ($form['fine_amount'] <= 0) {
        $errors[] = 'Mức phạt phải lớn hơn 0.';
    }
    if (!$errors) {
        $values = [$form['name'], $form['vehicle_type'], $form['fine_amount'], $form['legal_basis'] ?: null, $form['description'] ?: null];
        if ($editing) {
            query('UPDATE violation_types SET name = ?, vehicle_type = ?, fine_amount = ?, legal_basis = ?, description = ? WHERE id = ?', array_merge($values, [$id]));
            flash('success', 'Đã cập nhật loại vi phạm.');
        } else {
            query('INSERT INTO violation_types (name, vehicle_type, fine_amount, legal_basis, description) VALUES (?, ?, ?, ?, ?)', $values);
            flash('success', 'Đã thêm loại vi phạm.');
        }
        log_activity($editing ? 'Sửa loại vi phạm' : 'Thêm loại vi phạm', $form['name']);
        redirect('admin/violation_types.php');
    }
}

$types = fetch_all('SELECT vt.*, (SELECT COUNT(*) FROM violations v WHERE v.violation_type_id = vt.id) AS used FROM violation_types vt ORDER BY vt.vehicle_type, vt.name');

$pageTitle = 'Danh mục lỗi vi phạm';
$activeMenu = 'types';
require __DIR__ . '/includes/header.php';
?>
<div class="row">
    <div class="col-lg-8">
        <div class="table-wrap">
            <table class="table table-hover">
                <thead><tr><th>Lỗi vi phạm</th><th>Áp dụng</th><th class="text-right">Mức phạt</th><th class="text-center">Đã dùng</th><th style="width:100px"></th></tr></thead>
                <tbody>
                <?php foreach ($types as $t): ?>
                    <tr class="<?= $editing && (int) $editing['id'] === (int) $t['id'] ? 'table-warning' : '' ?>">
                        <td><?= e($t['name']) ?><br><small class="text-muted"><?= e($t['legal_basis']) ?></small></td>
                        <td><?= e($applyTypes[$t['vehicle_type']]) ?></td>
                        <td class="text-right"><?= money($t['fine_amount']) ?></td>
                        <td class="text-center"><?= $t['used'] ?></td>
                        <td class="actions">
                            <a href="?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-pencil"></i></a>
                            <form method="post" class="d-inline" data-confirm="Xóa loại vi phạm này?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" <?= $t['used'] ? 'disabled title="Đang được sử dụng"' : '' ?>><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-app">
            <div class="card-header"><?= $editing ? 'Sửa loại vi phạm' : 'Thêm loại vi phạm' ?></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-group"><label>Tên lỗi *</label><textarea name="name" rows="2" class="form-control" required><?= e($form['name']) ?></textarea></div>
                    <div class="form-group"><label>Áp dụng cho</label>
                        <select name="vehicle_type" class="form-control">
                            <?php foreach ($applyTypes as $k => $label): ?>
                                <option value="<?= $k ?>" <?= $form['vehicle_type'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Mức phạt (VNĐ) *</label><input type="number" min="1000" step="1000" name="fine_amount" class="form-control" value="<?= e($form['fine_amount']) ?>" required></div>
                    <div class="form-group"><label>Căn cứ pháp lý</label><input name="legal_basis" class="form-control" value="<?= e($form['legal_basis']) ?>"></div>
                    <div class="form-group"><label>Ghi chú</label><textarea name="description" rows="2" class="form-control"><?= e($form['description']) ?></textarea></div>
                    <button class="btn btn-app"><i class="fa fa-save"></i> Lưu</button>
                    <?php if ($editing): ?><a href="violation_types.php" class="btn btn-secondary">Hủy</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
