<?php
/**
 * CHI TIẾT BIÊN BẢN + IN THÔNG BÁO + KHIẾU NẠI
 *   GET  violation.php?code=VP..              -> show_detail()
 *   GET  violation.php?code=VP..&print=1      -> show_notice()       (thông báo vi phạm có QR nộp phạt)
 *   GET  violation.php?code=VP..&complain=1   -> show_complaint_form()
 *   POST action=complain                      -> submit_complaint()
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';

$v = find_violation_by_code(input('code'));
if (!$v) {
    flash('warning', 'Không tìm thấy biên bản vi phạm.');
    redirect('index.php');
}

if (input('complain') !== '' || input('action') === 'complain') {
    require_login();
    $errors = [];
    if (is_post()) {
        verify_csrf();
        $errors = submit_complaint($v); // thành công thì redirect, không quay lại đây
    }
    show_complaint_form($v, $errors);
} elseif (input('print') !== '') {
    show_notice($v);
} else {
    show_detail($v);
}

/** Kiểm tra biên bản còn được khiếu nại không; trả về câu báo lỗi hoặc null */
function complaint_blocked(array $v)
{
    if (!in_array($v['status'], ['chua_nop', 'khieu_nai'], true)) {
        return 'Biên bản đã ' . mb_strtolower(violation_statuses()[$v['status']][0]) . ', không thể khiếu nại.';
    }
    if (fetch_value("SELECT COUNT(*) FROM complaints WHERE violation_id = ? AND user_id = ? AND status = 'cho_xu_ly'", [$v['id'], current_user()['id']])) {
        return 'Bạn đã có một khiếu nại đang chờ xử lý cho biên bản này.';
    }
    return null;
}

