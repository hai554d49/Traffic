<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

// Xác nhận / từ chối giao dịch chuyển khoản do người dân báo đã chuyển
if (is_post()) {
    verify_csrf();
    $payment = fetch_one("SELECT * FROM payments WHERE id = ? AND status = 'cho_xac_nhan'", [(int) input('id')]);
    if (!$payment) {
        flash('danger', 'Giao dịch không tồn tại hoặc đã được xử lý.');
    } elseif (input('action') === 'confirm') {
        if (complete_payment($payment['id'], current_user()['id'])) {
            flash('success', 'Đã xác nhận giao dịch ' . e($payment['transaction_code']) . '.');
        } else {
            flash('warning', 'Không thể xác nhận: biên bản không còn ở trạng thái chờ nộp.');
        }
    } elseif (input('action') === 'reject') {
        query("UPDATE payments SET status = 'that_bai', confirmed_by = ?, note = ? WHERE id = ?",
            [current_user()['id'], input('note') ?: 'Không tìm thấy giao dịch chuyển khoản', $payment['id']]);
        log_activity('Từ chối giao dịch', $payment['transaction_code']);
        flash('info', 'Đã từ chối giao dịch ' . e($payment['transaction_code']) . '.');
    }
    redirect('admin/payments.php?' . http_build_query($_GET));
}

$status = input('status');
$method = input('method');
$where = ['1=1'];
$params = [];
if (isset(payment_statuses()[$status])) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if (isset(payment_methods()[$method])) {
    $where[] = 'p.method = ?';
    $params[] = $method;
}
$whereSql = implode(' AND ', $where);
$pg = paginate((int) fetch_value("SELECT COUNT(*) FROM payments p WHERE $whereSql", $params), 15);
$payments = fetch_all("SELECT p.*, v.code AS violation_code, vh.plate_number, u.full_name AS confirmer
    FROM payments p JOIN violations v ON v.id = p.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
    LEFT JOIN users u ON u.id = p.confirmed_by
    WHERE $whereSql ORDER BY p.created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
$sum = fetch_one("SELECT COALESCE(SUM(total_amount),0) AS total, COALESCE(SUM(late_fee),0) AS late FROM payments p WHERE $whereSql AND p.status = 'thanh_cong'", $params);

$pageTitle = 'Quản lý thanh toán';
$activeMenu = 'payments';
require __DIR__ . '/includes/header.php';
?>
<form class="filter-bar form-row align-items-end" method="get">
    <div class="col-md-3 mb-2">
        <label class="small mb-1">Trạng thái</label>
        <select name="status" class="form-control">
            <option value="">Tất cả</option>
            <?php foreach (payment_statuses() as $k => $s): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $s[0] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 mb-2">
        <label class="small mb-1">Hình thức</label>
        <select name="method" class="form-control">
            <option value="">Tất cả</option>
            <?php foreach (payment_methods() as $k => $label): ?>
                <option value="<?= $k ?>" <?= $method === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 mb-2"><button class="btn btn-dark"><i class="fa fa-filter"></i> Lọc</button></div>
    <div class="col-md-4 mb-2 text-md-right">
        <div>Thực thu: <b class="text-success h5"><?= money($sum['total']) ?></b></div>
        <small class="text-muted">trong đó tiền chậm nộp: <?= money($sum['late']) ?></small>
    </div>
</form>

<div class="table-wrap">
    <table class="table table-hover">
        <thead><tr><th>Mã GD</th><th>Biên bản</th><th class="text-right">Tiền phạt</th><th class="text-right">Chậm nộp</th><th class="text-right">Tổng</th>
            <th>Hình thức</th><th>Thời gian</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="9" class="text-center text-muted py-4">Không có giao dịch.</td></tr><?php endif; ?>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><b><?= e($p['transaction_code']) ?></b></td>
                <td><?= e($p['violation_code']) ?><br><span class="plate"><?= e($p['plate_number']) ?></span></td>
                <td class="text-right"><?= money($p['fine_amount']) ?></td>
                <td class="text-right"><?= $p['late_fee'] ? '<span class="text-danger">' . money($p['late_fee']) . '</span><br><small>' . $p['late_days'] . ' ngày</small>' : '-' ?></td>
                <td class="text-right font-weight-bold"><?= money($p['total_amount']) ?></td>
                <td><small><?= e(payment_methods()[$p['method']]) ?></small></td>
                <td><small><?= fmt_datetime($p['paid_at'] ?: $p['created_at']) ?></small></td>
                <td><?= badge($p['status'], payment_statuses()) ?>
                    <?php if ($p['confirmer']): ?><br><small class="text-muted"><?= e($p['confirmer']) ?></small><?php endif; ?>
                    <?php if ($p['note']): ?><br><small class="text-muted"><?= e($p['note']) ?></small><?php endif; ?></td>
                <td class="actions text-nowrap">
                    <?php if ($p['status'] === 'cho_xac_nhan'): ?>
                        <form method="post" class="d-inline" data-confirm="Xác nhận đã nhận được <?= money($p['total_amount']) ?> với nội dung 'NOP PHAT <?= e($p['violation_code']) ?>'?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="confirm">
                            <button class="btn btn-sm btn-success" title="Xác nhận"><i class="fa fa-check"></i></button>
                        </form>
                        <form method="post" class="d-inline" data-confirm="Từ chối giao dịch này?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="reject">
                            <button class="btn btn-sm btn-outline-danger" title="Từ chối"><i class="fa fa-times"></i></button>
                        </form>
                    <?php elseif ($p['status'] === 'thanh_cong'): ?>
                        <a href="<?= url('receipt.php?tx=' . urlencode($p['transaction_code'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Biên lai"><i class="fa fa-print"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-3"><?= render_pagination($pg) ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
