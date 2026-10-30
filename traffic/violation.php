<?php
require_once __DIR__ . '/includes/init.php';

$v = find_violation_by_code(input('code'));
if (!$v) {
    flash('warning', 'Không tìm thấy biên bản vi phạm.');
    redirect('lookup.php');
}

$user = current_user();
$isOwner = $user && $user['cccd'] && $user['cccd'] === $v['owner_cccd'];
$canSeePrivate = $isOwner || is_staff();

/** Che bớt thông tin cá nhân với người không phải chủ xe: "Nguyễn Văn An" => "Nguyễn V** A*" */
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

$f = calc_fine($v);
$complaints = fetch_all('SELECT * FROM complaints WHERE violation_id = ? ORDER BY created_at DESC', [$v['id']]);
$payment = fetch_one("SELECT * FROM payments WHERE violation_id = ? AND status = 'thanh_cong' ORDER BY paid_at DESC LIMIT 1", [$v['id']]);
$pendingTransfer = fetch_one("SELECT * FROM payments WHERE violation_id = ? AND status = 'cho_xac_nhan' LIMIT 1", [$v['id']]);

$pageTitle = 'Biên bản ' . $v['code'];
$activeMenu = 'lookup';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Biên bản <?= e($v['code']) ?></h1>
        <p><span class="plate"><?= e($v['plate_number']) ?></span> &nbsp; <?= badge($v['status'], violation_statuses()) ?>
            <?php if ($f['overdue']): ?><span class="badge badge-danger">Quá hạn <?= $f['late_days'] ?> ngày</span><?php endif; ?></p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="card card-app">
                    <div class="card-header">Thông tin vi phạm</div>
                    <div class="card-body">
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
                        <?php if ($v['evidence_image']): ?>
                            <hr><a href="<?= url($v['evidence_image']) ?>" target="_blank"><img src="<?= url($v['evidence_image']) ?>" class="img-fluid rounded" alt="Ảnh vi phạm"></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card card-app">
                    <div class="card-header">Khiếu nại (<?= count($complaints) ?>)</div>
                    <div class="card-body">
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
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-app">
                    <div class="card-header">Số tiền phải nộp</div>
                    <div class="card-body">
                        <?php if ($v['status'] === 'da_nop' && $payment): ?>
                            <div class="alert alert-success"><i class="fa fa-check-circle"></i> Đã nộp phạt lúc <?= fmt_datetime($payment['paid_at']) ?></div>
                            <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($payment['fine_amount']) ?></b></div>
                            <div class="d-flex justify-content-between"><span>Chậm nộp</span><b><?= money($payment['late_fee']) ?></b></div>
                            <div class="d-flex justify-content-between"><span>Đã nộp</span><b class="text-success"><?= money($payment['total_amount']) ?></b></div>
                            <a href="receipt.php?tx=<?= urlencode($payment['transaction_code']) ?>" class="btn btn-outline-dark btn-block mt-3"><i class="fa fa-print"></i> Xem biên lai</a>
                        <?php elseif ($v['status'] === 'da_huy'): ?>
                            <div class="alert alert-secondary mb-0">Biên bản đã bị hủy, không phải nộp phạt.</div>
                        <?php else: ?>
                            <div class="amount-box">
                                <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($f['fine']) ?></b></div>
                                <div class="d-flex justify-content-between"><span>Chậm nộp<?= $f['late_days'] ? ' (' . $f['late_days'] . ' ngày × ' . $f['rate'] . '%)' : '' ?></span>
                                    <b class="text-danger"><?= money($f['late_fee']) ?></b></div>
                                <hr class="my-2">
                                <div class="text-center"><small>Tổng cộng</small><div class="total"><?= money($f['total']) ?></div></div>
                            </div>
                            <?php if ($v['status'] === 'khieu_nai'): ?>
                                <div class="alert alert-info mt-3 mb-0 small">Biên bản đang được xem xét khiếu nại. Tiền chậm nộp tạm dừng tính cho đến khi có kết quả.</div>
                            <?php elseif ($pendingTransfer): ?>
                                <div class="alert alert-warning mt-3 mb-0 small">Bạn đã báo chuyển khoản (<?= e($pendingTransfer['transaction_code']) ?>). Đang chờ cán bộ xác nhận.</div>
                            <?php else: ?>
                                <?php if (!$f['overdue'] && $f['days_left'] >= 0): ?>
                                    <p class="small text-muted mt-2 mb-0">Còn <b><?= $f['days_left'] ?></b> ngày để nộp phạt không bị tính lãi.</p>
                                <?php endif; ?>
                                <a href="pay.php?code=<?= urlencode($v['code']) ?>" class="btn btn-app btn-block btn-lg mt-3"><i class="fa fa-qrcode"></i> Nộp phạt bằng QR</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (in_array($v['status'], ['chua_nop', 'khieu_nai'], true)): ?>
                    <a href="complaint.php?code=<?= urlencode($v['code']) ?>" class="btn btn-outline-dark btn-block"><i class="fa fa-commenting"></i> Gửi khiếu nại</a>
                <?php endif; ?>
                <a href="notice.php?code=<?= urlencode($v['code']) ?>" target="_blank" class="btn btn-outline-secondary btn-block"><i class="fa fa-print"></i> In thông báo vi phạm</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
