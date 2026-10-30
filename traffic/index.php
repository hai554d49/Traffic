<?php
require_once __DIR__ . '/includes/init.php';

$stats = fetch_one("SELECT COUNT(*) AS total, SUM(status = 'da_nop') AS paid,
                    SUM(status = 'chua_nop' AND due_date < CURDATE()) AS overdue FROM violations");
$resolved = (int) fetch_value("SELECT COUNT(*) FROM complaints WHERE status <> 'cho_xu_ly'");

$pageTitle = 'Trang chủ';
$activeMenu = 'home';
require __DIR__ . '/includes/header.php';
?>
<!-- banner + ô tra cứu -->
<div class="hero">
    <div class="container">
        <h1>Tra cứu phạt nguội</h1>
        <p class="lead">Kiểm tra vi phạm giao thông, nộp phạt bằng mã QR và gửi khiếu nại trực tuyến</p>
        <form action="<?= url('lookup.php') ?>" method="get" class="lookup-box">
            <div class="form-row">
                <div class="col-md-8 mb-2 mb-md-0">
                    <input name="q" class="form-control" placeholder="Nhập biển số (vd: 30A-123.45) hoặc mã biên bản" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-app btn-block"><i class="fa fa-search"></i> Tra cứu</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="container stat-strip">
    <div class="row">
        <div class="col-md-3 col-6"><div class="stat-card"><i class="fa fa-file-text"></i><div class="num"><?= number_format($stats['total']) ?></div><div class="lbl">Biên bản</div></div></div>
        <div class="col-md-3 col-6"><div class="stat-card"><i class="fa fa-check-circle"></i><div class="num"><?= number_format($stats['paid']) ?></div><div class="lbl">Đã nộp phạt</div></div></div>
        <div class="col-md-3 col-6"><div class="stat-card"><i class="fa fa-exclamation-triangle"></i><div class="num"><?= number_format($stats['overdue']) ?></div><div class="lbl">Quá hạn nộp</div></div></div>
        <div class="col-md-3 col-6"><div class="stat-card"><i class="fa fa-comments"></i><div class="num"><?= number_format($resolved) ?></div><div class="lbl">Khiếu nại đã giải quyết</div></div></div>
    </div>
</div>

<!-- dịch vụ (dùng style service_box của template) -->
<div class="service_section layout_padding pt-5">
    <div class="container">
        <h1 class="service_taital">Dịch vụ trực tuyến</h1>
        <div class="service_section_2">
            <div class="row">
                <?php
                $services = [
                    ['lookup.php', 'fa-search', 'Tra cứu vi phạm', 'Tra cứu theo biển số hoặc mã biên bản, xem chi tiết lỗi, hình ảnh và số tiền phải nộp.'],
                    ['scan.php', 'fa-qrcode', 'Thanh toán QR', 'Quét mã QR trên thông báo vi phạm để nộp phạt qua ví điện tử hoặc chuyển khoản ngân hàng.'],
                    ['my_complaints.php', 'fa-comments', 'Khiếu nại', 'Gửi khiếu nại kèm minh chứng nếu thông tin vi phạm không chính xác, theo dõi kết quả xử lý.'],
                    ['my_vehicles.php', 'fa-car', 'Xe của tôi', 'Đăng ký tài khoản bằng CCCD để tự động theo dõi vi phạm của tất cả xe đứng tên bạn.'],
                ];
                foreach ($services as $i => [$href, $icon, $title, $text]): ?>
                    <div class="col-lg-3 col-sm-6">
                        <a href="<?= url($href) ?>">
                            <div class="service_box<?= $i === 1 ? ' active' : '' ?>">
                                <div class="building_icon"><i class="fa <?= $icon ?>"></i></div>
                                <h4 class="residential_text"><?= $title ?></h4>
                                <p class="service_text"><?= $text ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- quy định nộp phạt -->
<div class="about_section_2 layout_padding">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="about_taital_main">
                    <h1 class="slightly_text">Nộp phạt đúng hạn để tránh phát sinh tiền chậm nộp</h1>
                    <p class="lorem_text">
                        Người vi phạm có <b><?= (int) setting('payment_deadline_days') ?> ngày</b> kể từ ngày lập biên bản để nộp phạt.
                        Quá thời hạn trên, mỗi ngày chậm nộp phải nộp thêm <b><?= e(setting('late_fee_rate')) ?>%</b> trên tổng số tiền phạt.
                        Trong thời gian khiếu nại, tiền chậm nộp được tạm dừng.
                    </p>
                    <div class="readmore_btn"><a href="<?= url('lookup.php') ?>">Tra cứu ngay</a></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-app mt-4 mt-md-0">
                    <div class="card-body">
                        <h5 class="font-weight-bold">Các bước nộp phạt bằng QR</h5>
                        <ol class="pl-3 mb-0" style="list-style: decimal">
                            <li class="mb-2">Tra cứu biển số hoặc quét mã QR trên thông báo vi phạm.</li>
                            <li class="mb-2">Kiểm tra số tiền phạt và tiền chậm nộp (nếu có).</li>
                            <li class="mb-2">Dùng điện thoại quét mã QR thanh toán.</li>
                            <li>Xác nhận thanh toán và nhận biên lai điện tử.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- kêu gọi (style shop_section của template) -->
<div class="shop_section layout_padding">
    <div class="container">
        <h1 class="shop_taital">Bạn có thắc mắc về <span style="color: #f9b116;">biên bản vi phạm?</span></h1>
        <p class="shop_text">Gửi khiếu nại trực tuyến hoặc liên hệ hotline <?= e(setting('hotline')) ?></p>
        <div class="text-center">
            <a href="<?= url('my_complaints.php') ?>" class="btn btn-app btn-lg px-4 m-1">Gửi khiếu nại</a>
            <a href="<?= url('scan.php') ?>" class="btn btn-light btn-lg px-4 m-1">Quét QR</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
