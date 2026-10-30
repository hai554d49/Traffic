<?php
/**
 * Thông báo vi phạm gửi tới chủ phương tiện (in được), có mã QR dẫn tới trang nộp phạt.
 */
require_once __DIR__ . '/includes/init.php';

$v = find_violation_by_code(input('code'));
if (!$v) {
    flash('warning', 'Không tìm thấy biên bản vi phạm.');
    redirect('lookup.php');
}
$f = calc_fine($v);
$rate = setting('late_fee_rate');

$pageTitle = 'Thông báo vi phạm ' . $v['code'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-content">
    <div class="container">
        <div class="text-center mb-3 no-print">
            <button onclick="window.print()" class="btn btn-app"><i class="fa fa-print"></i> In thông báo</button>
            <a href="violation.php?code=<?= urlencode($v['code']) ?>" class="btn btn-outline-dark">Quay lại</a>
        </div>
        <div class="print-sheet">
            <div class="row text-center">
                <div class="col-6"><b>CÔNG AN TỈNH / THÀNH PHỐ</b><br><b>PHÒNG CẢNH SÁT GIAO THÔNG</b></div>
                <div class="col-6"><b>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</b><br><u>Độc lập - Tự do - Hạnh phúc</u></div>
            </div>
            <h2 class="mt-4">THÔNG BÁO VI PHẠM GIAO THÔNG</h2>
            <p class="text-center">Số: <b><?= e($v['code']) ?></b></p>
            <p class="mt-4">Kính gửi: Ông/Bà <b><?= e($v['owner_name']) ?></b> - Địa chỉ: <?= e($v['owner_address']) ?></p>
            <p>Phương tiện <?= e(vehicle_type_label($v['vehicle_type'])) ?> biển số <span class="plate"><?= e($v['plate_number']) ?></span>
                (<?= e($v['brand']) ?>, màu <?= e($v['color']) ?>) đã có hành vi vi phạm như sau:</p>
            <table class="table table-bordered">
                <tr><th style="width:30%">Thời gian</th><td><?= fmt_datetime($v['violation_time']) ?></td></tr>
                <tr><th>Địa điểm</th><td><?= e($v['location']) ?></td></tr>
                <tr><th>Hành vi vi phạm</th><td><?= e($v['type_name']) ?><?= $v['description'] ? '<br><small>' . e($v['description']) . '</small>' : '' ?></td></tr>
                <tr><th>Căn cứ</th><td><?= e($v['legal_basis']) ?></td></tr>
                <tr><th>Mức phạt</th><td><b><?= money($v['fine_amount']) ?></b></td></tr>
                <tr><th>Hạn nộp phạt</th><td><b><?= fmt_date($v['due_date']) ?></b></td></tr>
            </table>
            <p>Đề nghị chủ phương tiện nộp phạt trước ngày <b><?= fmt_date($v['due_date']) ?></b>. Quá thời hạn trên, mỗi ngày chậm nộp phải nộp thêm
                <b><?= e($rate) ?>%</b> trên tổng số tiền phạt. Nếu có ý kiến không đồng ý, chủ phương tiện có quyền khiếu nại trực tuyến tại
                <i><?= e(absolute_url('')) ?></i> hoặc hotline <?= e(setting('hotline')) ?>.</p>
            <div class="row mt-4 align-items-center">
                <div class="col-sm-5 text-center">
                    <div class="qr-wrap"><div id="qr-pay"></div></div>
                    <div class="small mt-1"><b>Quét mã QR để nộp phạt</b></div>
                </div>
                <div class="col-sm-7 text-center">
                    <i>Ngày <?= date('d', strtotime($v['created_at'])) ?> tháng <?= date('m', strtotime($v['created_at'])) ?> năm <?= date('Y', strtotime($v['created_at'])) ?></i><br>
                    <b>CÁN BỘ LẬP BIÊN BẢN</b><br><br><br><?= e($v['officer_name'] ?: '') ?>
                </div>
            </div>
            <?php if ($v['status'] !== 'chua_nop'): ?>
                <div class="alert alert-info mt-4 mb-0 text-center">Trạng thái hiện tại: <?= violation_statuses()[$v['status']][0] ?></div>
            <?php elseif ($f['overdue']): ?>
                <div class="alert alert-danger mt-4 mb-0 text-center">Đã quá hạn <?= $f['late_days'] ?> ngày - số tiền phải nộp hiện tại: <b><?= money($f['total']) ?></b></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr-pay'), { text: <?= json_encode(absolute_url('pay.php?code=' . $v['code'])) ?>, width: 150, height: 150 });</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
