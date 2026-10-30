<?php
/**
 * Gửi khiếu nại cho một biên bản vi phạm.
 */
require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$v = find_violation_by_code(input('code'));
if (!$v) {
    flash('warning', 'Không tìm thấy biên bản vi phạm.');
    redirect('my_complaints.php');
}
if (!in_array($v['status'], ['chua_nop', 'khieu_nai'], true)) {
    flash('warning', 'Biên bản đã ' . mb_strtolower(violation_statuses()[$v['status']][0]) . ', không thể khiếu nại.');
    redirect('violation.php?code=' . urlencode($v['code']));
}
if (fetch_value("SELECT COUNT(*) FROM complaints WHERE violation_id = ? AND user_id = ? AND status = 'cho_xu_ly'", [$v['id'], $user['id']])) {
    flash('info', 'Bạn đã có một khiếu nại đang chờ xử lý cho biên bản này.');
    redirect('my_complaints.php');
}

$errors = [];
if (is_post()) {
    verify_csrf();
    $name = input('complainant_name');
    $phone = input('complainant_phone');
    $content = input('content');

    if ($name === '') {
        $errors[] = 'Vui lòng nhập họ tên người khiếu nại.';
    }
    if (!preg_match('/^0\d{9,10}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }
    if (mb_strlen($content) < 20) {
        $errors[] = 'Nội dung khiếu nại cần tối thiểu 20 ký tự, trình bày rõ lý do.';
    }
    if (empty($_POST['agree'])) {
        $errors[] = 'Vui lòng cam kết thông tin khiếu nại là đúng sự thật.';
    }
    $file = null;
    if (!$errors) {
        try {
            $file = upload_file('evidence_file', 'complaints');
        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $code = generate_code('KN', 'complaints');
        query('INSERT INTO complaints (code, violation_id, user_id, complainant_name, complainant_phone, content, evidence_file) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$code, $v['id'], $user['id'], $name, $phone, $content, $file]);
        // Đang khiếu nại: tạm dừng thu phạt và tính lãi
        if ($v['status'] === 'chua_nop') {
            query("UPDATE violations SET status = 'khieu_nai' WHERE id = ?", [$v['id']]);
            query("UPDATE payments SET status = 'het_han' WHERE violation_id = ? AND status = 'cho_thanh_toan'", [$v['id']]);
        }
        log_activity('Gửi khiếu nại', $code . ' - ' . $v['code']);
        flash('success', 'Đã gửi khiếu nại <b>' . $code . '</b>. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
        redirect('my_complaints.php');
    }
}

$pageTitle = 'Gửi khiếu nại';
$activeMenu = 'complaints';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Gửi khiếu nại</h1>
        <p>Biên bản <?= e($v['code']) ?> · <span class="plate"><?= e($v['plate_number']) ?></span></p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="card card-app">
                    <div class="card-header">Nội dung khiếu nại</div>
                    <div class="card-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Họ tên người khiếu nại *</label>
                                    <input name="complainant_name" class="form-control" value="<?= old('complainant_name', $user['full_name']) ?>" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Số điện thoại liên hệ *</label>
                                    <input name="complainant_phone" class="form-control" value="<?= old('complainant_phone', $user['phone']) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Lý do khiếu nại *</label>
                                <textarea name="content" rows="6" class="form-control" required
                                          placeholder="Trình bày cụ thể: thời điểm đó xe ở đâu, ai điều khiển, sai sót trong biên bản..."><?= old('content') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Tài liệu / hình ảnh minh chứng (JPG, PNG, PDF ≤ 5MB)</label>
                                <input type="file" name="evidence_file" class="form-control-file" accept="image/*,.pdf">
                            </div>
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="agree" name="agree" value="1">
                                <label class="custom-control-label" for="agree">Tôi cam kết nội dung khiếu nại là đúng sự thật và chịu trách nhiệm trước pháp luật.</label>
                            </div>
                            <button class="btn btn-app"><i class="fa fa-paper-plane"></i> Gửi khiếu nại</button>
                            <a href="violation.php?code=<?= urlencode($v['code']) ?>" class="btn btn-secondary">Hủy</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card card-app">
                    <div class="card-header">Biên bản bị khiếu nại</div>
                    <div class="card-body small">
                        <p class="mb-1"><b><?= e($v['type_name']) ?></b></p>
                        <p class="mb-1"><?= fmt_datetime($v['violation_time']) ?> - <?= e($v['location']) ?></p>
                        <p class="mb-0">Tiền phạt: <b><?= money($v['fine_amount']) ?></b> · Hạn nộp: <?= fmt_date($v['due_date']) ?></p>
                    </div>
                </div>
                <div class="alert alert-info small">
                    <b>Lưu ý:</b> Trong thời gian chờ xử lý khiếu nại, biên bản tạm dừng thu phạt và <b>không tính tiền chậm nộp</b>.
                    Nếu khiếu nại bị từ chối, hạn nộp được gia hạn thêm đúng số ngày đã chờ xử lý.
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
