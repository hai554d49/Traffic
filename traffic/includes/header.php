<?php
/**
 * Header giao diện người dân (dựa trên template Electricca).
 * Biến dùng: $pageTitle, $activeMenu
 */
$pageTitle = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
$user = current_user();

function nav_item($key, $href, $label, $active)
{
    return '<li class="nav-item' . ($key === $active ? ' active' : '') . '"><a class="nav-link" href="' . url($href) . '">' . $label . '</a></li>';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('css/responsive.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sen:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('css/app.css') ?>">
</head>
<body>
<!-- header section start -->
<div class="header_section">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <a class="navbar-brand app-brand" href="<?= url('index.php') ?>"><i class="fa fa-shield"></i> <?= APP_NAME ?></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ml-auto">
                    <?= nav_item('home', 'index.php', 'Trang chủ', $activeMenu) ?>
                    <?= nav_item('lookup', 'lookup.php', 'Tra cứu', $activeMenu) ?>
                    <?= nav_item('scan', 'scan.php', 'Quét QR', $activeMenu) ?>
                    <?php if ($user): ?>
                        <?= nav_item('vehicles', 'my_vehicles.php', 'Xe của tôi', $activeMenu) ?>
                        <?= nav_item('complaints', 'my_complaints.php', 'Khiếu nại', $activeMenu) ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"><i class="fa fa-user-circle"></i> <?= e($user['username']) ?></a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <span class="dropdown-item-text small text-muted"><?= e($user['full_name']) ?></span>
                                <div class="dropdown-divider"></div>
                                <?php if (is_staff()): ?>
                                    <a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="fa fa-dashboard"></i> Trang quản trị</a>
                                <?php endif; ?>
                                <a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fa fa-id-card"></i> Tài khoản</a>
                                <a class="dropdown-item" href="<?= url('logout.php') ?>"><i class="fa fa-sign-out"></i> Đăng xuất</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <?= nav_item('login', 'login.php', 'Đăng nhập', $activeMenu) ?>
                        <?= nav_item('register', 'register.php', 'Đăng ký', $activeMenu) ?>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </div>
</div>
<!-- header section end -->
<div class="clearfix"></div>
<?php if (empty($hideFlash)): ?>
    <div class="container mt-3"><?= render_flash() ?></div>
<?php endif; ?>
