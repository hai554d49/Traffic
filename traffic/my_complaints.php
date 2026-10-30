<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$complaints = fetch_all('SELECT c.*, v.code AS violation_code, v.status AS violation_status, vh.plate_number, vt.name AS type_name
    FROM complaints c JOIN violations v ON v.id = c.violation_id JOIN vehicles vh ON vh.id = v.vehicle_id
    JOIN violation_types vt ON vt.id = v.violation_type_id
    WHERE c.user_id = ? ORDER BY c.created_at DESC', [$user['id']]);

$pageTitle = 'Khiếu nại của tôi';
$activeMenu = 'complaints';
require __DIR__ . '/includes/header.php';
?>
<div class="page-banner">
    <div class="container">
        <h1>Khiếu nại của tôi</h1>
        <p>Theo dõi tiến độ xử lý các khiếu nại bạn đã gửi</p>
    </div>
</div>
<div class="page-content">
    <div class="container">
        <div class="card card-app">
            <div class="card-body form-row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0"><b>Gửi khiếu nại mới:</b> nhập mã biên bản cần khiếu nại</div>
                <div class="col-md-7">
                    <form action="complaint.php" class="form-row">
                        <div class="col-8"><input name="code" class="form-control" placeholder="Vd: VP00000002" required></div>
                        <div class="col-4"><button class="btn btn-app btn-block">Tiếp tục</button></div>
                    </form>
                </div>
            </div>
        </div>

        <?php if (!$complaints): ?>
            <div class="alert alert-light border">Bạn chưa gửi khiếu nại nào.</div>
        <?php endif; ?>
        <?php foreach ($complaints as $c): ?>
            <div class="card card-app">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <span><?= e($c['code']) ?> · Biên bản <a class="text-app" href="violation.php?code=<?= urlencode($c['violation_code']) ?>"><?= e($c['violation_code']) ?></a>
                        <span class="plate ml-1"><?= e($c['plate_number']) ?></span></span>
                    <?= badge($c['status'], complaint_statuses()) ?>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2"><?= e($c['type_name']) ?> · Gửi lúc <?= fmt_datetime($c['created_at']) ?></p>
                    <p style="white-space:pre-line"><?= e($c['content']) ?></p>
                    <?php if ($c['evidence_file']): ?>
                        <p><a href="<?= url($c['evidence_file']) ?>" target="_blank"><i class="fa fa-paperclip"></i> File minh chứng</a></p>
                    <?php endif; ?>
                    <?php if ($c['status'] !== 'cho_xu_ly'): ?>
                        <div class="alert alert-<?= $c['status'] === 'chap_nhan' ? 'success' : 'danger' ?> mb-0">
                            <b>Kết quả (<?= fmt_datetime($c['handled_at']) ?>):</b> <?= e($c['response']) ?>
                            <?php if ($c['violation_status'] === 'chua_nop'): ?>
                                <div class="mt-2"><a href="pay.php?code=<?= urlencode($c['violation_code']) ?>" class="btn btn-sm btn-app">Nộp phạt</a></div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0"><i class="fa fa-hourglass-half"></i> Đang chờ cán bộ xử lý.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
