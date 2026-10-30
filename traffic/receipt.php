<?php
/**
 * Biên lai thu tiền phạt điện tử (in được). Có mã QR để kiểm tra tính xác thực.
 */
require_once __DIR__ . '/includes/init.php';

$p = fetch_one("SELECT p.*, v.code AS violation_code, v.violation_time, v.location, vh.plate_number, vh.owner_name, vt.name AS type_name
    FROM payments p JOIN violations v ON v.id = p.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
    JOIN violation_types vt ON vt.id = v.violation_type_id
    WHERE p.transaction_code = ? AND p.status = 'thanh_cong'", [input('tx')]);
if (!$p) {
    flash('warning', 'Không tìm thấy biên lai.');
    redirect('lookup.php');
}

$pageTitle = 'Biên lai ' . $p['transaction_code'];
$hideFlash = false;
require __DIR__ . '/includes/header.php';
?>
<div class="page-content">
    <div class="container">
        <div class="text-center mb-3 no-print">
            <div class="alert alert-success d-inline-block"><i class="fa fa-check-circle"></i> Thanh toán thành công!</div><br>
            <button onclick="window.print()" class="btn btn-app"><i class="fa fa-print"></i> In biên lai</button>
            <a href="violation.php?code=<?= urlencode($p['violation_code']) ?>" class="btn btn-outline-dark">Xem biên bản</a>
        </div>
        <div class="print-sheet">
            <div class="row">
                <div class="col-8">
                    <b>CỤC CẢNH SÁT GIAO THÔNG</b><br><small><?= APP_NAME ?> - Hệ thống nộp phạt trực tuyến</small>
                </div>
                <div class="col-4 text-right"><small>Số: <b><?= e($p['transaction_code']) ?></b></small></div>
            </div>
            <h2 class="mt-4">BIÊN LAI THU TIỀN PHẠT</h2>
            <p class="text-center text-muted">(Biên lai điện tử)</p>
            <table class="table table-bordered mt-4">
                <tr><th style="width:35%">Người nộp / Chủ xe</th><td><?= e($p['owner_name']) ?></td></tr>
                <tr><th>Biển số</th><td><span class="plate"><?= e($p['plate_number']) ?></span></td></tr>
                <tr><th>Biên bản số</th><td><?= e($p['violation_code']) ?> - ngày <?= fmt_datetime($p['violation_time']) ?></td></tr>
                <tr><th>Lỗi vi phạm</th><td><?= e($p['type_name']) ?></td></tr>
                <tr><th>Tiền phạt</th><td><?= money($p['fine_amount']) ?></td></tr>
                <tr><th>Tiền chậm nộp</th><td><?= money($p['late_fee']) ?> (<?= (int) $p['late_days'] ?> ngày)</td></tr>
                <tr><th>Tổng số tiền đã nộp</th><td><b style="font-size:20px"><?= money($p['total_amount']) ?></b></td></tr>
                <tr><th>Hình thức</th><td><?= e(payment_methods()[$p['method']]) ?></td></tr>
                <tr><th>Thời gian thanh toán</th><td><?= fmt_datetime($p['paid_at']) ?></td></tr>
            </table>
            <div class="row mt-4 align-items-end">
                <div class="col-6"><div id="qr-verify"></div><small class="text-muted">Quét để kiểm tra biên lai</small></div>
                <div class="col-6 text-center"><i>Ngày <?= date('d', strtotime($p['paid_at'])) ?> tháng <?= date('m', strtotime($p['paid_at'])) ?> năm <?= date('Y', strtotime($p['paid_at'])) ?></i><br>
                    <b>ĐƠN VỊ THU</b><br><span class="text-success font-weight-bold" style="font-size:18px">ĐÃ THU TIỀN</span></div>
            </div>
        </div>
    </div>
</div>
<?php ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr-verify'), { text: <?= json_encode(absolute_url('receipt.php?tx=' . $p['transaction_code'])) ?>, width: 110, height: 110 });</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
