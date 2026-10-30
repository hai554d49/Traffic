<?php
/**
 * Danh sách xe đứng tên người dùng (liên kết qua số CCCD) và các vi phạm của từng xe.
 */
require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$vehicles = $user['cccd'] ? fetch_all('SELECT * FROM vehicles WHERE owner_cccd = ? ORDER BY plate_number', [$user['cccd']]) : [];

$violationsByVehicle = [];
$totalDue = 0;
if ($vehicles) {
    $ids = array_column($vehicles, 'id');
    $rows = fetch_all('SELECT v.*, vt.name AS type_name FROM violations v JOIN violation_types vt ON vt.id = v.violation_type_id
                       WHERE v.vehicle_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY v.violation_time DESC', $ids);
    foreach ($rows as $r) {
        $r['calc'] = calc_fine($r);
        if ($r['status'] === 'chua_nop') {
            $totalDue += $r['calc']['total'];
        }
        $violationsByVehicle[$r['vehicle_id']][] = $r;
    }
}

$pageTitle = 'Xe của tôi';
$activeMenu = 'vehicles';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Phương tiện của tôi</h1>
        <p>Các xe đứng tên CCCD <?= e($user['cccd'] ?: '(chưa cập nhật)') ?></p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <?php if (!$vehicles): ?>
            <div class="alert alert-light border">Chưa có phương tiện nào đăng ký với số CCCD của bạn. Bạn vẫn có thể <a href="lookup.php" class="text-app">tra cứu theo biển số</a>.</div>
        <?php else: ?>
            <?php if ($totalDue): ?>
                <div class="alert alert-warning"><i class="fa fa-exclamation-circle"></i> Tổng số tiền phạt chưa nộp (tính đến hôm nay): <b><?= money($totalDue) ?></b></div>
            <?php endif; ?>
            <?php foreach ($vehicles as $vh): $list = $violationsByVehicle[$vh['id']] ?? []; ?>
                <div class="card card-app">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                        <span><span class="plate"><?= e($vh['plate_number']) ?></span> <?= vehicle_type_label($vh['vehicle_type']) ?> · <?= e($vh['brand']) ?> · <?= e($vh['color']) ?></span>
                        <small class="text-muted"><?= count($list) ?> vi phạm</small>
                    </div>
                    <?php if (!$list): ?>
                        <div class="card-body text-success"><i class="fa fa-check-circle"></i> Không có vi phạm.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Mã</th><th>Lỗi</th><th>Thời gian</th><th>Hạn nộp</th><th class="text-right">Phải nộp</th><th>Trạng thái</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($list as $v): $f = $v['calc']; ?>
                                    <tr>
                                        <td><?= e($v['code']) ?></td>
                                        <td><?= e($v['type_name']) ?></td>
                                        <td><?= fmt_datetime($v['violation_time']) ?></td>
                                        <td><?= fmt_date($v['due_date']) ?><?= $f['overdue'] ? '<br><small class="text-danger">Quá ' . $f['late_days'] . ' ngày</small>' : '' ?></td>
                                        <td class="text-right"><?= in_array($v['status'], ['chua_nop', 'khieu_nai'], true) ? money($f['total']) : '-' ?></td>
                                        <td><?= badge($v['status'], violation_statuses()) ?></td>
                                        <td class="text-nowrap">
                                            <a href="violation.php?code=<?= urlencode($v['code']) ?>" class="btn btn-sm btn-outline-dark">Chi tiết</a>
                                            <?php if ($v['status'] === 'chua_nop'): ?>
                                                <a href="pay.php?code=<?= urlencode($v['code']) ?>" class="btn btn-sm btn-app">Nộp phạt</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
