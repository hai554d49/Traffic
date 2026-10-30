<?php
/**
 * Trang thanh toán phạt bằng mã QR.
 *  1) QR ví điện tử: QR chứa đường dẫn tới cổng thanh toán (pay_gateway.php) kèm token bí mật.
 *     Người dùng quét bằng điện thoại -> xác nhận -> hệ thống tự cập nhật "đã nộp" (trang này tự kiểm tra trạng thái).
 *  2) QR chuyển khoản VietQR: quét bằng app ngân hàng, sau đó bấm "Tôi đã chuyển khoản" để cán bộ đối soát.
 */
require_once __DIR__ . '/includes/init.php';

$v = find_violation_by_code(input('code'));
if (!$v) {
    flash('warning', 'Không tìm thấy biên bản vi phạm.');
    redirect('lookup.php');
}
if ($v['status'] !== 'chua_nop') {
    flash('info', 'Biên bản này không ở trạng thái chờ nộp phạt.');
    redirect('violation.php?code=' . urlencode($v['code']));
}

$f = calc_fine($v);
$user = current_user();
$transferContent = 'NOP PHAT ' . $v['code'];

if (fetch_value("SELECT COUNT(*) FROM payments WHERE violation_id = ? AND status = 'cho_xac_nhan'", [$v['id']])) {
    flash('warning', 'Bạn đã báo chuyển khoản cho biên bản này, vui lòng chờ cán bộ xác nhận.');
    redirect('violation.php?code=' . urlencode($v['code']));
}

// Người dân báo đã chuyển khoản qua VietQR
if (is_post() && input('action') === 'transfer') {
    verify_csrf();
    query("UPDATE payments SET status = 'het_han' WHERE violation_id = ? AND status = 'cho_thanh_toan'", [$v['id']]);
    $tx = generate_code('GD', 'payments', 'transaction_code');
    query("INSERT INTO payments (transaction_code, violation_id, user_id, fine_amount, late_days, late_fee, total_amount, method, status, note)
           VALUES (?, ?, ?, ?, ?, ?, ?, 'chuyen_khoan', 'cho_xac_nhan', ?)",
        [$tx, $v['id'], $user['id'] ?? null, $f['fine'], $f['late_days'], $f['late_fee'], $f['total'], 'ND: ' . $transferContent]);
    log_activity('Báo chuyển khoản', $tx . ' - ' . $v['code']);
    flash('success', 'Đã ghi nhận yêu cầu <b>' . $tx . '</b>. Cán bộ sẽ đối soát và xác nhận trong vòng 24 giờ.');
    redirect('violation.php?code=' . urlencode($v['code']));
}

