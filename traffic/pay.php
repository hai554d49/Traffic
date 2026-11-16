<?php
/**
 * TOÀN BỘ LUỒNG THANH TOÁN
 *   GET  pay.php?code=VP..       -> show_pay_page()   tạo phiên QR, hiện QR ví + QR VietQR, JS hỏi trạng thái mỗi 3 giây
 *   POST action=transfer         -> report_transfer() người dân báo "đã chuyển khoản" -> chờ cán bộ xác nhận
 *   GET  pay.php?status=TOKEN    -> api_status()      API trả JSON trạng thái giao dịch (JS gọi)
 *   GET  pay.php?token=TOKEN     -> show_gateway()    cổng ví MÔ PHỎNG, mở khi quét QR bằng điện thoại
 *   POST action=pay|cancel       -> gateway_submit()  xác nhận / hủy trên cổng ví
 *   GET  pay.php?receipt=GD..    -> show_receipt()    biên lai điện tử
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';

if (input('status') !== '') {
    api_status(input('status'));
} elseif (input('token') !== '') {
    show_gateway(input('token'));
} elseif (input('receipt') !== '') {
    show_receipt(input('receipt'));
} else {
    show_pay_page(input('code'));
}

/* ======================= TRANG NỘP PHẠT (máy tính) ======================= */

function show_pay_page($code)
{
    $v = find_violation_by_code($code);
    if (!$v) {
        flash('warning', 'Không tìm thấy biên bản vi phạm.');
        redirect('index.php');
    }
    $back = 'violation.php?code=' . urlencode($v['code']);
    if ($v['status'] !== 'chua_nop') {
        flash('info', 'Biên bản này không ở trạng thái chờ nộp phạt.');
        redirect($back);
    }
    if (fetch_value("SELECT COUNT(*) FROM payments WHERE violation_id = ? AND status = 'cho_xac_nhan'", [$v['id']])) {
        flash('warning', 'Bạn đã báo chuyển khoản cho biên bản này, vui lòng chờ cán bộ xác nhận.');
        redirect($back);
    }
    if (is_post() && input('action') === 'transfer') {
        verify_csrf();
        report_transfer($v);
    }

    $f = calc_fine($v);
    $payment = get_or_create_qr_session($v, $f['total']);
    $gatewayUrl = absolute_url('pay.php?token=' . $payment['token']);
    $transferContent = 'NOP PHAT ' . $v['code'];

    page_header('Nộp phạt ' . $v['code'], 'home');
    ?>
    <div class="page-banner"><div class="container"><h1>Nộp phạt trực tuyến</h1><p>Biên bản <?= e($v['code']) ?> · <span class="plate"><?= e($v['plate_number']) ?></span></p></div></div>
    <div class="page-content"><div class="container"><div class="row">
        <div class="col-lg-4 order-lg-2"><div class="card card-app">
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
        </div></div>
        <div class="col-lg-8 order-lg-1"><div class="card card-app">
            <div class="card-header p-0 border-0"><ul class="nav nav-tabs card-header-tabs m-0">
                <li class="nav-item"><a class="nav-link active text-dark font-weight-bold" data-toggle="tab" href="#tab-wallet"><i class="fa fa-mobile"></i> Quét QR ví điện tử</a></li>
                <li class="nav-item"><a class="nav-link text-dark font-weight-bold" data-toggle="tab" href="#tab-bank"><i class="fa fa-university"></i> Chuyển khoản VietQR</a></li>
            </ul></div>
            <div class="card-body tab-content">
                <div class="tab-pane fade show active text-center" id="tab-wallet">
                    <div id="qr-active">
                        <p>Dùng camera điện thoại quét mã QR dưới đây để mở cổng thanh toán</p>
                        <div class="qr-wrap"><div id="qrcode"></div></div>
                        <p class="mt-3 mb-1">Mã giao dịch: <b><?= e($payment['transaction_code']) ?></b></p>
                        <p>Mã QR hết hạn sau <span class="countdown" id="countdown">--:--</span></p>
                        <p class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Đang chờ thanh toán... trang sẽ tự cập nhật khi thanh toán thành công.</p>
                        <a href="<?= e($gatewayUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Không có điện thoại? Mở cổng thanh toán trên máy này</a>
                        <?php if (is_localhost_url()): ?>
                            <div class="alert alert-warning small mt-3 text-left mb-0"><b>Lưu ý demo:</b> mã QR đang trỏ tới <code>localhost</code> nên điện thoại không mở được.
                                Hãy truy cập bằng IP LAN của máy (vd <code>http://192.168.1.x<?= e(BASE_URL) ?>/</code>) hoặc khai báo <code>PUBLIC_HOST</code> trong <code>core.php</code>.</div>
                        <?php endif; ?>
                    </div>
                    <div id="qr-expired" class="d-none"><div class="alert alert-warning">Mã QR đã hết hạn.</div><a href="" class="btn btn-app">Tạo mã QR mới</a></div>
                </div>
                <div class="tab-pane fade" id="tab-bank"><div class="row align-items-center">
                    <div class="col-md-6 text-center"><div class="qr-wrap"><img src="<?= e(vietqr_image_url($f['total'], $transferContent)) ?>" alt="VietQR" style="max-width:260px" class="img-fluid"></div></div>
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
                </div></div>
            </div>
        </div></div>
    </div></div></div>
    <?php
    ob_start();
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById('qrcode'), { text: <?= json_encode($gatewayUrl) ?>, width: 240, height: 240 });
        var secondsLeft = <?= max(0, strtotime($payment['expires_at']) - time()) ?>;
        var statusUrl = <?= json_encode(url('pay.php?status=' . $payment['token'])) ?>;
        function showExpired() {
            document.getElementById('qr-active').classList.add('d-none');
            document.getElementById('qr-expired').classList.remove('d-none');
        }
        (function tick() {
            if (secondsLeft <= 0) { showExpired(); return; }
            var m = Math.floor(secondsLeft / 60), s = secondsLeft % 60;
            document.getElementById('countdown').textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            secondsLeft--;
            setTimeout(tick, 1000);
        })();
        // Polling: cứ 3 giây hỏi API api_status() một lần
        var poll = setInterval(function () {
            fetch(statusUrl, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.status === 'thanh_cong') { clearInterval(poll); window.location = data.receipt_url; }
                else if (data.status === 'het_han' || data.status === 'that_bai') { clearInterval(poll); showExpired(); }
            }).catch(function () {});
        }, 3000);
    </script>
    <?php
    page_footer(ob_get_clean());
}

