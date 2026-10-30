<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

$id = (int) input('id');
$vehicle = $id ? fetch_one('SELECT * FROM vehicles WHERE id = ?', [$id]) : null;
if ($id && !$vehicle) {
    flash('danger', 'Không tìm thấy phương tiện.');
    redirect('admin/vehicles.php');
}

$fields = ['plate_number', 'vehicle_type', 'brand', 'color', 'owner_name', 'owner_cccd', 'owner_phone', 'owner_address'];
$form = $vehicle ?: array_fill_keys($fields, '');
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach ($fields as $f) {
        $form[$f] = input($f);
    }
    $form['plate_number'] = strtoupper($form['plate_number']);

    // Biển số VN: 2 số tỉnh + seri (1-2 chữ, có thể kèm số) + 4-5 số. Vd: 30A-123.45, 29B1-567.89
    if (!preg_match('/^\d{2}[A-Z]{1,2}\d?-?\d{3}\.?\d{2}$|^\d{2}[A-Z]{1,2}\d?-?\d{4}$/', $form['plate_number'])) {
        $errors[] = 'Biển số không đúng định dạng (vd: 30A-123.45, 29B1-567.89).';
    } elseif (fetch_value('SELECT COUNT(*) FROM vehicles WHERE ' . sql_plate('plate_number') . ' = ? AND id <> ?', [normalize_plate($form['plate_number']), $id])) {
        $errors[] = 'Biển số đã tồn tại trong hệ thống.';
    }
    if (!isset(vehicle_types()[$form['vehicle_type']])) {
        $errors[] = 'Vui lòng chọn loại xe.';
    }
    if ($form['owner_name'] === '') {
        $errors[] = 'Vui lòng nhập tên chủ xe.';
    }
    if ($form['owner_cccd'] !== '' && !preg_match('/^\d{12}$/', $form['owner_cccd'])) {
        $errors[] = 'Số CCCD phải gồm 12 chữ số.';
    }
    if ($form['owner_phone'] !== '' && !preg_match('/^0\d{9,10}$/', $form['owner_phone'])) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }

    if (!$errors) {
        $values = [];
        foreach ($fields as $f) {
            $values[] = $form[$f] === '' ? null : $form[$f];
        }
        if ($vehicle) {
            $values[] = $id;
            query('UPDATE vehicles SET ' . implode(' = ?, ', $fields) . ' = ? WHERE id = ?', $values);
            log_activity('Sửa phương tiện', $form['plate_number']);
            flash('success', 'Đã cập nhật phương tiện ' . e($form['plate_number']) . '.');
        } else {
            query('INSERT INTO vehicles (' . implode(', ', $fields) . ') VALUES (' . rtrim(str_repeat('?, ', count($fields)), ', ') . ')', $values);
            log_activity('Thêm phương tiện', $form['plate_number']);
            flash('success', 'Đã thêm phương tiện ' . e($form['plate_number']) . '.');
        }
        redirect('admin/vehicles.php');
    }
}

$pageTitle = $vehicle ? 'Sửa phương tiện' : 'Thêm phương tiện';
$activeMenu = 'vehicles';
require __DIR__ . '/includes/header.php';
?>
<div class="card card-app" style="max-width: 900px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <h6 class="font-weight-bold text-app">Thông tin phương tiện</h6>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Biển số *</label>
                    <input name="plate_number" class="form-control text-uppercase" value="<?= e($form['plate_number']) ?>" placeholder="30A-123.45" required>
                </div>
                <div class="form-group col-md-3">
                    <label>Loại xe *</label>
                    <select name="vehicle_type" class="form-control" required>
                        <?php foreach (vehicle_types() as $k => $label): ?>
                            <option value="<?= $k ?>" <?= $form['vehicle_type'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>Hãng / Dòng xe</label>
                    <input name="brand" class="form-control" value="<?= e($form['brand']) ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Màu sơn</label>
                    <input name="color" class="form-control" value="<?= e($form['color']) ?>">
                </div>
            </div>
            <h6 class="font-weight-bold text-app mt-2">Chủ sở hữu</h6>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Họ tên *</label>
                    <input name="owner_name" class="form-control" value="<?= e($form['owner_name']) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Số CCCD</label>
                    <input name="owner_cccd" class="form-control" maxlength="12" value="<?= e($form['owner_cccd']) ?>">
                    <small class="text-muted">Người dân đăng ký cùng CCCD sẽ thấy xe này</small>
                </div>
                <div class="form-group col-md-4">
                    <label>Số điện thoại</label>
                    <input name="owner_phone" class="form-control" value="<?= e($form['owner_phone']) ?>">
                </div>
                <div class="form-group col-md-12">
                    <label>Địa chỉ</label>
                    <input name="owner_address" class="form-control" value="<?= e($form['owner_address']) ?>">
                </div>
            </div>
            <button class="btn btn-app"><i class="fa fa-save"></i> Lưu</button>
            <a href="vehicles.php" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