// Tạo (hoặc dùng lại) phiên thanh toán QR ví điện tử còn hiệu lực
$payment = fetch_one("SELECT * FROM payments WHERE violation_id = ? AND method = 'qr_vi_dien_tu' AND status = 'cho_thanh_toan'
                      AND expires_at > NOW() ORDER BY id DESC LIMIT 1", [$v['id']]);
if ($payment && (int) $payment['total_amount'] !== $f['total']) {
    // Số tiền đã thay đổi (sang ngày mới phát sinh thêm lãi) -> tạo phiên mới
    query("UPDATE payments SET status = 'het_han' WHERE id = ?", [$payment['id']]);
    $payment = null;
}
if (!$payment) {
    $minutes = max(1, (int) setting('qr_expire_minutes', 15));
    query("INSERT INTO payments (transaction_code, violation_id, user_id, fine_amount, late_days, late_fee, total_amount, method, status, token, expires_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, 'qr_vi_dien_tu', 'cho_thanh_toan', ?, NOW() + INTERVAL $minutes MINUTE)",
        [generate_code('GD', 'payments', 'transaction_code'), $v['id'], $user['id'] ?? null, $f['fine'], $f['late_days'], $f['late_fee'], $f['total'], bin2hex(random_bytes(20))]);
    $payment = fetch_one('SELECT * FROM payments WHERE id = ?', [db()->lastInsertId()]);
}
$gatewayUrl = absolute_url('pay_gateway.php?token=' . $payment['token']);
$secondsLeft = max(0, strtotime($payment['expires_at']) - time());

$pageTitle = 'Nộp phạt ' . $v['code'];
$activeMenu = 'lookup';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Nộp phạt trực tuyến</h1>
        <p>Biên bản <?= e($v['code']) ?> · <span class="plate"><?= e($v['plate_number']) ?></span></p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 order-lg-2">
                <div class="card card-app">
                    <div class="card-header">Chi tiết khoản nộp</div>
                    <div class="card-body">
                        <p class="small mb-2"><?= e($v['type_name']) ?></p>
                        <div class="amount-box">
                            <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($f['fine']) ?></b></div>
                            <div class="d-flex justify-content-between"><span>Hạn nộp</span><span><?= fmt_date($v['due_date']) ?></span></div>
                            <div class="d-flex justify-content-between"><span>Quá hạn</span><span><?= $f['late_days'] ?> ngày</span></div>
                            <div class="d-flex justify-content-between"><span>Chậm nộp (<?= $f['rate'] ?>%/ngày)</span><b class="text-danger"><?= money($f['late_fee']) ?></b></div>
                            <hr class="my-2">
                            <div class="text-center"><small>Tổng thanh toán</small><div class="total"><?= money($f['total']) ?></div></div>
                        </div>
                        <?php if ($f['overdue']): ?>
                            <div class="alert alert-danger small mt-3 mb-0">Biên bản đã quá hạn nộp. Mỗi ngày chậm nộp tiếp theo phát sinh thêm <?= money(round($f['fine'] * $f['rate'] / 100)) ?>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 order-lg-1">
                <div class="card card-app">
                    <div class="card-header p-0 border-0">
                        <ul class="nav nav-tabs card-header-tabs m-0" role="tablist">
                            <li class="nav-item"><a class="nav-link active text-dark font-weight-bold" data-toggle="tab" href="#tab-wallet"><i class="fa fa-mobile"></i> Quét QR ví điện tử</a></li>
                            <li class="nav-item"><a class="nav-link text-dark font-weight-bold" data-toggle="tab" href="#tab-bank"><i class="fa fa-university"></i> Chuyển khoản VietQR</a></li>
                        </ul>
                    </div>
                    <div class="card-body tab-content">
                        <!-- QR ví điện tử -->
                        <div class="tab-pane fade show active text-center" id="tab-wallet">
                            <div id="qr-active">
                                <p>Dùng camera điện thoại quét mã QR dưới đây để mở cổng thanh toán</p>
                                <div class="qr-wrap"><div id="qrcode"></div></div>
                                <p class="mt-3 mb-1">Mã giao dịch: <b><?= e($payment['transaction_code']) ?></b></p>
                                <p>Mã QR hết hạn sau <span class="countdown" id="countdown">--:--</span></p>
                                <p class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Đang chờ thanh toán... trang sẽ tự cập nhật khi thanh toán thành công.</p>
                                <a href="<?= e($gatewayUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Không có điện thoại? Mở cổng thanh toán trên máy này</a>
                                <?php if (is_localhost_url()): ?>
                                    <div class="alert alert-warning small mt-3 text-left mb-0">
                                        <b>Lưu ý demo:</b> mã QR đang trỏ tới <code>localhost</code> nên điện thoại không mở được.
                                        Hãy truy cập trang này bằng IP LAN của máy (vd: <code>http://192.168.1.x<?= e(BASE_URL) ?>/</code>)
                                        hoặc khai báo <code>PUBLIC_HOST</code> trong <code>config/config.php</code>.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div id="qr-expired" class="d-none">
                                <div class="alert alert-warning">Mã QR đã hết hạn.</div>
                                <a href="" class="btn btn-app">Tạo mã QR mới</a>
                            </div>
                        </div>
                        <!-- QR chuyển khoản ngân hàng -->
                        <div class="tab-pane fade" id="tab-bank">
                            <div class="row align-items-center">
                                <div class="col-md-6 text-center">
                                    <div class="qr-wrap"><img src="<?= e(vietqr_image_url($f['total'], $transferContent)) ?>" alt="VietQR" style="max-width:260px" class="img-fluid"></div>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm mt-3 mt-md-0">
                                        <tr><td>Ngân hàng</td><td><b><?= e(setting('bank_id')) ?></b></td></tr>
                                        <tr><td>Số tài khoản</td><td><b><?= e(setting('bank_account_no')) ?></b></td></tr>
                                        <tr><td>Chủ tài khoản</td><td><b><?= e(setting('bank_account_name')) ?></b></td></tr>
                                        <tr><td>Số tiền</td><td><b class="text-danger"><?= money($f['total']) ?></b></td></tr>
                                        <tr><td>Nội dung</td><td><b><?= e($transferContent) ?></b></td></tr>
                                    </table>
                                    <p class="small text-muted">Mở app ngân hàng, chọn quét QR. Thông tin số tiền & nội dung được điền sẵn.</p>
                                    <form method="post" data-confirm="Bạn chắc chắn đã chuyển khoản <?= money($f['total']) ?> với nội dung '<?= e($transferContent) ?>'?">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="transfer">
                                        <button class="btn btn-dark btn-block"><i class="fa fa-check"></i> Tôi đã chuyển khoản</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    (function () {
        new QRCode(document.getElementById('qrcode'), { text: <?= json_encode($gatewayUrl) ?>, width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });

        var secondsLeft = <?= (int) $secondsLeft ?>;
        var statusUrl = <?= json_encode(url('payment_status.php?token=' . $payment['token'])) ?>;
        var cd = document.getElementById('countdown');

        function showExpired() {
            document.getElementById('qr-active').classList.add('d-none');
            document.getElementById('qr-expired').classList.remove('d-none');
        }
        function tick() {
            if (secondsLeft <= 0) { showExpired(); return; }
            var m = Math.floor(secondsLeft / 60), s = secondsLeft % 60;
            cd.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            secondsLeft--;
            setTimeout(tick, 1000);
        }
        tick();

        // Kiểm tra trạng thái giao dịch mỗi 3 giây
        var poll = setInterval(function () {
            fetch(statusUrl, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.status === 'thanh_cong') {
                    clearInterval(poll);
                    window.location = data.receipt_url;
                } else if (data.status === 'het_han' || data.status === 'that_bai') {
                    clearInterval(poll);
                    showExpired();
                }
            }).catch(function () {});
        }, 3000);
    })();
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
