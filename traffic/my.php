<?php
/**
 * KHU VỰC CỦA NGƯỜI DÂN (phải đăng nhập)
 *   my.php                 -> show_my_vehicles()    xe đứng tên CCCD của tôi + vi phạm từng xe
 *   my.php?tab=complaints  -> show_my_complaints()  khiếu nại tôi đã gửi + kết quả
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';
require_login();

input('tab') === 'complaints' ? show_my_complaints() : show_my_vehicles();

function show_my_vehicles()
{
    $user = current_user();
    // Liên kết xe với tài khoản qua số CCCD (không cần khóa ngoại)
    $vehicles = $user['cccd'] ? fetch_all('SELECT * FROM vehicles WHERE owner_cccd = ? ORDER BY plate_number', [$user['cccd']]) : [];
    $byVehicle = [];
    $totalDue = 0;
    foreach ($vehicles as $vh) {
        $byVehicle[$vh['id']] = fetch_all('SELECT v.*, vt.name AS type_name FROM violations v JOIN violation_types vt ON vt.id = v.violation_type_id
                                           WHERE v.vehicle_id = ? ORDER BY v.violation_time DESC', [$vh['id']]);
        foreach ($byVehicle[$vh['id']] as $v) {
            if ($v['status'] === 'chua_nop') {
                $totalDue += calc_fine($v)['total'];
            }
        }
    }

    page_header('Xe của tôi', 'vehicles');
    ?>
    <div class="page-banner"><div class="container"><h1>Phương tiện của tôi</h1><p>Các xe đứng tên CCCD <?= e($user['cccd'] ?: '(chưa cập nhật)') ?></p></div></div>
    <div class="page-content"><div class="container">
        <?php if (!$vehicles): ?>
            <div class="alert alert-light border">Chưa có phương tiện nào đăng ký với số CCCD của bạn. Bạn vẫn có thể <a href="<?= url('index.php#tra-cuu') ?>" class="text-app">tra cứu theo biển số</a>.</div>
        <?php endif; ?>
        <?php if ($totalDue): ?>
            <div class="alert alert-warning"><i class="fa fa-exclamation-circle"></i> Tổng số tiền phạt chưa nộp (tính đến hôm nay): <b><?= money($totalDue) ?></b></div>
        <?php endif; ?>
        <?php foreach ($vehicles as $vh): ?>
            <div class="card card-app">
                <div class="card-header d-flex flex-wrap justify-content-between">
                    <span><span class="plate"><?= e($vh['plate_number']) ?></span> <?= vehicle_type_label($vh['vehicle_type']) ?> · <?= e($vh['brand']) ?> · <?= e($vh['color']) ?></span>
                    <small class="text-muted"><?= count($byVehicle[$vh['id']]) ?> vi phạm</small>
                </div>
                <?php if (!$byVehicle[$vh['id']]): ?>
                    <div class="card-body text-success"><i class="fa fa-check-circle"></i> Không có vi phạm.</div>
                <?php else: violation_table($byVehicle[$vh['id']]); endif; ?>
            </div>
        <?php endforeach; ?>
    </div></div>
    <?php
    page_footer();
}

function show_my_complaints()
{
    $complaints = fetch_all('SELECT c.*, v.code AS violation_code, v.status AS violation_status, vh.plate_number, vt.name AS type_name
        FROM complaints c JOIN violations v ON v.id = c.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
        JOIN violation_types vt ON vt.id = v.violation_type_id
        WHERE c.user_id = ? ORDER BY c.created_at DESC', [current_user()['id']]);

    page_header('Khiếu nại của tôi', 'complaints');
    ?>
    <div class="page-banner"><div class="container"><h1>Khiếu nại của tôi</h1><p>Theo dõi tiến độ xử lý các khiếu nại bạn đã gửi</p></div></div>
    <div class="page-content"><div class="container">
        <div class="card card-app"><div class="card-body form-row align-items-center">
            <div class="col-md-5 mb-2 mb-md-0"><b>Gửi khiếu nại mới:</b> nhập mã biên bản cần khiếu nại</div>
            <div class="col-md-7"><form action="<?= url('violation.php') ?>" class="form-row">
                <input type="hidden" name="complain" value="1">
                <div class="col-8"><input name="code" class="form-control" placeholder="Vd: VP00000002" required></div>
                <div class="col-4"><button class="btn btn-app btn-block">Tiếp tục</button></div>
            </form></div>
        </div></div>

        <?php if (!$complaints): ?><div class="alert alert-light border">Bạn chưa gửi khiếu nại nào.</div><?php endif; ?>
        <?php foreach ($complaints as $c): ?>
            <div class="card card-app">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <span><?= e($c['code']) ?> · Biên bản <a class="text-app" href="<?= url('violation.php?code=' . urlencode($c['violation_code'])) ?>"><?= e($c['violation_code']) ?></a>
                        <span class="plate ml-1"><?= e($c['plate_number']) ?></span></span>
                    <?= badge($c['status'], complaint_statuses()) ?>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2"><?= e($c['type_name']) ?> · Gửi lúc <?= fmt_datetime($c['created_at']) ?></p>
                    <p style="white-space:pre-line"><?= e($c['content']) ?></p>
                    <?php if ($c['evidence_file']): ?><p><a href="<?= url($c['evidence_file']) ?>" target="_blank"><i class="fa fa-paperclip"></i> File minh chứng</a></p><?php endif; ?>
                    <?php if ($c['status'] === 'cho_xu_ly'): ?>
                        <div class="alert alert-warning mb-0"><i class="fa fa-hourglass-half"></i> Đang chờ cán bộ xử lý.</div>
                    <?php else: ?>
                        <div class="alert alert-<?= $c['status'] === 'chap_nhan' ? 'success' : 'danger' ?> mb-0">
                            <b>Kết quả (<?= fmt_datetime($c['handled_at']) ?>):</b> <?= e($c['response']) ?>
                            <?php if ($c['violation_status'] === 'chua_nop'): ?><div class="mt-2"><a href="<?= url('pay.php?code=' . urlencode($c['violation_code'])) ?>" class="btn btn-sm btn-app">Nộp phạt</a></div><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>
    <?php
    page_footer();
}
