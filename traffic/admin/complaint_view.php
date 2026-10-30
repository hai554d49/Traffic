<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

$complaint = fetch_one('SELECT c.*, u.full_name AS handler_name, v.code AS violation_code FROM complaints c
                        JOIN violations v ON v.id = c.violation_id LEFT JOIN users u ON u.id = c.handled_by WHERE c.id = ?', [(int) input('id')]);
if (!$complaint) {
    flash('danger', 'Không tìm thấy khiếu nại.');
    redirect('admin/complaints.php');
}
$violation = find_violation_by_code($complaint['violation_code']);
$errors = [];

if (is_post() && $complaint['status'] === 'cho_xu_ly') {
    verify_csrf();
    $decision = input('decision');
    $response = input('response');
    $newFine = (int) input('new_fine');

    if (!in_array($decision, ['chap_nhan', 'tu_choi'], true)) {
        $errors[] = 'Vui lòng chọn kết quả xử lý.';
    }
    if ($response === '') {
        $errors[] = 'Vui lòng nhập nội dung trả lời cho người khiếu nại.';
    }
    if ($decision === 'chap_nhan' && input('accept_action') === 'adjust' && ($newFine <= 0 || $newFine >= $violation['fine_amount'])) {
        $errors[] = 'Mức phạt điều chỉnh phải lớn hơn 0 và nhỏ hơn mức phạt hiện tại.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        query('UPDATE complaints SET status = ?, response = ?, handled_by = ?, handled_at = NOW() WHERE id = ?',
            [$decision, $response, current_user()['id'], $complaint['id']]);

        // Số ngày khiếu nại được cộng vào hạn nộp, vì trong thời gian này không tính lãi chậm nộp
        $pendingDays = max(0, (int) (new DateTime($complaint['created_at']))->diff(new DateTime())->days);
        $hasOtherPending = fetch_value("SELECT COUNT(*) FROM complaints WHERE violation_id = ? AND status = 'cho_xu_ly'", [$violation['id']]);

        if ($decision === 'chap_nhan' && input('accept_action') !== 'adjust') {
            query("UPDATE violations SET status = 'da_huy' WHERE id = ?", [$violation['id']]);
            $result = 'Biên bản đã được hủy.';
        } elseif ($violation['status'] === 'khieu_nai' && !$hasOtherPending) {
            $sql = "UPDATE violations SET status = 'chua_nop', due_date = GREATEST(DATE_ADD(due_date, INTERVAL ? DAY), CURDATE() + INTERVAL 3 DAY)";
            $params = [$pendingDays];
            if ($decision === 'chap_nhan') {
                $sql .= ', fine_amount = ?';
                $params[] = $newFine;
            }
            $params[] = $violation['id'];
            query($sql . ' WHERE id = ?', $params);
            $result = $decision === 'chap_nhan'
                ? 'Mức phạt điều chỉnh còn ' . money($newFine) . ', hạn nộp được gia hạn.'
                : 'Biên bản trở lại trạng thái chờ nộp phạt, hạn nộp được gia hạn thêm ' . $pendingDays . ' ngày.';
        } else {
            $result = '';
        }
        $pdo->commit();

        log_activity('Xử lý khiếu nại', $complaint['code'] . ' - ' . complaint_statuses()[$decision][0]);
        flash('success', 'Đã xử lý khiếu nại ' . e($complaint['code']) . '. ' . $result);
        redirect('admin/complaints.php');
    }
}

$history = fetch_all('SELECT * FROM complaints WHERE violation_id = ? AND id <> ? ORDER BY created_at DESC', [$violation['id'], $complaint['id']]);

