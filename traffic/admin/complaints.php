<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

$status = input('status', 'cho_xu_ly');
$where = '1=1';
$params = [];
if (isset(complaint_statuses()[$status])) {
    $where = 'c.status = ?';
    $params[] = $status;
}
$pg = paginate((int) fetch_value("SELECT COUNT(*) FROM complaints c WHERE $where", $params), 10);
$complaints = fetch_all("SELECT c.*, v.code AS violation_code, vh.plate_number, vt.name AS type_name
    FROM complaints c
    JOIN violations v ON v.id = c.violation_id
    JOIN vehicles vh ON vh.id = v.vehicle_id
    JOIN violation_types vt ON vt.id = v.violation_type_id
    WHERE $where ORDER BY c.created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
$counts = [];
foreach (fetch_all('SELECT status, COUNT(*) AS c FROM complaints GROUP BY status') as $r) {
    $counts[$r['status']] = $r['c'];
}

$pageTitle = 'Xử lý khiếu nại';
$activeMenu = 'complaints';
require __DIR__ . '/includes/header.php';
?>
<ul class="nav nav-pills mb-3">
    <?php foreach (complaint_statuses() + ['all' => ['Tất cả', '']] as $k => $s): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status === $k ? 'active bg-dark' : 'text-dark' ?>" href="?status=<?= $k ?>"><?= $s[0] ?>
                <?php if (!empty($counts[$k])): ?><span class="badge badge-light"><?= $counts[$k] ?></span><?php endif; ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="table-wrap">
    <table class="table table-hover">
        <thead><tr><th>Mã KN</th><th>Biên bản</th><th>Người khiếu nại</th><th>Nội dung</th><th>Ngày gửi</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php if (!$complaints): ?><tr><td colspan="7" class="text-center text-muted py-4">Không có khiếu nại.</td></tr><?php endif; ?>
        <?php foreach ($complaints as $c): ?>
            <tr>
                <td><b><?= e($c['code']) ?></b></td>
                <td><?= e($c['violation_code']) ?><br><span class="plate"><?= e($c['plate_number']) ?></span></td>
                <td><?= e($c['complainant_name']) ?><br><small class="text-muted"><?= e($c['complainant_phone']) ?></small></td>
                <td style="max-width:320px"><small><?= e(mb_strimwidth($c['content'], 0, 140, '…')) ?></small></td>
                <td><?= fmt_datetime($c['created_at']) ?></td>
                <td><?= badge($c['status'], complaint_statuses()) ?></td>
                <td><a href="complaint_view.php?id=<?= $c['id'] ?>" class="btn btn-sm <?= $c['status'] === 'cho_xu_ly' ? 'btn-app' : 'btn-outline-secondary' ?>">
                        <?= $c['status'] === 'cho_xu_ly' ? 'Xử lý' : 'Xem' ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= render_pagination($pg) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
