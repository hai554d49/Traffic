<?php
require_once __DIR__ . '/../includes/init.php';
require_staff();

$id = (int) input('id');
$violation = $id ? fetch_one('SELECT * FROM violations WHERE id = ?', [$id]) : null;
if ($id && !$violation) {
    flash('danger', 'Không tìm thấy vi phạm.');
    redirect('admin/violations.php');
}
$isEdit = (bool) $violation;
$deadlineDays = (int) setting('payment_deadline_days', 10);

$vehicles = fetch_all('SELECT id, plate_number, owner_name, vehicle_type FROM vehicles ORDER BY plate_number');
$types = fetch_all('SELECT * FROM violation_types ORDER BY vehicle_type, name');

$errors = [];
$form = $violation ?: [
    'vehicle_id' => (int) input('vehicle_id'), 'violation_type_id' => '', 'violation_time' => date('Y-m-d H:i'),
    'location' => '', 'description' => '', 'fine_amount' => '', 'due_date' => date('Y-m-d', strtotime("+$deadlineDays days")),
    'status' => 'chua_nop', 'evidence_image' => null,
];

if (is_post()) {
    verify_csrf();
    $form = array_merge($form, [
        'vehicle_id'        => (int) input('vehicle_id'),
        'violation_type_id' => (int) input('violation_type_id'),
        'violation_time'    => str_replace('T', ' ', input('violation_time')),
        'location'          => input('location'),
        'description'       => input('description'),
        'fine_amount'       => (int) preg_replace('/\D/', '', input('fine_amount')),
        'due_date'          => input('due_date'),
    ]);
    if ($isEdit && $violation['status'] !== 'da_nop' && in_array(input('status'), ['chua_nop', 'khieu_nai', 'da_huy'], true)) {
        $form['status'] = input('status');
    }

    if (!fetch_value('SELECT COUNT(*) FROM vehicles WHERE id = ?', [$form['vehicle_id']])) {
        $errors[] = 'Vui lòng chọn phương tiện.';
    }
    if (!fetch_value('SELECT COUNT(*) FROM violation_types WHERE id = ?', [$form['violation_type_id']])) {
        $errors[] = 'Vui lòng chọn lỗi vi phạm.';
    }
    if (!strtotime($form['violation_time'])) {
        $errors[] = 'Thời gian vi phạm không hợp lệ.';
    } elseif (strtotime($form['violation_time']) > time()) {
        $errors[] = 'Thời gian vi phạm không được ở tương lai.';
    }
    if ($form['location'] === '') {
        $errors[] = 'Vui lòng nhập địa điểm vi phạm.';
    }
    if ($form['fine_amount'] <= 0) {
        $errors[] = 'Số tiền phạt phải lớn hơn 0.';
    }
    if (!strtotime($form['due_date'])) {
        $errors[] = 'Hạn nộp phạt không hợp lệ.';
    } elseif (strtotime($form['violation_time']) && $form['due_date'] < date('Y-m-d', strtotime($form['violation_time']))) {
        $errors[] = 'Hạn nộp phạt phải sau ngày vi phạm.';
    }

    if (!$errors) {
        try {
            $image = upload_file('evidence_image', 'evidence', ['jpg', 'jpeg', 'png', 'webp']);
            if ($image) {
                if ($form['evidence_image'] && is_file(dirname(__DIR__) . '/' . $form['evidence_image'])) {
                    @unlink(dirname(__DIR__) . '/' . $form['evidence_image']);
                }
                $form['evidence_image'] = $image;
            }
        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $values = [$form['vehicle_id'], $form['violation_type_id'], $form['violation_time'], $form['location'], $form['description'] ?: null,
            $form['fine_amount'], $form['due_date'], $form['evidence_image']];
        if ($isEdit) {
            query('UPDATE violations SET vehicle_id = ?, violation_type_id = ?, violation_time = ?, location = ?, description = ?,
                   fine_amount = ?, due_date = ?, evidence_image = ?, status = ? WHERE id = ?', array_merge($values, [$form['status'], $id]));
            log_activity('Sửa vi phạm', $violation['code']);
            flash('success', 'Đã cập nhật biên bản ' . e($violation['code']) . '.');
        } else {
            $code = generate_code('VP', 'violations');
            query('INSERT INTO violations (vehicle_id, violation_type_id, violation_time, location, description, fine_amount, due_date,
                   evidence_image, code, officer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$code, current_user()['id']]));
            log_activity('Thêm vi phạm', $code);
            flash('success', 'Đã lập biên bản <b>' . e($code) . '</b>. <a href="' . url('notice.php?code=' . $code) . '" target="_blank">In thông báo vi phạm</a>');
        }
        redirect('admin/violations.php');
    }
}

