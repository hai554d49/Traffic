<?php
/**
 * QUẢN LÝ PHƯƠNG TIỆN (thêm / sửa / xóa)
 *   GET  vehicles.php           -> show_list()      danh sách + tìm kiếm + phân trang
 *   GET  vehicles.php?new=1     -> show_form()      form trống (thêm)
 *   GET  vehicles.php?id=5      -> show_form()      form có sẵn dữ liệu xe id 5 (sửa)
 *   POST action=save            -> save_vehicle()   kiểm tra -> INSERT hoặc UPDATE -> về danh sách
 *   POST action=delete          -> delete_vehicle() kiểm tra quyền & ràng buộc -> DELETE
 */
require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../layout.php';
require_staff();

// Các cột người dùng được nhập (cố định trong code, không lấy từ người dùng)
const VEHICLE_FIELDS = ['plate_number', 'vehicle_type', 'brand', 'color', 'owner_name', 'owner_cccd', 'owner_phone', 'owner_address'];

$errors = [];
if (is_post()) {
    verify_csrf();
    if (input('action') === 'save') $errors = save_vehicle();   // thành công thì redirect, chỉ quay lại khi có lỗi
    if (input('action') === 'delete') delete_vehicle();
}

if ($errors || input('new') !== '' || input('id') !== '') {
    show_form($errors);
} else {
    show_list();
}

/* ======================= XỬ LÝ ======================= */

