<?php
/**
 * Cổng thanh toán MÔ PHỎNG (thay cho ví điện tử thật như MoMo/ZaloPay/VNPay).
 * Trang này được mở khi người dùng quét mã QR trên trang pay.php bằng điện thoại.
 * Khi tích hợp thật, bước xác nhận sẽ do cổng thanh toán gọi về (IPN/webhook) thay vì nút bấm.
 */
require_once __DIR__ . '/includes/init.php';

$token = input('token');
$payment = $token !== '' ? fetch_one('SELECT p.*, v.code AS violation_code, v.status AS violation_status, vh.plate_number, vt.name AS type_name
    FROM payments p JOIN violations v ON v.id = p.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
    JOIN violation_types vt ON vt.id = v.violation_type_id
    WHERE p.token = ?', [$token]) : null;

$message = null;
$success = false;

if ($payment && is_post()) {
    verify_csrf();
    if (input('action') === 'cancel') {
        query("UPDATE payments SET status = 'that_bai', note = 'Người dùng hủy giao dịch' WHERE id = ? AND status = 'cho_thanh_toan'", [$payment['id']]);
        $message = ['secondary', 'Bạn đã hủy giao dịch.'];
    } elseif ($payment['status'] !== 'cho_thanh_toan') {
        $message = ['warning', 'Giao dịch không còn hiệu lực.'];
    } elseif (strtotime($payment['expires_at']) < time()) {
        $message = ['warning', 'Mã QR đã hết hạn, vui lòng tạo mã mới.'];
    } elseif (complete_payment($payment['id'])) {
        $success = true;
    } else {
        $message = ['danger', 'Không thể thanh toán: biên bản đã được xử lý trước đó.'];
    }
    $payment = fetch_one('SELECT p.*, v.code AS violation_code, vh.plate_number, vt.name AS type_name
        FROM payments p JOIN violations v ON v.id = p.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
        JOIN violation_types vt ON vt.id = v.violation_type_id WHERE p.id = ?', [$payment['id']]);
}
$success = $success || ($payment && $payment['status'] === 'thanh_cong');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ví điện tử - Thanh toán</title>
    <link rel="stylesheet" href="<?= url('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?= url('css/app.css') ?>">
</head>
<body style="background:#f1f1f6">
<div class="wallet-page py-4 px-3">
    <div class="wallet-head text-center">
        <i class="fa fa-credit-card fa-2x"></i>
        <h4 class="mt-2 mb-0">Ví điện tử Demo</h4>
        <small>Cổng thanh toán mô phỏng</small>
    </div>
    <div class="bg-white p-4 shadow-sm" style="border-radius:0 0 12px 12px">
        <?php if (!$payment): ?>
            <div class="alert alert-danger mb-0">Mã thanh toán không hợp lệ.</div>
        <?php elseif ($success): ?>
            <div class="text-center">
                <i class="fa fa-check-circle text-success" style="font-size:72px"></i>
                <h4 class="mt-3 font-weight-bold">Thanh toán thành công</h4>
                <p class="mb-1">Số tiền: <b><?= money($payment['total_amount']) ?></b></p>
                <p class="text-muted small">Mã giao dịch <?= e($payment['transaction_code']) ?> · <?= fmt_datetime($payment['paid_at']) ?></p>
                <a href="<?= url('receipt.php?tx=' . urlencode($payment['transaction_code'])) ?>" class="btn btn-success btn-block">Xem biên lai</a>
            </div>
        <?php else: ?>
            <?php if ($message): ?><div class="alert alert-<?= $message[0] ?>"><?= e($message[1]) ?></div><?php endif; ?>
            <p class="text-muted mb-1">Thanh toán cho</p>
            <h6 class="font-weight-bold"><?= e(setting('bank_account_name')) ?></h6>
            <table class="table table-sm small">
                <tr><td>Nội dung</td><td>Nộp phạt <?= e($payment['violation_code']) ?></td></tr>
                <tr><td>Biển số</td><td><span class="plate"><?= e($payment['plate_number']) ?></span></td></tr>
                <tr><td>Lỗi</td><td><?= e($payment['type_name']) ?></td></tr>
                <tr><td>Tiền phạt</td><td><?= money($payment['fine_amount']) ?></td></tr>
                <tr><td>Chậm nộp</td><td><?= money($payment['late_fee']) ?></td></tr>
            </table>
            <div class="text-center my-3">
                <small class="text-muted">Tổng thanh toán</small>
                <div style="font-size:32px;font-weight:800;color:#a50064"><?= money($payment['total_amount']) ?></div>
            </div>
            <?php if ($payment['status'] === 'cho_thanh_toan' && strtotime($payment['expires_at']) >= time()): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <button name="action" value="pay" class="btn btn-lg btn-block text-white" style="background:#a50064">Xác nhận thanh toán</button>
                    <button name="action" value="cancel" class="btn btn-link btn-block text-muted">Hủy giao dịch</button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mb-0">Giao dịch đã <?= e(payment_statuses()[$payment['status']][0] ?? 'kết thúc') ?>. Vui lòng tạo mã QR mới trên trang nộp phạt.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <p class="text-center small text-muted mt-3">Đây là cổng thanh toán giả lập phục vụ demo đồ án, không trừ tiền thật.</p>
</div>
</body>
</html>