/** Dùng lại phiên QR còn hạn nếu số tiền không đổi, nếu không thì tạo phiên mới (token ngẫu nhiên, hết hạn sau N phút) */
function get_or_create_qr_session(array $v, $total)
{
    $payment = fetch_one("SELECT * FROM payments WHERE violation_id = ? AND method = 'qr_vi_dien_tu' AND status = 'cho_thanh_toan'
                          AND expires_at > NOW() ORDER BY id DESC LIMIT 1", [$v['id']]);
    if ($payment && (int) $payment['total_amount'] === $total) {
        return $payment;
    }
    if ($payment) {
        // Sang ngày mới, lãi tăng -> bỏ phiên cũ
        query("UPDATE payments SET status = 'het_han' WHERE id = ?", [$payment['id']]);
    }
    $minutes = max(1, (int) setting('qr_expire_minutes', 15));
    $id = create_payment($v, 'qr_vi_dien_tu', 'cho_thanh_toan', [
        'token'      => bin2hex(random_bytes(20)),
        'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
    ]);
    return fetch_one('SELECT * FROM payments WHERE id = ?', [$id]);
}

/** Người dân báo đã chuyển khoản VietQR -> tạo giao dịch "chờ xác nhận" cho cán bộ đối soát */
function report_transfer(array $v)
{
    query("UPDATE payments SET status = 'het_han' WHERE violation_id = ? AND status = 'cho_thanh_toan'", [$v['id']]);
    $id = create_payment($v, 'chuyen_khoan', 'cho_xac_nhan', ['note' => 'ND: NOP PHAT ' . $v['code']]);
    $tx = fetch_value('SELECT transaction_code FROM payments WHERE id = ?', [$id]);
    log_activity('Báo chuyển khoản', $tx . ' - ' . $v['code']);
    flash('success', 'Đã ghi nhận yêu cầu <b>' . $tx . '</b>. Cán bộ sẽ đối soát và xác nhận trong vòng 24 giờ.');
    redirect('violation.php?code=' . urlencode($v['code']));
}

/* ======================= API JSON ======================= */

function api_status($token)
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $p = fetch_one('SELECT status, transaction_code FROM payments WHERE token = ?', [$token]);
    if (!$p) {
        http_response_code(404);
        echo json_encode(['status' => 'not_found']);
        exit;
    }
    echo json_encode([
        'status'      => $p['status'],
        'receipt_url' => $p['status'] === 'thanh_cong' ? url('pay.php?receipt=' . urlencode($p['transaction_code'])) : null,
    ]);
    exit;
}

/* ======================= CỔNG VÍ MÔ PHỎNG (điện thoại) ======================= */
// Khi tích hợp ví thật (VNPay, MoMo), bước xác nhận do cổng thanh toán gọi về server (IPN) kèm chữ ký, thay cho nút bấm.

