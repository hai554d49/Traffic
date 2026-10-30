<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

$stats = fetch_one("SELECT
        COUNT(*) AS total,
        SUM(status = 'chua_nop') AS unpaid,
        SUM(status = 'chua_nop' AND due_date < CURDATE()) AS overdue,
        SUM(status = 'da_nop') AS paid,
        SUM(status = 'khieu_nai') AS disputed
    FROM violations");
$revenue = (int) fetch_value("SELECT COALESCE(SUM(total_amount), 0) FROM payments WHERE status = 'thanh_cong'");
$lateRevenue = (int) fetch_value("SELECT COALESCE(SUM(late_fee), 0) FROM payments WHERE status = 'thanh_cong'");
$vehicleCount = (int) fetch_value('SELECT COUNT(*) FROM vehicles');
$pendingComplaints = (int) fetch_value("SELECT COUNT(*) FROM complaints WHERE status = 'cho_xu_ly'");

// Tổng tiền phải thu (gồm lãi chậm nộp tính đến hôm nay) của các vi phạm chưa nộp
$receivable = 0;
foreach (fetch_all("SELECT fine_amount, due_date, status FROM violations WHERE status = 'chua_nop'") as $v) {
    $receivable += calc_fine($v)['total'];
}

// Doanh thu 6 tháng gần nhất
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
$rows = fetch_all("SELECT DATE_FORMAT(paid_at, '%Y-%m') AS m, SUM(total_amount) AS s FROM payments
                   WHERE status = 'thanh_cong' AND paid_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY m");
foreach ($rows as $r) {
    if (isset($months[$r['m']])) {
        $months[$r['m']] = (int) $r['s'];
    }
}

$byType = fetch_all('SELECT vt.name, vt.vehicle_type, COUNT(*) AS c FROM violations v JOIN violation_types vt ON vt.id = v.violation_type_id
                     GROUP BY vt.id ORDER BY c DESC LIMIT 6');

$recent = fetch_all('SELECT v.*, vh.plate_number, vt.name AS type_name FROM violations v
                     JOIN vehicles vh ON vh.id = v.vehicle_id JOIN violation_types vt ON vt.id = v.violation_type_id
                     ORDER BY v.created_at DESC LIMIT 6');

$pageTitle = 'Tổng quan';
$activeMenu = 'dashboard';
require __DIR__ . '/includes/header.php';

$kpis = [
    ['fa-file-text', '#f9b116', number_format($stats['total']), 'Tổng số vi phạm'],
    ['fa-clock-o', '#e0a800', number_format($stats['unpaid']), 'Chưa nộp phạt'],
    ['fa-exclamation-triangle', '#dc3545', number_format($stats['overdue']), 'Quá hạn nộp'],
    ['fa-comments', '#17a2b8', number_format($pendingComplaints), 'Khiếu nại chờ xử lý'],
    ['fa-money', '#28a745', money($revenue), 'Đã thu (gồm ' . money($lateRevenue) . ' chậm nộp)'],
    ['fa-hourglass-half', '#6f42c1', money($receivable), 'Còn phải thu (tính đến hôm nay)'],
    ['fa-car', '#252525', number_format($vehicleCount), 'Phương tiện quản lý'],
    ['fa-check-circle', '#20c997', number_format($stats['paid']), 'Đã nộp phạt'],
];
?>
<div class="row">
    <?php foreach ($kpis as [$icon, $color, $value, $label]): ?>
        <div class="col-xl-3 col-md-6">
            <div class="kpi">
                <div class="icon" style="background: <?= $color ?>"><i class="fa <?= $icon ?>"></i></div>
                <div><div class="value"><?= $value ?></div><div class="label"><?= e($label) ?></div></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card card-app">
            <div class="card-header">Tiền phạt thu được 6 tháng gần nhất</div>
            <div class="card-body"><canvas id="revenueChart" height="140"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-app">
            <div class="card-header">Lỗi vi phạm phổ biến</div>
            <div class="card-body"><canvas id="typeChart" height="200"></canvas></div>
        </div>
    </div>
</div>

<div class="card card-app">
    <div class="card-header d-flex justify-content-between align-items-center">
        Vi phạm mới ghi nhận
        <a href="violation_form.php" class="btn btn-sm btn-app"><i class="fa fa-plus"></i> Lập biên bản</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Mã</th><th>Biển số</th><th>Lỗi vi phạm</th><th>Thời gian</th><th class="text-right">Phải nộp</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $v): $f = calc_fine($v); ?>
                <tr>
                    <td><a href="violation_form.php?id=<?= $v['id'] ?>"><?= e($v['code']) ?></a></td>
                    <td><span class="plate"><?= e($v['plate_number']) ?></span></td>
                    <td><?= e($v['type_name']) ?></td>
                    <td><?= fmt_datetime($v['violation_time']) ?></td>
                    <td class="text-right"><?= money($f['total']) ?></td>
                    <td><?= badge($v['status'], violation_statuses()) ?> <?php if ($f['overdue']): ?><span class="badge badge-danger">Quá hạn</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$monthLabels = array_map(function ($m) { return 'T' . (int) substr($m, 5) . '/' . substr($m, 0, 4); }, array_keys($months));
$typeLabels = array_map(function ($t) { return mb_strimwidth($t['name'], 0, 40, '…') . ' (' . vehicle_type_label($t['vehicle_type']) . ')'; }, $byType);
ob_start();
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{ label: 'Tiền phạt (VNĐ)', data: <?= json_encode(array_values($months)) ?>, backgroundColor: '#f9b116', borderRadius: 4 }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: function (v) { return v.toLocaleString('vi-VN'); } } } }
        }
    });
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($typeLabels) ?>,
            datasets: [{ data: <?= json_encode(array_map('intval', array_column($byType, 'c'))) ?>,
                backgroundColor: ['#f9b116', '#252525', '#dc3545', '#17a2b8', '#28a745', '#6f42c1'] }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
    });
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
