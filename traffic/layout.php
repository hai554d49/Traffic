<?php
/**
 * LAYOUT.PHP - Khung giao diện Bootstrap dùng chung.
 *   page_header() / page_footer()   : trang người dân (dựa trên template Electricca)
 *   admin_header() / admin_footer() : trang quản trị (menu bên trái)
 */

function page_header($title, $active = '')
{
    $user = current_user();
    $nav = function ($key, $href, $label) use ($active) {
        return '<li class="nav-item' . ($key === $active ? ' active' : '') . '"><a class="nav-link" href="' . url($href) . '">' . $label . '</a></li>';
    };
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sen:wght@400;700;800&display=swap">
    <link rel="stylesheet" href="<?= url('css/app.css') ?>">
</head>
<body>
<div class="header_section">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <a class="navbar-brand app-brand" href="<?= url('index.php') ?>"><i class="fa fa-shield"></i> <?= APP_NAME ?></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ml-auto">
                    <?= $nav('home', 'index.php', 'Trang chủ') ?>
                    <?= $nav('scan', 'scan.php', 'Quét QR') ?>
                    <?php if ($user): ?>
                        <?= $nav('vehicles', 'my.php', 'Xe của tôi') ?>
                        <?= $nav('complaints', 'my.php?tab=complaints', 'Khiếu nại') ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"><i class="fa fa-user-circle"></i> <?= e($user['username']) ?></a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <?php if (is_staff()): ?><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="fa fa-dashboard"></i> Trang quản trị</a><?php endif; ?>
                                <a class="dropdown-item" href="<?= url('account.php?do=profile') ?>"><i class="fa fa-id-card"></i> Tài khoản</a>
                                <a class="dropdown-item" href="<?= url('account.php?do=logout') ?>"><i class="fa fa-sign-out"></i> Đăng xuất</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <?= $nav('login', 'account.php?do=login', 'Đăng nhập') ?>
                        <?= $nav('register', 'account.php?do=register', 'Đăng ký') ?>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </div>
</div>
<div class="clearfix"></div>
<div class="container mt-3"><?= render_flash() ?></div>
<?php
}

function page_footer($scripts = '')
{
    ?>
<div class="clearfix"></div>
<div class="footer_section">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h2 class="useful_text">Liên kết nhanh</h2>
                <div class="footer_menu"><ul>
                    <li><a href="<?= url('index.php') ?>">Tra cứu phạt nguội</a></li>
                    <li><a href="<?= url('scan.php') ?>">Quét QR thanh toán</a></li>
                    <li><a href="<?= url('my.php?tab=complaints') ?>">Khiếu nại của tôi</a></li>
                </ul></div>
            </div>
            <div class="col-md-4">
                <h2 class="useful_text">Quy định nộp phạt</h2>
                <p class="footer_text w-100">Thời hạn nộp phạt: <?= (int) setting('payment_deadline_days', 10) ?> ngày kể từ ngày vi phạm.
                    Quá hạn tính thêm <?= e(setting('late_fee_rate', 0.05)) ?>%/ngày trên số tiền phạt.</p>
            </div>
            <div class="col-md-4">
                <h2 class="useful_text">Liên hệ</h2>
                <p class="footer_text w-100"><i class="fa fa-phone"></i> Hotline: <?= e(setting('hotline')) ?><br>
                    <i class="fa fa-university"></i> <?= e(setting('bank_id')) ?> - <?= e(setting('bank_account_no')) ?><br><?= e(setting('bank_account_name')) ?></p>
            </div>
        </div>
    </div>
</div>
<div class="copyright_section"><div class="container">
    <p class="copyright_text"><?= date('Y') ?> <?= APP_NAME ?> - Đồ án môn Lập trình Web. Giao diện dựa trên template <a href="https://html.design">Free html Templates</a></p>
</div></div>
<?php common_scripts($scripts); ?>
</body>
</html>
<?php
}

