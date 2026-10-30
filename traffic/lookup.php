<?php
require_once __DIR__ . '/includes/init.php';

$q = input('q');
$results = [];
$vehicle = null;

if ($q !== '') {
    // Tìm theo mã biên bản trước
    $byCode = find_violation_by_code($q);
    if ($byCode) {
        redirect('violation.php?code=' . urlencode($byCode['code']));
    }
    $vehicle = fetch_one('SELECT * FROM vehicles WHERE ' . sql_plate('plate_number') . ' = ?', [normalize_plate($q)]);
    if ($vehicle) {
        $results = fetch_all('SELECT v.*, vt.name AS type_name FROM violations v JOIN violation_types vt ON vt.id = v.violation_type_id
                              WHERE v.vehicle_id = ? ORDER BY v.violation_time DESC', [$vehicle['id']]);
    }
}

$pageTitle = 'Tra cứu vi phạm';
$activeMenu = 'lookup';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Tra cứu vi phạm giao thông</h1>
        <p>Nhập biển số xe hoặc mã biên bản để kiểm tra</p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <form method="get" class="card card-app">
            <div class="card-body form-row">
                <div class="col-md-9 mb-2 mb-md-0">
                    <input name="q" class="form-control form-control-lg" value="<?= e($q) ?>" placeholder="Biển số (vd: 30A-123.45) hoặc mã biên bản (vd: VP00000001)" required>
                </div>
                <div class="col-md-3"><button class="btn btn-app btn-lg btn-block"><i class="fa fa-search"></i> Tra cứu</button></div>
            </div>
        </form>

        <?php if ($q !== '' && !$vehicle): ?>
            <div class="alert alert-success"><i class="fa fa-check-circle"></i> Không tìm thấy vi phạm nào với từ khóa <b><?= e($q) ?></b>.</div>
        <?php elseif ($vehicle): ?>
            <div class="card card-app">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <span>Kết quả cho xe <span class="plate"><?= e($vehicle['plate_number']) ?></span></span>
                    <small class="text-muted"><?= vehicle_type_label($vehicle['vehicle_type']) ?> · <?= e($vehicle['brand']) ?> · <?= e($vehicle['color']) ?></small>
                </div>
                <?php if (!$results): ?>
                    <div class="card-body"><div class="alert alert-success mb-0">Phương tiện này chưa có vi phạm nào.</div></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Mã biên bản</th><th>Lỗi vi phạm</th><th>Thời gian / Địa điểm</th><th>Hạn nộp</th><th class="text-right">Phải nộp</th><th>Trạng thái</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($results as $v): $f = calc_fine($v); ?>
                                <tr>
                                    <td><b><?= e($v['code']) ?></b></td>
                                    <td><?= e($v['type_name']) ?></td>
                                    <td><?= fmt_datetime($v['violation_time']) ?><br><small class="text-muted"><?= e($v['location']) ?></small></td>
                                    <td><?= fmt_date($v['due_date']) ?>
                                        <?php if ($f['overdue']): ?><br><small class="text-danger">Quá hạn <?= $f['late_days'] ?> ngày</small>
                                        <?php elseif ($v['status'] === 'chua_nop'): ?><br><small class="text-muted">Còn <?= $f['days_left'] ?> ngày</small><?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <?php if (in_array($v['status'], ['chua_nop', 'khieu_nai'], true)): ?>
                                            <b><?= money($f['total']) ?></b>
                                            <?php if ($f['late_fee']): ?><br><small class="text-danger">gồm <?= money($f['late_fee']) ?> chậm nộp</small><?php endif; ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td><?= badge($v['status'], violation_statuses()) ?></td>
                                    <td class="text-nowrap">
                                        <a href="violation.php?code=<?= urlencode($v['code']) ?>" class="btn btn-sm btn-outline-dark">Chi tiết</a>
                                        <?php if ($v['status'] === 'chua_nop'): ?>
                                            <a href="pay.php?code=<?= urlencode($v['code']) ?>" class="btn btn-sm btn-app"><i class="fa fa-qrcode"></i> Nộp phạt</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