function find_payment_by_token($token)
{
    return fetch_one('SELECT p.*, v.code AS violation_code, vh.plate_number, vt.name AS type_name
        FROM payments p JOIN violations v ON v.id = p.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
        JOIN violation_types vt ON vt.id = v.violation_type_id WHERE p.token = ?', [$token]);
}

/** Xử lý nút trên cổng ví; trả về thông báo lỗi [loại, nội dung] hoặc null nếu thanh toán thành công */
function gateway_submit(array $payment)
{
    if (input('action') === 'cancel') {
        query("UPDATE payments SET status = 'that_bai', note = 'Người dùng hủy giao dịch' WHERE id = ? AND status = 'cho_thanh_toan'", [$payment['id']]);
        return ['secondary', 'Bạn đã hủy giao dịch.'];
    }
    if ($payment['status'] !== 'cho_thanh_toan') return ['warning', 'Giao dịch không còn hiệu lực.'];
    if (strtotime($payment['expires_at']) < time()) return ['warning', 'Mã QR đã hết hạn, vui lòng tạo mã mới.'];
    if (!complete_payment($payment['id'])) return ['danger', 'Không thể thanh toán: biên bản đã được xử lý trước đó.'];
    return null;
}

function show_gateway($token)
{
    $payment = find_payment_by_token($token);
    $message = null;
    if ($payment && is_post()) {
        verify_csrf();
        $message = gateway_submit($payment);
        $payment = find_payment_by_token($token); // đọc lại trạng thái mới
    }
    $active = $payment && $payment['status'] === 'cho_thanh_toan' && strtotime($payment['expires_at']) >= time();
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
    <div class="wallet-head text-center"><i class="fa fa-credit-card fa-2x"></i><h4 class="mt-2 mb-0">Ví điện tử Demo</h4><small>Cổng thanh toán mô phỏng</small></div>
    <div class="bg-white p-4 shadow-sm" style="border-radius:0 0 12px 12px">
        <?php if (!$payment): ?>
            <div class="alert alert-danger mb-0">Mã thanh toán không hợp lệ.</div>
        <?php elseif ($payment['status'] === 'thanh_cong'): ?>
            <div class="text-center">
                <i class="fa fa-check-circle text-success" style="font-size:72px"></i>
                <h4 class="mt-3 font-weight-bold">Thanh toán thành công</h4>
                <p class="mb-1">Số tiền: <b><?= money($payment['total_amount']) ?></b></p>
                <p class="text-muted small">Mã giao dịch <?= e($payment['transaction_code']) ?> · <?= fmt_datetime($payment['paid_at']) ?></p>
                <a href="<?= url('pay.php?receipt=' . urlencode($payment['transaction_code'])) ?>" class="btn btn-success btn-block">Xem biên lai</a>
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
            <div class="text-center my-3"><small class="text-muted">Tổng thanh toán</small><div style="font-size:32px;font-weight:800;color:#a50064"><?= money($payment['total_amount']) ?></div></div>
            <?php if ($active): ?>
                <form method="post">
                    <?= csrf_field() ?>
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
    <?php
}

/* ======================= BIÊN LAI ======================= */

function show_receipt($tx)
{
    $p = fetch_one("SELECT p.*, v.code AS violation_code, v.violation_time, vh.plate_number, vh.owner_name, vt.name AS type_name
        FROM payments p JOIN violations v ON v.id = p.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
        JOIN violation_types vt ON vt.id = v.violation_type_id
        WHERE p.transaction_code = ? AND p.status = 'thanh_cong'", [$tx]);
    if (!$p) {
        flash('warning', 'Không tìm thấy biên lai.');
        redirect('index.php');
    }
    $paid = strtotime($p['paid_at']);

    page_header('Biên lai ' . $p['transaction_code']);
    ?>
    <div class="page-content"><div class="container">
        <div class="text-center mb-3 no-print">
            <div class="alert alert-success d-inline-block"><i class="fa fa-check-circle"></i> Thanh toán thành công!</div><br>
            <button onclick="window.print()" class="btn btn-app"><i class="fa fa-print"></i> In biên lai</button>
            <a href="<?= url('violation.php?code=' . urlencode($p['violation_code'])) ?>" class="btn btn-outline-dark">Xem biên bản</a>
        </div>
        <div class="print-sheet">
            <div class="row">
                <div class="col-8"><b>CỤC CẢNH SÁT GIAO THÔNG</b><br><small><?= APP_NAME ?> - Hệ thống nộp phạt trực tuyến</small></div>
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
                <div class="col-6 text-center"><i>Ngày <?= date('d', $paid) ?> tháng <?= date('m', $paid) ?> năm <?= date('Y', $paid) ?></i><br>
                    <b>ĐƠN VỊ THU</b><br><span class="text-success font-weight-bold" style="font-size:18px">ĐÃ THU TIỀN</span></div>
            </div>
        </div>
    </div></div>
    <?php
    page_footer('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById("qr-verify"), { text: ' . json_encode(absolute_url('pay.php?receipt=' . $p['transaction_code'])) . ', width: 110, height: 110 });</script>');
}
