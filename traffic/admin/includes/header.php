<?php
/**
 * Layout trang quản trị. Biến dùng: $pageTitle, $activeMenu
 */
$pageTitle = $pageTitle ?? 'Quản trị';
$activeMenu = $activeMenu ?? '';
$me = current_user();
$pendingComplaints = (int) fetch_value("SELECT COUNT(*) FROM complaints WHERE status = 'cho_xu_ly'");
$pendingPayments = (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'cho_xac_nhan'");

$menu = [
    'dashboard'  => ['index.php', 'fa-dashboard', 'Tổng quan', false],
    'violations' => ['violations.php', 'fa-file-text', 'Vi phạm', false],
    'vehicles'   => ['vehicles.php', 'fa-car', 'Phương tiện', false],
    'complaints' => ['complaints.php', 'fa-comments', 'Khiếu nại', false],
    'payments'   => ['payments.php', 'fa-money', 'Thanh toán', false],
    'types'      => ['violation_types.php', 'fa-list', 'Danh mục lỗi', true],
    'users'      => ['users.php', 'fa-users', 'Người dùng', true],
    'settings'   => ['settings.php', 'fa-cog', 'Cấu hình', true],
    'logs'       => ['logs.php', 'fa-history', 'Nhật ký', true],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Quản trị <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sen:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('css/admin.css') ?>">
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="sidebar">
        <a class="sidebar-brand" href="<?= url('admin/index.php') ?>"><i class="fa fa-shield"></i> <?= APP_NAME ?></a>
        <ul class="sidebar-menu">
            <?php foreach ($menu as $key => [$href, $icon, $label, $adminOnly]): ?>
                <?php if ($adminOnly && !is_admin()) continue; ?>
                <li class="<?= $key === $activeMenu ? 'active' : '' ?>">
                    <a href="<?= url('admin/' . $href) ?>"><i class="fa <?= $icon ?> fa-fw"></i> <?= $label ?>
                        <?php if ($key === 'complaints' && $pendingComplaints): ?><span class="badge badge-warning float-right"><?= $pendingComplaints ?></span><?php endif; ?>
                        <?php if ($key === 'payments' && $pendingPayments): ?><span class="badge badge-warning float-right"><?= $pendingPayments ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="sidebar-sep"></li>
            <li><a href="<?= url('index.php') ?>" target="_blank"><i class="fa fa-globe fa-fw"></i> Xem trang người dân</a></li>
        </ul>
    </aside>
    <div class="admin-main">
        <nav class="admin-topbar">
            <button class="btn btn-sm btn-outline-dark d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="fa fa-bars"></i></button>
            <h5 class="mb-0 ml-2 font-weight-bold"><?= e($pageTitle) ?></h5>
            <div class="ml-auto dropdown">
                <a href="#" class="dropdown-toggle text-dark" data-toggle="dropdown">
                    <i class="fa fa-user-circle"></i> <?= e($me['full_name']) ?>
                    <small class="text-muted">(<?= e(role_labels()[$me['role']]) ?>)</small>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fa fa-id-card"></i> Tài khoản</a>
                    <a class="dropdown-item" href="<?= url('logout.php') ?>"><i class="fa fa-sign-out"></i> Đăng xuất</a>
                </div>
            </div>
        </nav>
        <div class="admin-content">
            <?= render_flash() ?>
