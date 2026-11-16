<?php
/**
 * QUẢN LÝ VI PHẠM (biên bản)
 *   GET  violations.php                 -> show_list()          lọc theo từ khóa / trạng thái / ngày + phân trang
 *   GET  violations.php?export=1        -> export_csv()         tải file CSV theo bộ lọc hiện tại
 *   GET  violations.php?new=1           -> show_form()          lập biên bản mới
 *   GET  violations.php?id=5            -> show_form()          sửa biên bản
 *   POST action=save                    -> save_violation()     kiểm tra -> upload ảnh -> INSERT / UPDATE
 *   POST action=delete                  -> delete_violation()   chỉ admin; khiếu nại + giao dịch bị xóa theo (CASCADE)
 *   POST action=cash                    -> collect_cash()       ghi nhận nộp tiền mặt -> complete_payment()
 */
require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../layout.php';
require_staff();

$errors = [];
if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'save') $errors = save_violation();
    if ($action === 'delete') delete_violation();
    if ($action === 'cash') collect_cash();
}

if (input('export') !== '') {
    export_csv();
} elseif ($errors || input('new') !== '' || input('id') !== '') {
    show_form($errors);
} else {
    show_list();
}

/* ======================= XỬ LÝ ======================= */

function save_violation()
{
    $id = (int) input('id');
    $old = $id ? fetch_one('SELECT * FROM violations WHERE id = ?', [$id]) : null;
    $d = [
        'vehicle_id'        => (int) input('vehicle_id'),
        'violation_type_id' => (int) input('violation_type_id'),
        'violation_time'    => str_replace('T', ' ', input('violation_time')),  // datetime-local gửi "2026-11-15T08:30"
        'location'          => input('location'),
        'description'       => input('description'),
        'fine_amount'       => (int) input('fine_amount'),
        'due_date'          => input('due_date'),
    ];
    // Trạng thái chỉ sửa tay được giữa chưa nộp / khiếu nại / hủy; "đã nộp" chỉ do thanh toán tạo ra
    $status = $old ? $old['status'] : 'chua_nop';
    if ($old && $old['status'] !== 'da_nop' && in_array(input('status'), ['chua_nop', 'khieu_nai', 'da_huy'], true)) {
        $status = input('status');
    }

    $errors = [];
    if (!fetch_value('SELECT COUNT(*) FROM vehicles WHERE id = ?', [$d['vehicle_id']])) $errors[] = 'Vui lòng chọn phương tiện.';
    if (!fetch_value('SELECT COUNT(*) FROM violation_types WHERE id = ?', [$d['violation_type_id']])) $errors[] = 'Vui lòng chọn lỗi vi phạm.';
    $time = strtotime($d['violation_time']);
    if (!$time) {
        $errors[] = 'Thời gian vi phạm không hợp lệ.';
    } elseif ($time > time()) {
        $errors[] = 'Thời gian vi phạm không được ở tương lai.';
    }
    if ($d['location'] === '') $errors[] = 'Vui lòng nhập địa điểm vi phạm.';
    if ($d['fine_amount'] <= 0) $errors[] = 'Số tiền phạt phải lớn hơn 0.';
    if (!strtotime($d['due_date'])) {
        $errors[] = 'Hạn nộp phạt không hợp lệ.';
    } elseif ($time && $d['due_date'] < date('Y-m-d', $time)) {
        $errors[] = 'Hạn nộp phạt phải sau ngày vi phạm.';
    }
    if ($errors) return $errors;

    try {
        $image = upload_file('evidence_image', 'evidence', ['jpg', 'jpeg', 'png', 'webp']);
    } catch (Exception $ex) {
        return [$ex->getMessage()];
    }
    if ($image && $old) {
        delete_upload($old['evidence_image']); // thay ảnh mới thì xóa ảnh cũ
    }
    $d['evidence_image'] = $image ?: ($old['evidence_image'] ?? null);
    $values = [$d['vehicle_id'], $d['violation_type_id'], $d['violation_time'], $d['location'], $d['description'] ?: null,
        $d['fine_amount'], $d['due_date'], $d['evidence_image']];

    if ($old) {
        query('UPDATE violations SET vehicle_id = ?, violation_type_id = ?, violation_time = ?, location = ?, description = ?,
               fine_amount = ?, due_date = ?, evidence_image = ?, status = ? WHERE id = ?', array_merge($values, [$status, $id]));
        log_activity('Sửa vi phạm', $old['code']);
        flash('success', 'Đã cập nhật biên bản ' . e($old['code']) . '.');
    } else {
        $code = generate_code('VP', 'violations');
        query('INSERT INTO violations (vehicle_id, violation_type_id, violation_time, location, description, fine_amount, due_date,
               evidence_image, code, officer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$code, current_user()['id']]));
        log_activity('Thêm vi phạm', $code);
        flash('success', 'Đã lập biên bản <b>' . e($code) . '</b>. <a href="' . url('violation.php?print=1&code=' . $code) . '" target="_blank">In thông báo vi phạm</a>');
    }
    redirect('admin/violations.php');
}