/** Lưu khiếu nại: kiểm tra -> upload file -> INSERT complaints -> chuyển biên bản sang "đang khiếu nại" */
function submit_complaint(array $v)
{
    if ($msg = complaint_blocked($v)) {
        flash('warning', $msg);
        redirect('violation.php?code=' . urlencode($v['code']));
    }
    $name = input('complainant_name');
    $phone = input('complainant_phone');
    $content = input('content');

    $errors = [];
    if ($name === '') $errors[] = 'Vui lòng nhập họ tên người khiếu nại.';
    if (!preg_match('/^0\d{9,10}$/', $phone)) $errors[] = 'Số điện thoại không hợp lệ.';
    if (mb_strlen($content) < 20) $errors[] = 'Nội dung khiếu nại cần tối thiểu 20 ký tự, trình bày rõ lý do.';
    if (empty($_POST['agree'])) $errors[] = 'Vui lòng cam kết thông tin khiếu nại là đúng sự thật.';
    if ($errors) return $errors;

    try {
        $file = upload_file('evidence_file', 'complaints');
    } catch (Exception $ex) {
        return [$ex->getMessage()];
    }

    $code = generate_code('KN', 'complaints');
    query('INSERT INTO complaints (code, violation_id, user_id, complainant_name, complainant_phone, content, evidence_file) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$code, $v['id'], current_user()['id'], $name, $phone, $content, $file]);
    if ($v['status'] === 'chua_nop') {
        // Đang khiếu nại: tạm dừng thu phạt và tính lãi, hủy các mã QR đang mở
        query("UPDATE violations SET status = 'khieu_nai' WHERE id = ?", [$v['id']]);
        query("UPDATE payments SET status = 'het_han' WHERE violation_id = ? AND status = 'cho_thanh_toan'", [$v['id']]);
    }
    log_activity('Gửi khiếu nại', $code . ' - ' . $v['code']);
    flash('success', 'Đã gửi khiếu nại <b>' . $code . '</b>. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
    redirect('my.php?tab=complaints');
}

function show_complaint_form(array $v, array $errors)
{
    if (!$errors && ($msg = complaint_blocked($v))) {
        flash('info', $msg);
        redirect('violation.php?code=' . urlencode($v['code']));
    }
    $user = current_user();
    page_header('Gửi khiếu nại', 'complaints');
    ?>
    <div class="page-banner"><div class="container"><h1>Gửi khiếu nại</h1><p>Biên bản <?= e($v['code']) ?> · <span class="plate"><?= e($v['plate_number']) ?></span></p></div></div>
    <div class="page-content"><div class="container"><div class="row">
        <div class="col-lg-8"><div class="card card-app">
            <div class="card-header">Nội dung khiếu nại</div>
            <div class="card-body">
                <?= render_errors($errors) ?>
                <form method="post" enctype="multipart/form-data" action="<?= url('violation.php?code=' . urlencode($v['code'])) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="complain">
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Họ tên người khiếu nại *</label>
                            <input name="complainant_name" class="form-control" value="<?= old('complainant_name', $user['full_name']) ?>" required></div>
                        <div class="form-group col-md-6"><label>Số điện thoại liên hệ *</label>
                            <input name="complainant_phone" class="form-control" value="<?= old('complainant_phone', $user['phone']) ?>" required></div>
                    </div>
                    <div class="form-group"><label>Lý do khiếu nại *</label>
                        <textarea name="content" rows="6" class="form-control" required placeholder="Trình bày cụ thể: thời điểm đó xe ở đâu, ai điều khiển, sai sót trong biên bản..."><?= old('content') ?></textarea></div>
                    <div class="form-group"><label>Tài liệu / hình ảnh minh chứng (JPG, PNG, PDF ≤ 5MB)</label>
                        <input type="file" name="evidence_file" class="form-control-file" accept="image/*,.pdf"></div>
                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" id="agree" name="agree" value="1">
                        <label class="custom-control-label" for="agree">Tôi cam kết nội dung khiếu nại là đúng sự thật và chịu trách nhiệm trước pháp luật.</label>
                    </div>
                    <button class="btn btn-app"><i class="fa fa-paper-plane"></i> Gửi khiếu nại</button>
                    <a href="<?= url('violation.php?code=' . urlencode($v['code'])) ?>" class="btn btn-secondary">Hủy</a>
                </form>
            </div>
        </div></div>
        <div class="col-lg-4">
            <div class="card card-app"><div class="card-header">Biên bản bị khiếu nại</div><div class="card-body small">
                <p class="mb-1"><b><?= e($v['type_name']) ?></b></p>
                <p class="mb-1"><?= fmt_datetime($v['violation_time']) ?> - <?= e($v['location']) ?></p>
                <p class="mb-0">Tiền phạt: <b><?= money($v['fine_amount']) ?></b> · Hạn nộp: <?= fmt_date($v['due_date']) ?></p>
            </div></div>
            <div class="alert alert-info small"><b>Lưu ý:</b> Trong thời gian chờ xử lý khiếu nại, biên bản tạm dừng thu phạt và <b>không tính tiền chậm nộp</b>.
                Nếu khiếu nại bị từ chối, hạn nộp được gia hạn thêm đúng số ngày đã chờ xử lý.</div>
        </div>
    </div></div></div>
    <?php
    page_footer();
}

/** Che tên chủ xe với người lạ: "Nguyễn Văn An" => "Nguyễn V** A*" */
function mask_name($name)
{
    $parts = explode(' ', $name);
    foreach ($parts as $i => $p) {
        if ($i > 0) {
            $parts[$i] = mb_substr($p, 0, 1) . str_repeat('*', max(1, mb_strlen($p) - 1));
        }
    }
    return implode(' ', $parts);
}

function show_detail(array $v)
{
    $user = current_user();
    $canSeePrivate = is_staff() || ($user && $user['cccd'] && $user['cccd'] === $v['owner_cccd']);
    $f = calc_fine($v);
    $complaints = fetch_all('SELECT * FROM complaints WHERE violation_id = ? ORDER BY created_at DESC', [$v['id']]);
    $payment = fetch_one("SELECT * FROM payments WHERE violation_id = ? AND status = 'thanh_cong' ORDER BY paid_at DESC LIMIT 1", [$v['id']]);
    $pendingTransfer = fetch_one("SELECT * FROM payments WHERE violation_id = ? AND status = 'cho_xac_nhan' LIMIT 1", [$v['id']]);
    $code = urlencode($v['code']);

    page_header('Biên bản ' . $v['code'], 'home');
    ?>
    <div class="page-banner"><div class="container">
        <h1>Biên bản <?= e($v['code']) ?></h1>
        <p><span class="plate"><?= e($v['plate_number']) ?></span> &nbsp; <?= badge($v['status'], violation_statuses()) ?>
            <?php if ($f['overdue']): ?><span class="badge badge-danger">Quá hạn <?= $f['late_days'] ?> ngày</span><?php endif; ?></p>
    </div></div>
    <div class="page-content"><div class="container"><div class="row">
        <div class="col-lg-8">
            <div class="card card-app"><div class="card-header">Thông tin vi phạm</div><div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Lỗi vi phạm</dt><dd class="col-sm-8"><?= e($v['type_name']) ?></dd>
                    <dt class="col-sm-4">Căn cứ</dt><dd class="col-sm-8"><?= e($v['legal_basis']) ?></dd>
                    <dt class="col-sm-4">Thời gian</dt><dd class="col-sm-8"><?= fmt_datetime($v['violation_time']) ?></dd>
                    <dt class="col-sm-4">Địa điểm</dt><dd class="col-sm-8"><?= e($v['location']) ?></dd>
                    <?php if ($v['description']): ?><dt class="col-sm-4">Mô tả</dt><dd class="col-sm-8"><?= e($v['description']) ?></dd><?php endif; ?>
                    <dt class="col-sm-4">Phương tiện</dt><dd class="col-sm-8"><?= vehicle_type_label($v['vehicle_type']) ?> - <?= e($v['brand']) ?> - <?= e($v['color']) ?></dd>
                    <dt class="col-sm-4">Chủ phương tiện</dt><dd class="col-sm-8"><?= e($canSeePrivate ? $v['owner_name'] : mask_name($v['owner_name'])) ?></dd>
                    <?php if ($canSeePrivate): ?><dt class="col-sm-4">Địa chỉ</dt><dd class="col-sm-8"><?= e($v['owner_address']) ?></dd><?php endif; ?>
                    <dt class="col-sm-4">Cán bộ lập biên bản</dt><dd class="col-sm-8"><?= e($v['officer_name'] ?: 'Hệ thống camera giám sát') ?></dd>
                    <dt class="col-sm-4">Hạn nộp phạt</dt><dd class="col-sm-8"><?= fmt_date($v['due_date']) ?></dd>
                </dl>
                <?php if ($v['evidence_image']): ?><hr><a href="<?= url($v['evidence_image']) ?>" target="_blank"><img src="<?= url($v['evidence_image']) ?>" class="img-fluid rounded" alt="Ảnh vi phạm"></a><?php endif; ?>
            </div></div>

            <div class="card card-app"><div class="card-header">Khiếu nại (<?= count($complaints) ?>)</div><div class="card-body">
                <?php if (!$complaints): ?><p class="text-muted mb-0">Chưa có khiếu nại nào.</p><?php endif; ?>
                <?php foreach ($complaints as $c): ?>
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between"><b><?= e($c['code']) ?></b> <?= badge($c['status'], complaint_statuses()) ?></div>
                        <small class="text-muted">Gửi lúc <?= fmt_datetime($c['created_at']) ?></small>
                        <?php if ($canSeePrivate || ($user && (int) $c['user_id'] === (int) $user['id'])): ?>
                            <p class="mt-2 mb-1" style="white-space:pre-line"><?= e($c['content']) ?></p>
                            <?php if ($c['response']): ?><div class="alert alert-light mb-0 mt-2"><b>Trả lời:</b> <?= e($c['response']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card card-app"><div class="card-header">Số tiền phải nộp</div><div class="card-body">
                <?php if ($v['status'] === 'da_nop' && $payment): ?>
                    <div class="alert alert-success"><i class="fa fa-check-circle"></i> Đã nộp phạt lúc <?= fmt_datetime($payment['paid_at']) ?></div>
                    <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($payment['fine_amount']) ?></b></div>
                    <div class="d-flex justify-content-between"><span>Chậm nộp</span><b><?= money($payment['late_fee']) ?></b></div>
                    <div class="d-flex justify-content-between"><span>Đã nộp</span><b class="text-success"><?= money($payment['total_amount']) ?></b></div>
                    <a href="<?= url('pay.php?receipt=' . urlencode($payment['transaction_code'])) ?>" class="btn btn-outline-dark btn-block mt-3"><i class="fa fa-print"></i> Xem biên lai</a>
                <?php elseif ($v['status'] === 'da_huy'): ?>
                    <div class="alert alert-secondary mb-0">Biên bản đã bị hủy, không phải nộp phạt.</div>
                <?php else: ?>
                    <div class="amount-box">
                        <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($f['fine']) ?></b></div>
                        <div class="d-flex justify-content-between"><span>Chậm nộp<?= $f['late_days'] ? ' (' . $f['late_days'] . ' ngày × ' . $f['rate'] . '%)' : '' ?></span><b class="text-danger"><?= money($f['late_fee']) ?></b></div>
                        <hr class="my-2">
                        <div class="text-center"><small>Tổng cộng</small><div class="total"><?= money($f['total']) ?></div></div>
                    </div>
                    <?php if ($v['status'] === 'khieu_nai'): ?>
                        <div class="alert alert-info mt-3 mb-0 small">Biên bản đang được xem xét khiếu nại. Tiền chậm nộp tạm dừng tính cho đến khi có kết quả.</div>
                    <?php elseif ($pendingTransfer): ?>
                        <div class="alert alert-warning mt-3 mb-0 small">Đã báo chuyển khoản (<?= e($pendingTransfer['transaction_code']) ?>). Đang chờ cán bộ xác nhận.</div>
                    <?php else: ?>
                        <?php if (!$f['overdue'] && $f['days_left'] >= 0): ?><p class="small text-muted mt-2 mb-0">Còn <b><?= $f['days_left'] ?></b> ngày để nộp phạt không bị tính lãi.</p><?php endif; ?>
                        <a href="<?= url('pay.php?code=' . $code) ?>" class="btn btn-app btn-block btn-lg mt-3"><i class="fa fa-qrcode"></i> Nộp phạt bằng QR</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div></div>
            <?php if (in_array($v['status'], ['chua_nop', 'khieu_nai'], true)): ?>
                <a href="<?= url('violation.php?complain=1&code=' . $code) ?>" class="btn btn-outline-dark btn-block"><i class="fa fa-commenting"></i> Gửi khiếu nại</a>
            <?php endif; ?>
            <a href="<?= url('violation.php?print=1&code=' . $code) ?>" target="_blank" class="btn btn-outline-secondary btn-block"><i class="fa fa-print"></i> In thông báo vi phạm</a>
        </div>
    </div></div></div>
    <?php
    page_footer();
}

/** Thông báo vi phạm gửi chủ xe (in được), có QR dẫn tới trang nộp phạt */
function show_notice(array $v)
{
    $f = calc_fine($v);
    $created = strtotime($v['created_at']);
    page_header('Thông báo vi phạm ' . $v['code']);
    ?>
    <div class="page-content"><div class="container">
        <div class="text-center mb-3 no-print"><button onclick="window.print()" class="btn btn-app"><i class="fa fa-print"></i> In thông báo</button></div>
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
                <b><?= e(setting('late_fee_rate')) ?>%</b> trên tổng số tiền phạt. Nếu không đồng ý, chủ phương tiện có quyền khiếu nại trực tuyến tại
                <i><?= e(absolute_url('')) ?></i> hoặc hotline <?= e(setting('hotline')) ?>.</p>
            <div class="row mt-4 align-items-center">
                <div class="col-sm-5 text-center"><div class="qr-wrap"><div id="qr-pay"></div></div><div class="small mt-1"><b>Quét mã QR để nộp phạt</b></div></div>
                <div class="col-sm-7 text-center"><i>Ngày <?= date('d', $created) ?> tháng <?= date('m', $created) ?> năm <?= date('Y', $created) ?></i><br>
                    <b>CÁN BỘ LẬP BIÊN BẢN</b><br><br><br><?= e($v['officer_name'] ?: '') ?></div>
            </div>
            <?php if ($v['status'] !== 'chua_nop'): ?>
                <div class="alert alert-info mt-4 mb-0 text-center">Trạng thái hiện tại: <?= violation_statuses()[$v['status']][0] ?></div>
            <?php elseif ($f['overdue']): ?>
                <div class="alert alert-danger mt-4 mb-0 text-center">Đã quá hạn <?= $f['late_days'] ?> ngày - số tiền phải nộp hiện tại: <b><?= money($f['total']) ?></b></div>
            <?php endif; ?>
        </div>
    </div></div>
    <?php
    page_footer('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById("qr-pay"), { text: ' . json_encode(absolute_url('pay.php?code=' . $v['code'])) . ', width: 150, height: 150 });</script>');
}