$pageTitle = 'Khiếu nại ' . $complaint['code'];
$activeMenu = 'complaints';
require __DIR__ . '/includes/header.php';
?>
<div class="row">
    <div class="col-lg-7">
        <div class="card card-app">
            <div class="card-header d-flex justify-content-between">Nội dung khiếu nại <?= badge($complaint['status'], complaint_statuses()) ?></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Người khiếu nại</dt><dd class="col-sm-8"><?= e($complaint['complainant_name']) ?> - <?= e($complaint['complainant_phone']) ?></dd>
                    <dt class="col-sm-4">Ngày gửi</dt><dd class="col-sm-8"><?= fmt_datetime($complaint['created_at']) ?></dd>
                    <dt class="col-sm-4">Nội dung</dt><dd class="col-sm-8" style="white-space:pre-line"><?= e($complaint['content']) ?></dd>
                    <?php if ($complaint['evidence_file']): ?>
                        <dt class="col-sm-4">Minh chứng</dt>
                        <dd class="col-sm-8">
                            <?php if (substr($complaint['evidence_file'], -4) === '.pdf'): ?>
                                <a href="<?= url($complaint['evidence_file']) ?>" target="_blank"><i class="fa fa-file-pdf-o"></i> Xem file PDF</a>
                            <?php else: ?>
                                <a href="<?= url($complaint['evidence_file']) ?>" target="_blank"><img src="<?= url($complaint['evidence_file']) ?>" class="img-thumbnail" style="max-height:200px"></a>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($complaint['status'] === 'cho_xu_ly'): ?>
            <div class="card card-app">
                <div class="card-header">Kết quả xử lý</div>
                <div class="card-body">
                    <?php if ($errors): ?>
                        <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="d1" name="decision" value="chap_nhan" class="custom-control-input" <?= input('decision') === 'chap_nhan' ? 'checked' : '' ?>>
                                <label class="custom-control-label text-success font-weight-bold" for="d1">Chấp nhận khiếu nại</label>
                            </div>
                            <div class="ml-4 mt-2 mb-2" id="acceptOptions">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="a1" name="accept_action" value="cancel" class="custom-control-input" checked>
                                    <label class="custom-control-label" for="a1">Hủy biên bản vi phạm</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="a2" name="accept_action" value="adjust" class="custom-control-input" <?= input('accept_action') === 'adjust' ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="a2">Điều chỉnh giảm mức phạt còn:</label>
                                </div>
                                <input type="number" name="new_fine" step="1000" class="form-control form-control-sm mt-1" style="max-width:220px" placeholder="Số tiền mới" value="<?= old('new_fine') ?>">
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="d2" name="decision" value="tu_choi" class="custom-control-input" <?= input('decision') === 'tu_choi' ? 'checked' : '' ?>>
                                <label class="custom-control-label text-danger font-weight-bold" for="d2">Từ chối khiếu nại (giữ nguyên biên bản, gia hạn nộp)</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Trả lời người khiếu nại *</label>
                            <textarea name="response" rows="4" class="form-control" required><?= old('response') ?></textarea>
                        </div>
                        <button class="btn btn-app"><i class="fa fa-check"></i> Lưu kết quả</button>
                        <a href="complaints.php" class="btn btn-secondary">Quay lại</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card card-app">
                <div class="card-header">Kết quả đã xử lý</div>
                <div class="card-body">
                    <p style="white-space:pre-line"><?= e($complaint['response']) ?></p>
                    <small class="text-muted">Xử lý bởi <?= e($complaint['handler_name'] ?: 'N/A') ?> lúc <?= fmt_datetime($complaint['handled_at']) ?></small>
                </div>
            </div>
            <a href="complaints.php" class="btn btn-secondary">Quay lại</a>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="card card-app">
            <div class="card-header">Biên bản <?= e($violation['code']) ?></div>
            <div class="card-body">
                <p><span class="plate"><?= e($violation['plate_number']) ?></span> <?= badge($violation['status'], violation_statuses()) ?></p>
                <dl class="row small mb-0">
                    <dt class="col-5">Chủ xe</dt><dd class="col-7"><?= e($violation['owner_name']) ?></dd>
                    <dt class="col-5">Lỗi</dt><dd class="col-7"><?= e($violation['type_name']) ?></dd>
                    <dt class="col-5">Thời gian</dt><dd class="col-7"><?= fmt_datetime($violation['violation_time']) ?></dd>
                    <dt class="col-5">Địa điểm</dt><dd class="col-7"><?= e($violation['location']) ?></dd>
                    <dt class="col-5">Mô tả</dt><dd class="col-7"><?= e($violation['description']) ?></dd>
                    <dt class="col-5">Tiền phạt</dt><dd class="col-7"><?= money($violation['fine_amount']) ?></dd>
                    <dt class="col-5">Hạn nộp</dt><dd class="col-7"><?= fmt_date($violation['due_date']) ?></dd>
                    <dt class="col-5">Cán bộ lập</dt><dd class="col-7"><?= e($violation['officer_name']) ?></dd>
                </dl>
                <?php if ($violation['evidence_image']): ?>
                    <img src="<?= url($violation['evidence_image']) ?>" class="img-fluid rounded mt-2">
                <?php endif; ?>
                <a href="violation_form.php?id=<?= $violation['id'] ?>" class="btn btn-sm btn-outline-dark mt-3">Mở biên bản</a>
            </div>
        </div>
        <?php if ($history): ?>
            <div class="card card-app">
                <div class="card-header">Khiếu nại khác của biên bản này</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($history as $h): ?>
                        <li class="list-group-item small"><a href="?id=<?= $h['id'] ?>"><?= e($h['code']) ?></a> <?= badge($h['status'], complaint_statuses()) ?> - <?= fmt_date($h['created_at']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