function delete_violation()
{
    $v = fetch_one('SELECT * FROM violations WHERE id = ?', [(int) input('id')]);
    if (!is_admin()) {
        flash('danger', 'Chỉ quản trị viên được xóa biên bản.');
    } elseif ($v) {
        query('DELETE FROM violations WHERE id = ?', [$v['id']]);
        delete_upload($v['evidence_image']);
        log_activity('Xóa vi phạm', $v['code']);
        flash('success', 'Đã xóa biên bản ' . e($v['code']) . '.');
    }
    redirect('admin/violations.php');
}

/** Thu tiền mặt tại trụ sở: tạo giao dịch tiền mặt rồi dùng chung complete_payment() như các kênh khác */
function collect_cash()
{
    $v = fetch_one('SELECT * FROM violations WHERE id = ?', [(int) input('id')]);
    if (!$v || $v['status'] !== 'chua_nop') {
        flash('warning', 'Biên bản không ở trạng thái chờ nộp phạt.');
        redirect('admin/violations.php');
    }
    $paymentId = create_payment($v, 'tien_mat', 'cho_xac_nhan', ['note' => 'Nộp trực tiếp tại trụ sở']);
    complete_payment($paymentId, current_user()['id']);
    flash('success', 'Đã ghi nhận nộp tiền mặt ' . money(calc_fine($v)['total']) . ' cho biên bản ' . e($v['code']) . '.');
    redirect('admin/violations.php');
}

/* ======================= DANH SÁCH & XUẤT CSV ======================= */

/** Ghép điều kiện lọc từ URL thành [chuỗi WHERE, mảng tham số] - dùng chung cho danh sách và xuất CSV */
function build_filter()
{
    $where = ['1=1'];
    $params = [];
    if (($q = input('q')) !== '') {
        $where[] = '(v.code LIKE ? OR ' . sql_plate('vh.plate_number') . ' LIKE ? OR vh.owner_name LIKE ? OR v.location LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, '%' . normalize_plate($q) . '%', $like, $like);
    }
    $status = input('status');
    if ($status === 'qua_han') {
        $where[] = "v.status = 'chua_nop' AND v.due_date < CURDATE()";
    } elseif (isset(violation_statuses()[$status])) {
        $where[] = 'v.status = ?';
        $params[] = $status;
    }
    if (input('from') !== '') {
        $where[] = 'DATE(v.violation_time) >= ?';
        $params[] = input('from');
    }
    if (input('to') !== '') {
        $where[] = 'DATE(v.violation_time) <= ?';
        $params[] = input('to');
    }
    $sql = 'FROM violations v JOIN vehicles vh ON vh.id = v.vehicle_id JOIN violation_types vt ON vt.id = v.violation_type_id WHERE ' . implode(' AND ', $where);
    return [$sql, $params];
}

function export_csv()
{
    [$sql, $params] = build_filter();
    $rows = fetch_all("SELECT v.*, vh.plate_number, vh.owner_name, vh.owner_cccd, vt.name AS type_name $sql ORDER BY v.violation_time DESC", $params);
    log_activity('Xuất CSV vi phạm', count($rows) . ' dòng');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vi-pham-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel hiển thị đúng tiếng Việt
    fputcsv($out, ['Mã biên bản', 'Biển số', 'Chủ xe', 'CCCD', 'Lỗi vi phạm', 'Thời gian', 'Địa điểm', 'Tiền phạt', 'Hạn nộp', 'Số ngày quá hạn', 'Tiền chậm nộp', 'Tổng phải nộp', 'Trạng thái']);
    foreach ($rows as $v) {
        $f = calc_fine($v);
        fputcsv($out, [$v['code'], $v['plate_number'], $v['owner_name'], $v['owner_cccd'], $v['type_name'], fmt_datetime($v['violation_time']), $v['location'],
            $f['fine'], fmt_date($v['due_date']), $f['late_days'], $f['late_fee'], $f['total'], violation_statuses()[$v['status']][0]]);
    }
    fclose($out);
    exit;
}

