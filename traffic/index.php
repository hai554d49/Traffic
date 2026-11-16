<?php
/**
 * TRANG CHỦ + TRA CỨU
 *   GET index.php           -> show_home()
 *   GET index.php?q=30A...  -> lookup(): nhập mã biên bản thì chuyển sang violation.php, nhập biển số thì hiện danh sách vi phạm
 */
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';

$q = input('q');
$q === '' ? show_home() : lookup($q);

function lookup($q)
{
    // 1. Thử coi từ khóa là mã biên bản
    $byCode = find_violation_by_code($q);
    if ($byCode) {
        redirect('violation.php?code=' . urlencode($byCode['code']));
    }
    // 2. Coi là biển số: so sánh sau khi bỏ dấu "-", "."
    $vehicle = fetch_one('SELECT * FROM vehicles WHERE ' . sql_plate('plate_number') . ' = ?', [normalize_plate($q)]);
    $rows = $vehicle ? fetch_all('SELECT v.*, vt.name AS type_name FROM violations v JOIN violation_types vt ON vt.id = v.violation_type_id
                                  WHERE v.vehicle_id = ? ORDER BY v.violation_time DESC', [$vehicle['id']]) : [];

    page_header('Tra cứu vi phạm', 'home');
    ?>
    <div class="page-banner"><div class="container"><h1>Kết quả tra cứu</h1><p>Từ khóa: <b><?= e($q) ?></b></p></div></div>
    <div class="page-content"><div class="container">
        <?php search_box($q); ?>
        <?php if (!$vehicle): ?>
            <div class="alert alert-success"><i class="fa fa-check-circle"></i> Không tìm thấy vi phạm nào với từ khóa <b><?= e($q) ?></b>.</div>
        <?php else: ?>
            <div class="card card-app">
                <div class="card-header d-flex flex-wrap justify-content-between">
                    <span>Xe <span class="plate"><?= e($vehicle['plate_number']) ?></span></span>
                    <small class="text-muted"><?= vehicle_type_label($vehicle['vehicle_type']) ?> · <?= e($vehicle['brand']) ?> · <?= e($vehicle['color']) ?></small>
                </div>
                <?php if (!$rows): ?>
                    <div class="card-body"><div class="alert alert-success mb-0">Phương tiện này chưa có vi phạm nào.</div></div>
                <?php else: violation_table($rows); endif; ?>
            </div>
        <?php endif; ?>
    </div></div>
    <?php
    page_footer();
}

function search_box($q = '')
{
    ?>
    <form action="<?= url('index.php') ?>" method="get" class="lookup-box mb-4" id="tra-cuu">
        <div class="form-row">
            <div class="col-md-8 mb-2 mb-md-0"><input name="q" class="form-control" value="<?= e($q) ?>" placeholder="Nhập biển số (vd: 30A-123.45) hoặc mã biên bản" required></div>
            <div class="col-md-4"><button class="btn btn-app btn-block"><i class="fa fa-search"></i> Tra cứu</button></div>
        </div>
    </form>
    <?php
}

function show_home()
{
    $stats = fetch_one("SELECT COUNT(*) AS total, SUM(status = 'da_nop') AS paid, SUM(status = 'chua_nop' AND due_date < CURDATE()) AS overdue FROM violations");
    $resolved = (int) fetch_value("SELECT COUNT(*) FROM complaints WHERE status <> 'cho_xu_ly'");
    $services = [
        ['index.php#tra-cuu', 'fa-search', 'Tra cứu vi phạm', 'Tra cứu theo biển số hoặc mã biên bản, xem chi tiết lỗi, hình ảnh và số tiền phải nộp.'],
        ['scan.php', 'fa-qrcode', 'Thanh toán QR', 'Quét mã QR trên thông báo vi phạm để nộp phạt qua ví điện tử hoặc chuyển khoản ngân hàng.'],
        ['my.php?tab=complaints', 'fa-comments', 'Khiếu nại', 'Gửi khiếu nại kèm minh chứng nếu thông tin vi phạm không chính xác, theo dõi kết quả xử lý.'],
        ['my.php', 'fa-car', 'Xe của tôi', 'Đăng ký tài khoản bằng CCCD để tự động theo dõi vi phạm của tất cả xe đứng tên bạn.'],
    ];
    $statCards = [
        ['fa-file-text', $stats['total'], 'Biên bản'],
        ['fa-check-circle', $stats['paid'], 'Đã nộp phạt'],
        ['fa-exclamation-triangle', $stats['overdue'], 'Quá hạn nộp'],
        ['fa-comments', $resolved, 'Khiếu nại đã giải quyết'],
    ];

    page_header('Trang chủ', 'home');
    ?>
    <div class="hero"><div class="container">
        <h1>Tra cứu phạt nguội</h1>
        <p class="lead">Kiểm tra vi phạm giao thông, nộp phạt bằng mã QR và gửi khiếu nại trực tuyến</p>
        <?php search_box(); ?>
    </div></div>

    <div class="container stat-strip"><div class="row">
        <?php foreach ($statCards as [$icon, $num, $label]): ?>
            <div class="col-md-3 col-6"><div class="stat-card"><i class="fa <?= $icon ?>"></i><div class="num"><?= number_format((int) $num) ?></div><div class="lbl"><?= $label ?></div></div></div>
        <?php endforeach; ?>
    </div></div>

    <div class="service_section layout_padding pt-5"><div class="container">
        <h1 class="service_taital">Dịch vụ trực tuyến</h1>
        <div class="service_section_2"><div class="row">
            <?php foreach ($services as $i => [$href, $icon, $title, $text]): ?>
                <div class="col-lg-3 col-sm-6"><a href="<?= url($href) ?>">
                    <div class="service_box<?= $i === 1 ? ' active' : '' ?>">
                        <div class="building_icon"><i class="fa <?= $icon ?>"></i></div>
                        <h4 class="residential_text"><?= $title ?></h4>
                        <p class="service_text"><?= $text ?></p>
                    </div>
                </a></div>
            <?php endforeach; ?>
        </div></div>
    </div></div>

    <div class="about_section_2 layout_padding"><div class="container"><div class="row align-items-center">
        <div class="col-md-6"><div class="about_taital_main">
            <h1 class="slightly_text">Nộp phạt đúng hạn để tránh phát sinh tiền chậm nộp</h1>
            <p class="lorem_text">Người vi phạm có <b><?= (int) setting('payment_deadline_days') ?> ngày</b> kể từ ngày lập biên bản để nộp phạt.
                Quá thời hạn trên, mỗi ngày chậm nộp phải nộp thêm <b><?= e(setting('late_fee_rate')) ?>%</b> trên tổng số tiền phạt.
                Trong thời gian khiếu nại, tiền chậm nộp được tạm dừng.</p>
        </div></div>
        <div class="col-md-6"><div class="card card-app mt-4 mt-md-0"><div class="card-body">
            <h5 class="font-weight-bold">Các bước nộp phạt bằng QR</h5>
            <ol class="pl-3 mb-0" style="list-style: decimal">
                <li class="mb-2">Tra cứu biển số hoặc quét mã QR trên thông báo vi phạm.</li>
                <li class="mb-2">Kiểm tra số tiền phạt và tiền chậm nộp (nếu có).</li>
                <li class="mb-2">Dùng điện thoại quét mã QR thanh toán.</li>
                <li>Xác nhận thanh toán và nhận biên lai điện tử.</li>
            </ol>
        </div></div></div>
    </div></div></div>

    <div class="shop_section layout_padding"><div class="container">
        <h1 class="shop_taital">Bạn có thắc mắc về <span style="color: #f9b116;">biên bản vi phạm?</span></h1>
        <p class="shop_text">Gửi khiếu nại trực tuyến hoặc liên hệ hotline <?= e(setting('hotline')) ?></p>
        <div class="text-center">
            <a href="<?= url('my.php?tab=complaints') ?>" class="btn btn-app btn-lg px-4 m-1">Gửi khiếu nại</a>
            <a href="<?= url('scan.php') ?>" class="btn btn-light btn-lg px-4 m-1">Quét QR</a>
        </div>
    </div></div>
    <?php
    page_footer();
}