/** Đọc form -> kiểm tra hợp lệ -> INSERT (thêm) hoặc UPDATE (sửa) -> ghi nhật ký -> redirect */
function save_vehicle()
{
    $id = (int) input('id');                       // 0 = thêm mới, > 0 = sửa
    $data = [];
    foreach (VEHICLE_FIELDS as $f) {
        $data[$f] = input($f);
    }
    $data['plate_number'] = strtoupper($data['plate_number']);

    $errors = validate_vehicle($data, $id);
    if ($errors) {
        return $errors;
    }

    $values = array_map(function ($v) { return $v === '' ? null : $v; }, array_values($data));
    if ($id) {
        query('UPDATE vehicles SET plate_number = ?, vehicle_type = ?, brand = ?, color = ?, owner_name = ?, owner_cccd = ?,
               owner_phone = ?, owner_address = ? WHERE id = ?', array_merge($values, [$id]));
        log_activity('Sửa phương tiện', $data['plate_number']);
        flash('success', 'Đã cập nhật phương tiện ' . e($data['plate_number']) . '.');
    } else {
        query('INSERT INTO vehicles (plate_number, vehicle_type, brand, color, owner_name, owner_cccd, owner_phone, owner_address)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $values);
        log_activity('Thêm phương tiện', $data['plate_number']);
        flash('success', 'Đã thêm phương tiện ' . e($data['plate_number']) . '.');
    }
    redirect('admin/vehicles.php');
}

/** Quy tắc kiểm tra dữ liệu xe; trả về mảng câu báo lỗi (rỗng = hợp lệ) */
function validate_vehicle(array $d, $id)
{
    $errors = [];
    // Biển số VN: 2 số tỉnh + seri 1-2 chữ (có thể kèm 1 số) + 4-5 số. Vd 30A-123.45, 29B1-567.89, 30A-1234
    if (!preg_match('/^\d{2}[A-Z]{1,2}\d?-?(\d{3}\.?\d{2}|\d{4})$/', $d['plate_number'])) {
        $errors[] = 'Biển số không đúng định dạng (vd: 30A-123.45, 29B1-567.89).';
    } elseif (fetch_value('SELECT COUNT(*) FROM vehicles WHERE ' . sql_plate('plate_number') . ' = ? AND id <> ?', [normalize_plate($d['plate_number']), $id])) {
        $errors[] = 'Biển số đã tồn tại trong hệ thống.';
    }
    if (!isset(vehicle_types()[$d['vehicle_type']])) $errors[] = 'Vui lòng chọn loại xe.';
    if ($d['owner_name'] === '') $errors[] = 'Vui lòng nhập tên chủ xe.';
    if ($d['owner_cccd'] !== '' && !preg_match('/^\d{12}$/', $d['owner_cccd'])) $errors[] = 'Số CCCD phải gồm 12 chữ số.';
    if ($d['owner_phone'] !== '' && !preg_match('/^0\d{9,10}$/', $d['owner_phone'])) $errors[] = 'Số điện thoại không hợp lệ.';
    return $errors;
}

/** Chỉ admin; không xóa xe đã có biên bản (khóa ngoại RESTRICT) */
function delete_vehicle()
{
    $vh = fetch_one('SELECT * FROM vehicles WHERE id = ?', [(int) input('id')]);
    if (!is_admin()) {
        flash('danger', 'Chỉ quản trị viên được xóa phương tiện.');
    } elseif (!$vh) {
        flash('danger', 'Không tìm thấy phương tiện.');
    } elseif (fetch_value('SELECT COUNT(*) FROM violations WHERE vehicle_id = ?', [$vh['id']])) {
        flash('warning', 'Không thể xóa xe <b>' . e($vh['plate_number']) . '</b> vì đã có biên bản vi phạm. Hãy xóa các biên bản trước.');
    } else {
        query('DELETE FROM vehicles WHERE id = ?', [$vh['id']]);
        log_activity('Xóa phương tiện', $vh['plate_number']);
        flash('success', 'Đã xóa phương tiện ' . e($vh['plate_number']) . '.');
    }
    redirect('admin/vehicles.php');
}

/* ======================= GIAO DIỆN ======================= */

function show_list()
{
    // Ghép điều kiện lọc: WHERE 1=1 AND ... ; giá trị luôn đi qua dấu ?
    $where = ['1=1'];
    $params = [];
    $keyword = input('q');
    $type = input('type');
    if ($keyword !== '') {
        $where[] = '(' . sql_plate('plate_number') . ' LIKE ? OR owner_name LIKE ? OR owner_cccd LIKE ? OR owner_phone LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, '%' . normalize_plate($keyword) . '%', $like, $like, $like);
    }
    if (isset(vehicle_types()[$type])) {
        $where[] = 'vehicle_type = ?';
        $params[] = $type;
    }
    $whereSql = implode(' AND ', $where);
    $pg = paginate((int) fetch_value("SELECT COUNT(*) FROM vehicles WHERE $whereSql", $params), 10);
    $vehicles = fetch_all("SELECT vh.*,
            (SELECT COUNT(*) FROM violations v WHERE v.vehicle_id = vh.id) AS total_violations,
            (SELECT COUNT(*) FROM violations v WHERE v.vehicle_id = vh.id AND v.status = 'chua_nop') AS unpaid
        FROM vehicles vh WHERE $whereSql ORDER BY vh.created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

    admin_header('Quản lý phương tiện', 'vehicles');
    ?>
    <form class="filter-bar form-row align-items-end" method="get">
        <div class="col-md-5 mb-2"><label class="small mb-1">Từ khóa</label>
            <input name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Biển số, chủ xe, CCCD, số điện thoại"></div>
        <div class="col-md-3 mb-2"><label class="small mb-1">Loại xe</label>
            <select name="type" class="form-control"><option value="">Tất cả</option>
                <?php foreach (vehicle_types() as $k => $label): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4 mb-2">
            <button class="btn btn-dark"><i class="fa fa-search"></i> Lọc</button>
            <a href="vehicles.php?new=1" class="btn btn-app"><i class="fa fa-plus"></i> Thêm phương tiện</a>
        </div>
    </form>
    <div class="table-wrap"><table class="table table-hover">
        <thead><tr><th>Biển số</th><th>Loại / Hãng</th><th>Chủ sở hữu</th><th>CCCD</th><th>Điện thoại</th><th class="text-center">Vi phạm</th><th class="text-center" style="width:150px">Thao tác</th></tr></thead>
        <tbody>
        <?php if (!$vehicles): ?><tr><td colspan="7" class="text-center text-muted py-4">Không có dữ liệu.</td></tr><?php endif; ?>
        <?php foreach ($vehicles as $vh): ?>
            <tr>
                <td><span class="plate"><?= e($vh['plate_number']) ?></span></td>
                <td><?= vehicle_type_label($vh['vehicle_type']) ?><br><small class="text-muted"><?= e($vh['brand']) ?> <?= e($vh['color']) ?></small></td>
                <td><?= e($vh['owner_name']) ?><br><small class="text-muted"><?= e($vh['owner_address']) ?></small></td>
                <td><?= e($vh['owner_cccd']) ?></td>
                <td><?= e($vh['owner_phone']) ?></td>
                <td class="text-center"><a href="violations.php?q=<?= urlencode($vh['plate_number']) ?>"><?= $vh['total_violations'] ?></a>
                    <?php if ($vh['unpaid']): ?><span class="badge badge-warning"><?= $vh['unpaid'] ?> chưa nộp</span><?php endif; ?></td>
                <td class="text-center actions">
                    <a href="violations.php?new=1&vehicle_id=<?= $vh['id'] ?>" class="btn btn-sm btn-outline-warning" title="Lập biên bản"><i class="fa fa-file-text"></i></a>
                    <a href="vehicles.php?id=<?= $vh['id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="fa fa-pencil"></i></a>
                    <?php if (is_admin()): ?>
                        <form method="post" class="d-inline" data-confirm="Xóa phương tiện <?= e($vh['plate_number']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $vh['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fa fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="d-flex justify-content-between align-items-center mt-3"><small class="text-muted">Tổng: <?= $pg['total'] ?> phương tiện</small><?= render_pagination($pg) ?></div>
    <?php
    admin_footer();
}

/** Form thêm/sửa. Có lỗi thì điền lại dữ liệu vừa gõ ($_POST); sửa thì điền dữ liệu cũ từ DB */
function show_form(array $errors)
{
    $id = (int) input('id');
    $vehicle = $id ? fetch_one('SELECT * FROM vehicles WHERE id = ?', [$id]) : null;
    if ($id && !$vehicle) {
        flash('danger', 'Không tìm thấy phương tiện.');
        redirect('admin/vehicles.php');
    }
    $form = $errors ? $_POST : ($vehicle ?: array_fill_keys(VEHICLE_FIELDS, ''));
    $val = function ($f) use ($form) { return e($form[$f] ?? ''); };

    admin_header($vehicle ? 'Sửa phương tiện' : 'Thêm phương tiện', 'vehicles');
    ?>
    <div class="card card-app" style="max-width: 900px">
        <div class="card-header"><?= $vehicle ? 'Sửa phương tiện ' . e($vehicle['plate_number']) : 'Thêm phương tiện' ?></div>
        <div class="card-body">
            <?= render_errors($errors) ?>
            <form method="post" action="vehicles.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $id ?>">
                <h6 class="font-weight-bold text-app">Thông tin phương tiện</h6>
                <div class="form-row">
                    <div class="form-group col-md-3"><label>Biển số *</label><input name="plate_number" class="form-control text-uppercase" value="<?= $val('plate_number') ?>" placeholder="30A-123.45" required></div>
                    <div class="form-group col-md-3"><label>Loại xe *</label>
                        <select name="vehicle_type" class="form-control" required>
                            <?php foreach (vehicle_types() as $k => $label): ?><option value="<?= $k ?>" <?= ($form['vehicle_type'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group col-md-3"><label>Hãng / Dòng xe</label><input name="brand" class="form-control" value="<?= $val('brand') ?>"></div>
                    <div class="form-group col-md-3"><label>Màu sơn</label><input name="color" class="form-control" value="<?= $val('color') ?>"></div>
                </div>
                <h6 class="font-weight-bold text-app mt-2">Chủ sở hữu</h6>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Họ tên *</label><input name="owner_name" class="form-control" value="<?= $val('owner_name') ?>" required></div>
                    <div class="form-group col-md-4"><label>Số CCCD</label><input name="owner_cccd" class="form-control" maxlength="12" value="<?= $val('owner_cccd') ?>">
                        <small class="text-muted">Người dân đăng ký cùng CCCD sẽ thấy xe này</small></div>
                    <div class="form-group col-md-4"><label>Số điện thoại</label><input name="owner_phone" class="form-control" value="<?= $val('owner_phone') ?>"></div>
                    <div class="form-group col-md-12"><label>Địa chỉ</label><input name="owner_address" class="form-control" value="<?= $val('owner_address') ?>"></div>
                </div>
                <button class="btn btn-app"><i class="fa fa-save"></i> Lưu</button>
                <a href="vehicles.php" class="btn btn-secondary">Quay lại</a>
            </form>
        </div>
    </div>
    <?php
    admin_footer();
}
