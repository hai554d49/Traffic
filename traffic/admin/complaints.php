<?php
/**
 * XỬ LÝ KHIẾU NẠI
 *   GET  complaints.php?status=cho_xu_ly  -> show_list()         lọc theo trạng thái
 *   GET  complaints.php?id=3              -> show_detail()       nội dung khiếu nại + biên bản + form xử lý
 *   POST action=handle                    -> handle_complaint()  chấp nhận (hủy / giảm phạt) hoặc từ chối, trong 1 transaction
 */
require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../layout.php';
require_staff();

$errors = [];
if (is_post() && input('action') === 'handle') {
    verify_csrf();
    $errors = handle_complaint();
}
input('id') !== '' ? show_detail($errors) : show_list();

/* ======================= XỬ LÝ ======================= */

function find_complaint($id)
{
    return fetch_one('SELECT c.*, u.full_name AS handler_name, v.code AS violation_code FROM complaints c
                      JOIN violations v ON v.id = c.violation_id LEFT JOIN users u ON u.id = c.handled_by WHERE c.id = ?', [$id]);
}

/**
 * Kết quả xử lý:
 *   chấp nhận + hủy        -> biên bản "đã hủy"
 *   chấp nhận + giảm phạt  -> đổi fine_amount, biên bản về "chưa nộp", gia hạn nộp
 *   từ chối                -> biên bản về "chưa nộp", gia hạn nộp
 * Gia hạn = cộng số ngày đã chờ xử lý (vì lúc chờ không tính lãi), tối thiểu còn 3 ngày kể từ hôm nay.
 */
function handle_complaint()
{
    $c = find_complaint((int) input('id'));
    if (!$c || $c['status'] !== 'cho_xu_ly') {
        flash('warning', 'Khiếu nại không tồn tại hoặc đã được xử lý.');
        redirect('admin/complaints.php');
    }
    $v = find_violation_by_code($c['violation_code']);
    $decision = input('decision');
    $adjust = $decision === 'chap_nhan' && input('accept_action') === 'adjust';
    $newFine = (int) input('new_fine');
    $response = input('response');

    $errors = [];
    if (!in_array($decision, ['chap_nhan', 'tu_choi'], true)) $errors[] = 'Vui lòng chọn kết quả xử lý.';
    if ($response === '') $errors[] = 'Vui lòng nhập nội dung trả lời cho người khiếu nại.';
    if ($adjust && ($newFine <= 0 || $newFine >= $v['fine_amount'])) $errors[] = 'Mức phạt điều chỉnh phải lớn hơn 0 và nhỏ hơn mức phạt hiện tại.';
    if ($errors) return $errors;

    $pdo = db();
    $pdo->beginTransaction();
    query('UPDATE complaints SET status = ?, response = ?, handled_by = ?, handled_at = NOW() WHERE id = ?', [$decision, $response, current_user()['id'], $c['id']]);

    $pendingDays = (int) (new DateTime($c['created_at']))->diff(new DateTime())->days;
    $otherPending = fetch_value("SELECT COUNT(*) FROM complaints WHERE violation_id = ? AND status = 'cho_xu_ly'", [$v['id']]);
    $extendSql = 'due_date = GREATEST(DATE_ADD(due_date, INTERVAL ? DAY), CURDATE() + INTERVAL 3 DAY)';
    $result = '';
    if ($decision === 'chap_nhan' && !$adjust) {
        query("UPDATE violations SET status = 'da_huy' WHERE id = ?", [$v['id']]);
        $result = 'Biên bản đã được hủy.';
    } elseif ($adjust) {
        query("UPDATE violations SET fine_amount = ?, status = IF(status = 'khieu_nai' AND ? = 0, 'chua_nop', status), $extendSql WHERE id = ?",
            [$newFine, $otherPending, $pendingDays, $v['id']]);
        $result = 'Mức phạt điều chỉnh còn ' . money($newFine) . ', hạn nộp được gia hạn.';
    } elseif ($v['status'] === 'khieu_nai' && !$otherPending) {
        query("UPDATE violations SET status = 'chua_nop', $extendSql WHERE id = ?", [$pendingDays, $v['id']]);
        $result = 'Biên bản trở lại chờ nộp phạt, hạn nộp được gia hạn thêm ' . $pendingDays . ' ngày.';
    }
    $pdo->commit();

    log_activity('Xử lý khiếu nại', $c['code'] . ' - ' . complaint_statuses()[$decision][0]);
    flash('success', 'Đã xử lý khiếu nại ' . e($c['code']) . '. ' . $result);
    redirect('admin/complaints.php');
}

/* ======================= GIAO DIỆN ======================= */

function show_list()
{
    $status = input('status', 'cho_xu_ly');
    $where = '1=1';
    $params = [];
    if (isset(complaint_statuses()[$status])) {
        $where = 'c.status = ?';
        $params[] = $status;
    }
    $pg = paginate((int) fetch_value("SELECT COUNT(*) FROM complaints c WHERE $where", $params), 10);
    $rows = fetch_all("SELECT c.*, v.code AS violation_code, vh.plate_number FROM complaints c
        JOIN violations v ON v.id = c.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
        WHERE $where ORDER BY c.created_at DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
    $counts = [];
    foreach (fetch_all('SELECT status, COUNT(*) AS c FROM complaints GROUP BY status') as $r) {
        $counts[$r['status']] = $r['c'];
    }

    admin_header('Xử lý khiếu nại', 'complaints');
    ?>
    <ul class="nav nav-pills mb-3">
        <?php foreach (complaint_statuses() + ['all' => ['Tất cả', '']] as $k => $s): ?>
            <li class="nav-item"><a class="nav-link <?= $status === $k ? 'active bg-dark' : 'text-dark' ?>" href="?status=<?= $k ?>"><?= $s[0] ?>
                <?php if (!empty($counts[$k])): ?><span class="badge badge-light"><?= $counts[$k] ?></span><?php endif; ?></a></li>
        <?php endforeach; ?>
    </ul>
    <div class="table-wrap"><table class="table table-hover">
        <thead><tr><th>Mã KN</th><th>Biên bản</th><th>Người khiếu nại</th><th>Nội dung</th><th>Ngày gửi</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Không có khiếu nại.</td></tr><?php endif; ?>
        <?php foreach ($rows as $c): ?>
            <tr>
                <td><b><?= e($c['code']) ?></b></td>
                <td><?= e($c['violation_code']) ?><br><span class="plate"><?= e($c['plate_number']) ?></span></td>
                <td><?= e($c['complainant_name']) ?><br><small class="text-muted"><?= e($c['complainant_phone']) ?></small></td>
                <td style="max-width:320px"><small><?= e(mb_strimwidth($c['content'], 0, 140, '…')) ?></small></td>
                <td><?= fmt_datetime($c['created_at']) ?></td>
                <td><?= badge($c['status'], complaint_statuses()) ?></td>
                <td><a href="complaints.php?id=<?= $c['id'] ?>" class="btn btn-sm <?= $c['status'] === 'cho_xu_ly' ? 'btn-app' : 'btn-outline-secondary' ?>"><?= $c['status'] === 'cho_xu_ly' ? 'Xử lý' : 'Xem' ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="mt-3"><?= render_pagination($pg) ?></div>
    <?php
    admin_footer();
}

function show_detail(array $errors)
{
    $c = find_complaint((int) input('id'));
    if (!$c) {
        flash('danger', 'Không tìm thấy khiếu nại.');
        redirect('admin/complaints.php');
    }
    $v = find_violation_by_code($c['violation_code']);

    admin_header('Khiếu nại ' . $c['code'], 'complaints');
    ?>
    <div class="row">
        <div class="col-lg-7">
            <div class="card card-app">
                <div class="card-header d-flex justify-content-between">Nội dung khiếu nại <?= badge($c['status'], complaint_statuses()) ?></div>
                <div class="card-body"><dl class="row mb-0">
                    <dt class="col-sm-4">Người khiếu nại</dt><dd class="col-sm-8"><?= e($c['complainant_name']) ?> - <?= e($c['complainant_phone']) ?></dd>
                    <dt class="col-sm-4">Ngày gửi</dt><dd class="col-sm-8"><?= fmt_datetime($c['created_at']) ?></dd>
                    <dt class="col-sm-4">Nội dung</dt><dd class="col-sm-8" style="white-space:pre-line"><?= e($c['content']) ?></dd>
                    <?php if ($c['evidence_file']): ?>
                        <dt class="col-sm-4">Minh chứng</dt><dd class="col-sm-8">
                            <?php if (substr($c['evidence_file'], -4) === '.pdf'): ?><a href="<?= url($c['evidence_file']) ?>" target="_blank"><i class="fa fa-file-pdf-o"></i> Xem file PDF</a>
                            <?php else: ?><a href="<?= url($c['evidence_file']) ?>" target="_blank"><img src="<?= url($c['evidence_file']) ?>" class="img-thumbnail" style="max-height:200px"></a><?php endif; ?></dd>
                    <?php endif; ?>
                </dl></div>
            </div>
            <?php if ($c['status'] === 'cho_xu_ly'): ?>
                <div class="card card-app"><div class="card-header">Kết quả xử lý</div><div class="card-body">
                    <?= render_errors($errors) ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="handle">
                        <div class="form-group">
                            <div class="custom-control custom-radio"><input type="radio" id="d1" name="decision" value="chap_nhan" class="custom-control-input" <?= input('decision') === 'chap_nhan' ? 'checked' : '' ?>>
                                <label class="custom-control-label text-success font-weight-bold" for="d1">Chấp nhận khiếu nại</label></div>
                            <div class="ml-4 mt-2 mb-2">
                                <div class="custom-control custom-radio"><input type="radio" id="a1" name="accept_action" value="cancel" class="custom-control-input" <?= input('accept_action') !== 'adjust' ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="a1">Hủy biên bản vi phạm</label></div>
                                <div class="custom-control custom-radio"><input type="radio" id="a2" name="accept_action" value="adjust" class="custom-control-input" <?= input('accept_action') === 'adjust' ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="a2">Điều chỉnh giảm mức phạt còn:</label></div>
                                <input type="number" name="new_fine" step="1000" class="form-control form-control-sm mt-1" style="max-width:220px" placeholder="Số tiền mới" value="<?= old('new_fine') ?>">
                            </div>
                            <div class="custom-control custom-radio"><input type="radio" id="d2" name="decision" value="tu_choi" class="custom-control-input" <?= input('decision') === 'tu_choi' ? 'checked' : '' ?>>
                                <label class="custom-control-label text-danger font-weight-bold" for="d2">Từ chối khiếu nại (giữ nguyên biên bản, gia hạn nộp)</label></div>
                        </div>
                        <div class="form-group"><label>Trả lời người khiếu nại *</label><textarea name="response" rows="4" class="form-control" required><?= old('response') ?></textarea></div>
                        <button class="btn btn-app"><i class="fa fa-check"></i> Lưu kết quả</button>
                        <a href="complaints.php" class="btn btn-secondary">Quay lại</a>
                    </form>
                </div></div>
            <?php else: ?>
                <div class="card card-app"><div class="card-header">Kết quả đã xử lý</div><div class="card-body">
                    <p style="white-space:pre-line"><?= e($c['response']) ?></p>
                    <small class="text-muted">Xử lý bởi <?= e($c['handler_name'] ?: 'N/A') ?> lúc <?= fmt_datetime($c['handled_at']) ?></small>
                </div></div>
                <a href="complaints.php" class="btn btn-secondary">Quay lại</a>
            <?php endif; ?>
        </div>
        <div class="col-lg-5"><div class="card card-app">
            <div class="card-header">Biên bản <?= e($v['code']) ?></div>
            <div class="card-body">
                <p><span class="plate"><?= e($v['plate_number']) ?></span> <?= badge($v['status'], violation_statuses()) ?></p>
                <dl class="row small mb-0">
                    <dt class="col-5">Chủ xe</dt><dd class="col-7"><?= e($v['owner_name']) ?></dd>
                    <dt class="col-5">Lỗi</dt><dd class="col-7"><?= e($v['type_name']) ?></dd>
                    <dt class="col-5">Thời gian</dt><dd class="col-7"><?= fmt_datetime($v['violation_time']) ?></dd>
                    <dt class="col-5">Địa điểm</dt><dd class="col-7"><?= e($v['location']) ?></dd>
                    <dt class="col-5">Mô tả</dt><dd class="col-7"><?= e($v['description']) ?></dd>
                    <dt class="col-5">Tiền phạt</dt><dd class="col-7"><?= money($v['fine_amount']) ?></dd>
                    <dt class="col-5">Hạn nộp</dt><dd class="col-7"><?= fmt_date($v['due_date']) ?></dd>
                    <dt class="col-5">Cán bộ lập</dt><dd class="col-7"><?= e($v['officer_name']) ?></dd>
                </dl>
                <?php if ($v['evidence_image']): ?><img src="<?= url($v['evidence_image']) ?>" class="img-fluid rounded mt-2"><?php endif; ?>
                <a href="violations.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-dark mt-3">Mở biên bản</a>
            </div>
        </div></div>
    </div>
    <?php
    admin_footer();
}
