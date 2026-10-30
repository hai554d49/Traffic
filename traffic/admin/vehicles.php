<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

if (is_post() && input('action') === 'delete') {
    verify_csrf();
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

$where = ['1=1'];
$params = [];
$keyword = input('q');
$type = input('type');
if ($keyword !== '') {
    $where[] = '(' . sql_plate('vh.plate_number') . ' LIKE ? OR vh.owner_name LIKE ? OR vh.owner_cccd LIKE ? OR vh.owner_phone LIKE ?)';
    $like = '%' . $keyword . '%';
    array_push($params, '%' . normalize_plate($keyword) . '%', $like, $like, $like);
}
if (isset(vehicle_types()[$type])) {
    $where[] = 'vh.vehicle_type = ?';
    $params[] = $type;
}
$whereSql = implode(' AND ', $where);
$pg = paginate((int) fetch_value("SELECT COUNT(*) FROM vehicles vh WHERE $whereSql", $params), 10);
$vehicles = fetch_all("SELECT vh.*,
        (SELECT COUNT(*) FROM violations v WHERE v.vehicle_id = vh.id) AS total_violations,
        (SELECT COUNT(*) FROM violations v WHERE v.vehicle_id = vh.id AND v.status = 'chua_nop') AS unpaid
    FROM vehicles vh WHERE $whereSql ORDER BY vh.created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$pageTitle = 'Quản lý phương tiện';
$activeMenu = 'vehicles';
require __DIR__ . '/includes/header.php';
?>
<form class="filter-bar form-row align-items-end" method="get">
    <div class="col-md-5 mb-2">
        <label class="small mb-1">Từ khóa</label>
        <input name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Biển số, chủ xe, CCCD, số điện thoại">
    </div>
    <div class="col-md-3 mb-2">
        <label class="small mb-1">Loại xe</label>
        <select name="type" class="form-control">
            <option value="">Tất cả</option>
            <?php foreach (vehicle_types() as $k => $label): ?>
                <option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 mb-2">
        <button class="btn btn-dark"><i class="fa fa-search"></i> Lọc</button>
        <a href="vehicle_form.php" class="btn btn-app"><i class="fa fa-plus"></i> Thêm phương tiện</a>
    </div>
</form>

<div class="table-wrap">
    <table class="table table-hover">
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
                <td class="text-center">
                    <a href="violations.php?q=<?= urlencode($vh['plate_number']) ?>"><?= $vh['total_violations'] ?></a>
                    <?php if ($vh['unpaid']): ?><span class="badge badge-warning"><?= $vh['unpaid'] ?> chưa nộp</span><?php endif; ?>
                </td>
                <td class="text-center actions">
                    <a href="violation_form.php?vehicle_id=<?= $vh['id'] ?>" class="btn btn-sm btn-outline-warning" title="Lập biên bản"><i class="fa fa-file-text"></i></a>
                    <a href="vehicle_form.php?id=<?= $vh['id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="fa fa-pencil"></i></a>
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
    </table>
</div>
<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-muted">Tổng: <?= $pg['total'] ?> phương tiện</small>
    <?= render_pagination($pg) ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
