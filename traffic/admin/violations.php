<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

/* ---------- Xử lý thao tác ---------- */
if (is_post()) {
    verify_csrf();
    $id = (int) input('id');
    $v = fetch_one('SELECT * FROM violations WHERE id = ?', [$id]);
    if (!$v) {
        flash('danger', 'Không tìm thấy vi phạm.');
        redirect('admin/violations.php');
    }

    if (input('action') === 'delete') {
        if (!is_admin()) {
            flash('danger', 'Chỉ quản trị viên được xóa biên bản.');
        } else {
            query('DELETE FROM violations WHERE id = ?', [$id]);
            if ($v['evidence_image'] && is_file(dirname(__DIR__) . '/' . $v['evidence_image'])) {
                @unlink(dirname(__DIR__) . '/' . $v['evidence_image']);
            }
            log_activity('Xóa vi phạm', $v['code']);
            flash('success', 'Đã xóa biên bản ' . e($v['code']) . '.');
        }
    } elseif (input('action') === 'cash') {
        // Ghi nhận nộp tiền mặt tại trụ sở
        if ($v['status'] !== 'chua_nop') {
            flash('warning', 'Biên bản không ở trạng thái chờ nộp phạt.');
        } else {
            $f = calc_fine($v);
            query("INSERT INTO payments (transaction_code, violation_id, fine_amount, late_days, late_fee, total_amount, method, status, note)
                   VALUES (?, ?, ?, ?, ?, ?, 'tien_mat', 'cho_xac_nhan', 'Nộp trực tiếp tại trụ sở')",
                [generate_code('GD', 'payments', 'transaction_code'), $id, $f['fine'], $f['late_days'], $f['late_fee'], $f['total']]);
            complete_payment(db()->lastInsertId(), current_user()['id']);
            flash('success', 'Đã ghi nhận nộp tiền mặt ' . money($f['total']) . ' cho biên bản ' . e($v['code']) . '.');
        }
    }
    redirect('admin/violations.php?' . http_build_query(array_diff_key($_GET, ['page' => 0])));
}

/* ---------- Bộ lọc ---------- */
$where = ['1=1'];
$params = [];
$keyword = input('q');
$status = input('status');
$from = input('from');
$to = input('to');

if ($keyword !== '') {
    $where[] = '(v.code LIKE ? OR ' . sql_plate('vh.plate_number') . ' LIKE ? OR vh.owner_name LIKE ? OR v.location LIKE ?)';
    $like = '%' . $keyword . '%';
    array_push($params, $like, '%' . normalize_plate($keyword) . '%', $like, $like);
}
if ($status === 'qua_han') {
    $where[] = "v.status = 'chua_nop' AND v.due_date < CURDATE()";
} elseif (isset(violation_statuses()[$status])) {
    $where[] = 'v.status = ?';
    $params[] = $status;
}
if ($from !== '') {
    $where[] = 'DATE(v.violation_time) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(v.violation_time) <= ?';
    $params[] = $to;
}

$baseSql = 'FROM violations v JOIN vehicles vh ON vh.id = v.vehicle_id JOIN violation_types vt ON vt.id = v.violation_type_id
            WHERE ' . implode(' AND ', $where);
$pg = paginate((int) fetch_value("SELECT COUNT(*) $baseSql", $params), 10);
$violations = fetch_all("SELECT v.*, vh.plate_number, vh.owner_name, vt.name AS type_name $baseSql
                         ORDER BY v.violation_time DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$pageTitle = 'Quản lý vi phạm';
$activeMenu = 'violations';
require __DIR__ . '/includes/header.php';
?>
<form class="filter-bar form-row align-items-end" method="get">
    <div class="col-md-3 mb-2">
        <label class="small mb-1">Từ khóa</label>
        <input name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Mã, biển số, chủ xe, địa điểm">
    </div>
    <div class="col-md-2 mb-2">
        <label class="small mb-1">Trạng thái</label>
        <select name="status" class="form-control">
            <option value="">Tất cả</option>
            <?php foreach (violation_statuses() as $k => $s): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $s[0] ?></option>
            <?php endforeach; ?>
            <option value="qua_han" <?= $status === 'qua_han' ? 'selected' : '' ?>>Quá hạn nộp</option>
        </select>
    </div>
    <div class="col-md-2 mb-2">
        <label class="small mb-1">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="col-md-2 mb-2">
        <label class="small mb-1">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <div class="col-md-3 mb-2">
        <button class="btn btn-dark"><i class="fa fa-search"></i> Lọc</button>
        <a href="export.php?<?= e(http_build_query($_GET)) ?>" class="btn btn-outline-success"><i class="fa fa-file-excel-o"></i> Xuất CSV</a>
        <a href="violation_form.php" class="btn btn-app"><i class="fa fa-plus"></i> Thêm</a>
    </div>
</form>

<div class="table-wrap">
    <table class="table table-hover">
        <thead>
        <tr><th>Mã biên bản</th><th>Biển số / Chủ xe</th><th>Lỗi vi phạm</th><th>Thời gian</th><th>Hạn nộp</th>
            <th class="text-right">Phải nộp</th><th>Trạng thái</th><th class="text-center" style="width:170px">Thao tác</th></tr>
        </thead>
        <tbody>
        <?php if (!$violations): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Không có dữ liệu.</td></tr>
        <?php endif; ?>
        <?php foreach ($violations as $v): $f = calc_fine($v); ?>
            <tr>
                <td><b><?= e($v['code']) ?></b></td>
                <td><span class="plate"><?= e($v['plate_number']) ?></span><br><small class="text-muted"><?= e($v['owner_name']) ?></small></td>
                <td style="max-width:260px"><?= e($v['type_name']) ?><br><small class="text-muted"><?= e($v['location']) ?></small></td>
                <td><?= fmt_datetime($v['violation_time']) ?></td>
                <td><?= fmt_date($v['due_date']) ?>
                    <?php if ($f['overdue']): ?><br><small class="text-danger">Quá <?= $f['late_days'] ?> ngày</small><?php endif; ?></td>
                <td class="text-right"><?= money($f['total']) ?>
                    <?php if ($f['late_fee']): ?><br><small class="text-danger">+<?= money($f['late_fee']) ?> lãi</small><?php endif; ?></td>
                <td><?= badge($v['status'], violation_statuses()) ?></td>
                <td class="text-center actions">
                    <a href="<?= url('violation.php?code=' . urlencode($v['code'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem"><i class="fa fa-eye"></i></a>
                    <a href="violation_form.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="fa fa-pencil"></i></a>
                    <?php if ($v['status'] === 'chua_nop'): ?>
                        <form method="post" class="d-inline" data-confirm="Xác nhận đã thu tiền mặt <?= money($f['total']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="action" value="cash">
                            <button class="btn btn-sm btn-outline-success" title="Thu tiền mặt"><i class="fa fa-money"></i></button>
                        </form>
                    <?php endif; ?>
                    <?php if (is_admin()): ?>
                        <form method="post" class="d-inline" data-confirm="Xóa biên bản <?= e($v['code']) ?>? Các khiếu nại và giao dịch liên quan cũng bị xóa.">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="action" value="delete">
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
    <small class="text-muted">Tổng: <?= $pg['total'] ?> biên bản</small>
    <?= render_pagination($pg) ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