function admin_header($title, $active = '')
{
    $me = current_user();
    $pendingComplaints = (int) fetch_value("SELECT COUNT(*) FROM complaints WHERE status = 'cho_xu_ly'");
    $pendingPayments = (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'cho_xac_nhan'");
    // [file, icon, nhãn, chỉ admin, số cần xử lý]
    $menu = [
        'dashboard'  => ['index.php', 'fa-dashboard', 'Tổng quan', false, 0],
        'violations' => ['violations.php', 'fa-file-text', 'Vi phạm', false, 0],
        'vehicles'   => ['vehicles.php', 'fa-car', 'Phương tiện', false, 0],
        'complaints' => ['complaints.php', 'fa-comments', 'Khiếu nại', false, $pendingComplaints],
        'payments'   => ['payments.php', 'fa-money', 'Thanh toán', false, $pendingPayments],
        'users'      => ['users.php', 'fa-users', 'Người dùng', true, 0],
        'settings'   => ['settings.php', 'fa-cog', 'Cấu hình', true, 0],
    ];
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Quản trị <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sen:wght@400;700;800&display=swap">
    <link rel="stylesheet" href="<?= url('css/app.css') ?>">
</head>
<body class="admin-body">
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="sidebar">
        <a class="sidebar-brand" href="<?= url('admin/index.php') ?>"><i class="fa fa-shield"></i> <?= APP_NAME ?></a>
        <ul class="sidebar-menu">
            <?php foreach ($menu as $key => [$href, $icon, $label, $adminOnly, $count]): ?>
                <?php if ($adminOnly && !is_admin()) continue; ?>
                <li class="<?= $key === $active ? 'active' : '' ?>"><a href="<?= url('admin/' . $href) ?>"><i class="fa <?= $icon ?> fa-fw"></i> <?= $label ?>
                    <?php if ($count): ?><span class="badge badge-warning float-right"><?= $count ?></span><?php endif; ?></a></li>
            <?php endforeach; ?>
            <li class="sidebar-sep"></li>
            <li><a href="<?= url('index.php') ?>" target="_blank"><i class="fa fa-globe fa-fw"></i> Xem trang người dân</a></li>
        </ul>
    </aside>
    <div class="admin-main">
        <nav class="admin-topbar">
            <button class="btn btn-sm btn-outline-dark d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="fa fa-bars"></i></button>
            <h5 class="mb-0 ml-2 font-weight-bold"><?= e($title) ?></h5>
            <div class="ml-auto dropdown">
                <a href="#" class="dropdown-toggle text-dark" data-toggle="dropdown"><i class="fa fa-user-circle"></i> <?= e($me['full_name']) ?>
                    <small class="text-muted">(<?= e(role_labels()[$me['role']]) ?>)</small></a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="<?= url('account.php?do=profile') ?>"><i class="fa fa-id-card"></i> Tài khoản</a>
                    <a class="dropdown-item" href="<?= url('account.php?do=logout') ?>"><i class="fa fa-sign-out"></i> Đăng xuất</a>
                </div>
            </div>
        </nav>
        <div class="admin-content">
            <?= render_flash() ?>
<?php
}

function admin_footer($scripts = '')
{
    ?>
        </div>
    </div>
</div>
<?php common_scripts($scripts); ?>
</body>
</html>
<?php
}

/** Bảng danh sách vi phạm cho người dân (dùng ở trang tra cứu và "Xe của tôi") */
function violation_table(array $rows)
{
    ?>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Mã</th><th>Lỗi vi phạm</th><th>Thời gian / Địa điểm</th><th>Hạn nộp</th><th class="text-right">Phải nộp</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $v): $f = calc_fine($v); ?>
            <tr>
                <td><b><?= e($v['code']) ?></b></td>
                <td><?= e($v['type_name']) ?></td>
                <td><?= fmt_datetime($v['violation_time']) ?><br><small class="text-muted"><?= e($v['location']) ?></small></td>
                <td><?= fmt_date($v['due_date']) ?>
                    <?php if ($f['overdue']): ?><br><small class="text-danger">Quá hạn <?= $f['late_days'] ?> ngày</small>
                    <?php elseif ($v['status'] === 'chua_nop'): ?><br><small class="text-muted">Còn <?= $f['days_left'] ?> ngày</small><?php endif; ?></td>
                <td class="text-right"><?php if (in_array($v['status'], ['chua_nop', 'khieu_nai'], true)): ?><b><?= money($f['total']) ?></b>
                    <?php if ($f['late_fee']): ?><br><small class="text-danger">gồm <?= money($f['late_fee']) ?> chậm nộp</small><?php endif; ?>
                    <?php else: ?>-<?php endif; ?></td>
                <td><?= badge($v['status'], violation_statuses()) ?></td>
                <td class="text-nowrap">
                    <a href="<?= url('violation.php?code=' . urlencode($v['code'])) ?>" class="btn btn-sm btn-outline-dark">Chi tiết</a>
                    <?php if ($v['status'] === 'chua_nop'): ?><a href="<?= url('pay.php?code=' . urlencode($v['code'])) ?>" class="btn btn-sm btn-app"><i class="fa fa-qrcode"></i> Nộp phạt</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php
}

/** jQuery + Bootstrap JS + script riêng của trang */
function common_scripts($scripts)
{
    ?>
<script src="<?= url('js/jquery.min.js') ?>"></script>
<script src="<?= url('js/bootstrap.bundle.min.js') ?>"></script>
<script>
    // Form/link có data-confirm: hỏi lại trước khi xóa, hủy...
    $(document).on('submit', 'form[data-confirm]', function (e) { if (!confirm($(this).data('confirm'))) e.preventDefault(); });
    $(document).on('click', 'a[data-confirm]', function (e) { if (!confirm($(this).data('confirm'))) e.preventDefault(); });
    setTimeout(function () { $('.alert-dismissible').alert('close'); }, 6000);
</script>
<?= $scripts ?>
<?php
}