$complaints = $isEdit ? fetch_all('SELECT * FROM complaints WHERE violation_id = ? ORDER BY created_at DESC', [$id]) : [];
$payments = $isEdit ? fetch_all('SELECT * FROM payments WHERE violation_id = ? ORDER BY created_at DESC', [$id]) : [];

$pageTitle = $isEdit ? 'Sửa biên bản ' . $violation['code'] : 'Lập biên bản vi phạm';
$activeMenu = 'violations';
require __DIR__ . '/includes/header.php';
?>
<div class="row">
    <div class="col-lg-8">
        <div class="card card-app">
            <div class="card-header"><?= $isEdit ? 'Thông tin biên bản ' . e($violation['code']) : 'Lập biên bản mới' ?></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Phương tiện *</label>
                            <select name="vehicle_id" id="vehicle_id" class="form-control" required>
                                <option value="">-- Chọn biển số --</option>
                                <?php foreach ($vehicles as $vh): ?>
                                    <option value="<?= $vh['id'] ?>" data-type="<?= $vh['vehicle_type'] ?>" <?= (int) $form['vehicle_id'] === (int) $vh['id'] ? 'selected' : '' ?>>
                                        <?= e($vh['plate_number']) ?> - <?= e($vh['owner_name']) ?> (<?= vehicle_type_label($vh['vehicle_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small><a href="vehicle_form.php" target="_blank">+ Thêm phương tiện mới</a></small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Thời gian vi phạm *</label>
                            <input type="datetime-local" name="violation_time" id="violation_time" class="form-control" required
                                   value="<?= e(date('Y-m-d\TH:i', strtotime($form['violation_time']))) ?>">
                        </div>
                        <div class="form-group col-md-12">
                            <label>Lỗi vi phạm *</label>
                            <select name="violation_type_id" id="violation_type_id" class="form-control" required>
                                <option value="">-- Chọn lỗi --</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>" data-fine="<?= $t['fine_amount'] ?>" data-type="<?= $t['vehicle_type'] ?>"
                                        <?= (int) $form['violation_type_id'] === (int) $t['id'] ? 'selected' : '' ?>>
                                        [<?= vehicle_type_label($t['vehicle_type']) ?>] <?= e($t['name']) ?> - <?= money($t['fine_amount']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-12">
                            <label>Địa điểm *</label>
                            <input name="location" class="form-control" value="<?= e($form['location']) ?>" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Số tiền phạt (VNĐ) *</label>
                            <input name="fine_amount" id="fine_amount" type="number" min="1000" step="1000" class="form-control" value="<?= e($form['fine_amount']) ?>" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Hạn nộp phạt *</label>
                            <input type="date" name="due_date" id="due_date" class="form-control" value="<?= e($form['due_date']) ?>" required>
                            <small class="text-muted">Mặc định <?= $deadlineDays ?> ngày sau ngày vi phạm</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Trạng thái</label>
                            <?php if ($isEdit && $violation['status'] !== 'da_nop'): ?>
                                <select name="status" class="form-control">
                                    <?php foreach (['chua_nop', 'khieu_nai', 'da_huy'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= violation_statuses()[$s][0] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="pt-2"><?= badge($form['status'], violation_statuses()) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group col-md-12">
                            <label>Mô tả chi tiết</label>
                            <textarea name="description" rows="3" class="form-control"><?= e($form['description']) ?></textarea>
                        </div>
                        <div class="form-group col-md-12">
                            <label>Ảnh vi phạm (camera / hiện trường)</label>
                            <input type="file" name="evidence_image" class="form-control-file" accept="image/*">
                            <?php if ($form['evidence_image']): ?>
                                <img src="<?= url($form['evidence_image']) ?>" class="img-thumbnail mt-2" style="max-height:160px">
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-app"><i class="fa fa-save"></i> Lưu</button>
                    <a href="violations.php" class="btn btn-secondary">Quay lại</a>
                    <?php if ($isEdit): ?>
                        <a href="<?= url('notice.php?code=' . urlencode($violation['code'])) ?>" target="_blank" class="btn btn-outline-dark"><i class="fa fa-print"></i> In thông báo</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <?php if ($isEdit): $f = calc_fine($violation); ?>
            <div class="card card-app">
                <div class="card-header">Số tiền phải nộp hôm nay</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($f['fine']) ?></b></div>
                    <div class="d-flex justify-content-between"><span>Chậm nộp (<?= $f['late_days'] ?> ngày × <?= $f['rate'] ?>%)</span><b class="text-danger"><?= money($f['late_fee']) ?></b></div>
                    <hr>
                    <div class="d-flex justify-content-between"><span>Tổng</span><b class="h5 mb-0"><?= money($f['total']) ?></b></div>
                </div>
            </div>
            <div class="card card-app">
                <div class="card-header">Khiếu nại (<?= count($complaints) ?>)</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($complaints as $c): ?>
                        <li class="list-group-item"><a href="complaint_view.php?id=<?= $c['id'] ?>"><?= e($c['code']) ?></a>
                            <?= badge($c['status'], complaint_statuses()) ?><br><small class="text-muted"><?= fmt_datetime($c['created_at']) ?></small></li>
                    <?php endforeach; ?>
                    <?php if (!$complaints): ?><li class="list-group-item text-muted">Không có</li><?php endif; ?>
                </ul>
            </div>
            <div class="card card-app">
                <div class="card-header">Giao dịch thanh toán (<?= count($payments) ?>)</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($payments as $p): ?>
                        <li class="list-group-item"><?= e($p['transaction_code']) ?> <?= badge($p['status'], payment_statuses()) ?><br>
                            <small><?= money($p['total_amount']) ?> · <?= e(payment_methods()[$p['method']]) ?></small></li>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?><li class="list-group-item text-muted">Chưa có</li><?php endif; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="card card-app">
                <div class="card-header">Hướng dẫn</div>
                <div class="card-body small">
                    <p>Chọn lỗi vi phạm để tự điền mức phạt. Hạn nộp được tự tính = ngày vi phạm + <?= $deadlineDays ?> ngày.</p>
                    <p class="mb-0">Sau khi lưu, hệ thống sinh mã biên bản và mã QR thanh toán trên thông báo vi phạm gửi cho chủ xe.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php ob_start(); ?>
<script>
    $(function () {
        var deadline = <?= $deadlineDays ?>;
        // Chọn lỗi -> tự điền mức phạt
        $('#violation_type_id').on('change', function () {
            var fine = $(this).find(':selected').data('fine');
            if (fine) $('#fine_amount').val(fine);
        });
        // Chọn xe -> chỉ hiện các lỗi áp dụng cho loại xe đó
        $('#vehicle_id').on('change', function () {
            var type = $(this).find(':selected').data('type');
            $('#violation_type_id option[data-type]').each(function () {
                var t = $(this).data('type');
                $(this).toggle(!type || t === 'tat_ca' || t === type);
            });
        }).trigger('change');
        // Đổi ngày vi phạm -> tự tính hạn nộp
        $('#violation_time').on('change', function () {
            if (!this.value) return;
            var d = new Date(this.value);
            d.setDate(d.getDate() + deadline);
            $('#due_date').val(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'));
        });
    });
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