function show_list()
{
    [$sql, $params] = build_filter();
    $pg = paginate((int) fetch_value("SELECT COUNT(*) $sql", $params), 10);
    $rows = fetch_all("SELECT v.*, vh.plate_number, vh.owner_name, vt.name AS type_name $sql
                       ORDER BY v.violation_time DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
    $status = input('status');

    admin_header('Quản lý vi phạm', 'violations');
    ?>
    <form class="filter-bar form-row align-items-end" method="get">
        <div class="col-md-3 mb-2"><label class="small mb-1">Từ khóa</label><input name="q" class="form-control" value="<?= e(input('q')) ?>" placeholder="Mã, biển số, chủ xe, địa điểm"></div>
        <div class="col-md-2 mb-2"><label class="small mb-1">Trạng thái</label>
            <select name="status" class="form-control"><option value="">Tất cả</option>
                <?php foreach (violation_statuses() as $k => $s): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $s[0] ?></option><?php endforeach; ?>
                <option value="qua_han" <?= $status === 'qua_han' ? 'selected' : '' ?>>Quá hạn nộp</option>
            </select></div>
        <div class="col-md-2 mb-2"><label class="small mb-1">Từ ngày</label><input type="date" name="from" class="form-control" value="<?= e(input('from')) ?>"></div>
        <div class="col-md-2 mb-2"><label class="small mb-1">Đến ngày</label><input type="date" name="to" class="form-control" value="<?= e(input('to')) ?>"></div>
        <div class="col-md-3 mb-2">
            <button class="btn btn-dark"><i class="fa fa-search"></i> Lọc</button>
            <a href="?<?= e(http_build_query(array_merge($_GET, ['export' => 1]))) ?>" class="btn btn-outline-success"><i class="fa fa-file-excel-o"></i> CSV</a>
            <a href="violations.php?new=1" class="btn btn-app"><i class="fa fa-plus"></i> Thêm</a>
        </div>
    </form>
    <div class="table-wrap"><table class="table table-hover">
        <thead><tr><th>Mã biên bản</th><th>Biển số / Chủ xe</th><th>Lỗi vi phạm</th><th>Thời gian</th><th>Hạn nộp</th><th class="text-right">Phải nộp</th><th>Trạng thái</th><th class="text-center" style="width:170px">Thao tác</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Không có dữ liệu.</td></tr><?php endif; ?>
        <?php foreach ($rows as $v): $f = calc_fine($v); ?>
            <tr>
                <td><b><?= e($v['code']) ?></b></td>
                <td><span class="plate"><?= e($v['plate_number']) ?></span><br><small class="text-muted"><?= e($v['owner_name']) ?></small></td>
                <td style="max-width:260px"><?= e($v['type_name']) ?><br><small class="text-muted"><?= e($v['location']) ?></small></td>
                <td><?= fmt_datetime($v['violation_time']) ?></td>
                <td><?= fmt_date($v['due_date']) ?><?php if ($f['overdue']): ?><br><small class="text-danger">Quá <?= $f['late_days'] ?> ngày</small><?php endif; ?></td>
                <td class="text-right"><?= money($f['total']) ?><?php if ($f['late_fee']): ?><br><small class="text-danger">+<?= money($f['late_fee']) ?> lãi</small><?php endif; ?></td>
                <td><?= badge($v['status'], violation_statuses()) ?></td>
                <td class="text-center actions">
                    <a href="<?= url('violation.php?code=' . urlencode($v['code'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem"><i class="fa fa-eye"></i></a>
                    <a href="violations.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="fa fa-pencil"></i></a>
                    <?php if ($v['status'] === 'chua_nop'): ?>
                        <form method="post" class="d-inline" data-confirm="Xác nhận đã thu tiền mặt <?= money($f['total']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="action" value="cash">
                            <button class="btn btn-sm btn-outline-success" title="Thu tiền mặt"><i class="fa fa-money"></i></button>
                        </form>
                    <?php endif; ?>
                    <?php if (is_admin()): ?>
                        <form method="post" class="d-inline" data-confirm="Xóa biên bản <?= e($v['code']) ?>? Các khiếu nại và giao dịch liên quan cũng bị xóa.">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="action" value="delete">
                            <button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fa fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="d-flex justify-content-between align-items-center mt-3"><small class="text-muted">Tổng: <?= $pg['total'] ?> biên bản</small><?= render_pagination($pg) ?></div>
    <?php
    admin_footer();
}

/* ======================= FORM THÊM / SỬA ======================= */

function show_form(array $errors)
{
    $id = (int) input('id');
    $v = $id ? fetch_one('SELECT * FROM violations WHERE id = ?', [$id]) : null;
    if ($id && !$v) {
        flash('danger', 'Không tìm thấy vi phạm.');
        redirect('admin/violations.php');
    }
    $deadline = (int) setting('payment_deadline_days', 10);
    $form = $v ?: ['vehicle_id' => (int) input('vehicle_id'), 'violation_type_id' => '', 'violation_time' => date('Y-m-d H:i'), 'location' => '',
        'description' => '', 'fine_amount' => '', 'due_date' => date('Y-m-d', strtotime("+$deadline days")), 'status' => 'chua_nop', 'evidence_image' => null];
    if ($errors) {
        $form = array_merge($form, $_POST); // giữ lại dữ liệu vừa gõ
    }
    $vehicles = fetch_all('SELECT id, plate_number, owner_name, vehicle_type FROM vehicles ORDER BY plate_number');
    $types = fetch_all('SELECT * FROM violation_types ORDER BY vehicle_type, name');
    $complaints = $v ? fetch_all('SELECT * FROM complaints WHERE violation_id = ? ORDER BY created_at DESC', [$id]) : [];
    $payments = $v ? fetch_all('SELECT * FROM payments WHERE violation_id = ? ORDER BY created_at DESC', [$id]) : [];

    admin_header($v ? 'Sửa biên bản ' . $v['code'] : 'Lập biên bản vi phạm', 'violations');
    ?>
    <div class="row">
        <div class="col-lg-8"><div class="card card-app">
            <div class="card-header"><?= $v ? 'Thông tin biên bản ' . e($v['code']) : 'Lập biên bản mới' ?></div>
            <div class="card-body">
                <?= render_errors($errors) ?>
                <form method="post" action="violations.php" enctype="multipart/form-data">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $id ?>">
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Phương tiện *</label>
                            <select name="vehicle_id" id="vehicle_id" class="form-control" required><option value="">-- Chọn biển số --</option>
                                <?php foreach ($vehicles as $vh): ?>
                                    <option value="<?= $vh['id'] ?>" data-type="<?= $vh['vehicle_type'] ?>" <?= (int) $form['vehicle_id'] === (int) $vh['id'] ? 'selected' : '' ?>>
                                        <?= e($vh['plate_number']) ?> - <?= e($vh['owner_name']) ?> (<?= vehicle_type_label($vh['vehicle_type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small><a href="vehicles.php?new=1" target="_blank">+ Thêm phương tiện mới</a></small></div>
                        <div class="form-group col-md-6"><label>Thời gian vi phạm *</label>
                            <input type="datetime-local" name="violation_time" id="violation_time" class="form-control" required value="<?= e(date('Y-m-d\TH:i', strtotime(str_replace('T', ' ', $form['violation_time'])))) ?>"></div>
                        <div class="form-group col-md-12"><label>Lỗi vi phạm *</label>
                            <select name="violation_type_id" id="violation_type_id" class="form-control" required><option value="">-- Chọn lỗi --</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>" data-fine="<?= $t['fine_amount'] ?>" data-type="<?= $t['vehicle_type'] ?>" <?= (int) $form['violation_type_id'] === (int) $t['id'] ? 'selected' : '' ?>>
                                        [<?= vehicle_type_label($t['vehicle_type']) ?>] <?= e($t['name']) ?> - <?= money($t['fine_amount']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="form-group col-md-12"><label>Địa điểm *</label><input name="location" class="form-control" value="<?= e($form['location']) ?>" required></div>
                        <div class="form-group col-md-4"><label>Số tiền phạt (VNĐ) *</label><input name="fine_amount" id="fine_amount" type="number" min="1000" step="1000" class="form-control" value="<?= e($form['fine_amount']) ?>" required></div>
                        <div class="form-group col-md-4"><label>Hạn nộp phạt *</label><input type="date" name="due_date" id="due_date" class="form-control" value="<?= e($form['due_date']) ?>" required>
                            <small class="text-muted">Mặc định <?= $deadline ?> ngày sau ngày vi phạm</small></div>
                        <div class="form-group col-md-4"><label>Trạng thái</label>
                            <?php if ($v && $v['status'] !== 'da_nop'): ?>
                                <select name="status" class="form-control">
                                    <?php foreach (['chua_nop', 'khieu_nai', 'da_huy'] as $s): ?><option value="<?= $s ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= violation_statuses()[$s][0] ?></option><?php endforeach; ?>
                                </select>
                            <?php else: ?><div class="pt-2"><?= badge($v['status'] ?? 'chua_nop', violation_statuses()) ?></div><?php endif; ?></div>
                        <div class="form-group col-md-12"><label>Mô tả chi tiết</label><textarea name="description" rows="3" class="form-control"><?= e($form['description']) ?></textarea></div>
                        <div class="form-group col-md-12"><label>Ảnh vi phạm (camera / hiện trường)</label><input type="file" name="evidence_image" class="form-control-file" accept="image/*">
                            <?php if (!empty($v['evidence_image'])): ?><img src="<?= url($v['evidence_image']) ?>" class="img-thumbnail mt-2" style="max-height:160px"><?php endif; ?></div>
                    </div>
                    <button class="btn btn-app"><i class="fa fa-save"></i> Lưu</button>
                    <a href="violations.php" class="btn btn-secondary">Quay lại</a>
                    <?php if ($v): ?><a href="<?= url('violation.php?print=1&code=' . urlencode($v['code'])) ?>" target="_blank" class="btn btn-outline-dark"><i class="fa fa-print"></i> In thông báo</a><?php endif; ?>
                </form>
            </div>
        </div></div>
        <div class="col-lg-4">
            <?php if ($v): $f = calc_fine($v); ?>
                <div class="card card-app"><div class="card-header">Số tiền phải nộp hôm nay</div><div class="card-body">
                    <div class="d-flex justify-content-between"><span>Tiền phạt</span><b><?= money($f['fine']) ?></b></div>
                    <div class="d-flex justify-content-between"><span>Chậm nộp (<?= $f['late_days'] ?> ngày × <?= $f['rate'] ?>%)</span><b class="text-danger"><?= money($f['late_fee']) ?></b></div>
                    <hr><div class="d-flex justify-content-between"><span>Tổng</span><b class="h5 mb-0"><?= money($f['total']) ?></b></div>
                </div></div>
                <div class="card card-app"><div class="card-header">Khiếu nại (<?= count($complaints) ?>)</div><ul class="list-group list-group-flush">
                    <?php foreach ($complaints as $c): ?><li class="list-group-item"><a href="complaints.php?id=<?= $c['id'] ?>"><?= e($c['code']) ?></a> <?= badge($c['status'], complaint_statuses()) ?><br><small class="text-muted"><?= fmt_datetime($c['created_at']) ?></small></li><?php endforeach; ?>
                    <?php if (!$complaints): ?><li class="list-group-item text-muted">Không có</li><?php endif; ?>
                </ul></div>
                <div class="card card-app"><div class="card-header">Giao dịch thanh toán (<?= count($payments) ?>)</div><ul class="list-group list-group-flush">
                    <?php foreach ($payments as $p): ?><li class="list-group-item"><?= e($p['transaction_code']) ?> <?= badge($p['status'], payment_statuses()) ?><br><small><?= money($p['total_amount']) ?> · <?= e(payment_methods()[$p['method']]) ?></small></li><?php endforeach; ?>
                    <?php if (!$payments): ?><li class="list-group-item text-muted">Chưa có</li><?php endif; ?>
                </ul></div>
            <?php else: ?>
                <div class="card card-app"><div class="card-header">Hướng dẫn</div><div class="card-body small">
                    <p>Chọn lỗi vi phạm để tự điền mức phạt. Hạn nộp được tự tính = ngày vi phạm + <?= $deadline ?> ngày.</p>
                    <p class="mb-0">Sau khi lưu, hệ thống sinh mã biên bản và mã QR thanh toán trên thông báo vi phạm gửi cho chủ xe.</p>
                </div></div>
            <?php endif; ?>
        </div>
    </div>
    <?php ob_start(); ?>
    <script>
        $(function () {
            var deadline = <?= $deadline ?>;
            // Chọn lỗi -> tự điền mức phạt
            $('#violation_type_id').on('change', function () { var fine = $(this).find(':selected').data('fine'); if (fine) $('#fine_amount').val(fine); });
            // Chọn xe -> chỉ hiện các lỗi áp dụng cho loại xe đó
            $('#vehicle_id').on('change', function () {
                var type = $(this).find(':selected').data('type');
                $('#violation_type_id option[data-type]').each(function () { var t = $(this).data('type'); $(this).toggle(!type || t === 'tat_ca' || t === type); });
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
    admin_footer(ob_get_clean());
}
