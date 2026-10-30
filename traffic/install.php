<?php
/**
 * Cài đặt cơ sở dữ liệu: tạo database, import database/traffic_violation.sql và tạo tài khoản mẫu.
 * Truy cập: http://localhost/<thu-muc>/install.php
 * Nên xóa hoặc đổi tên file này sau khi cài đặt xong.
 */
require_once __DIR__ . '/config/config.php';

$messages = [];
$error = null;
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');
        $pdo->exec("SET time_zone = '+07:00'");
        $messages[] = 'Đã tạo cơ sở dữ liệu <b>' . DB_NAME . '</b>.';

        $sql = file_get_contents(__DIR__ . '/database/traffic_violation.sql');
        // Chạy lần lượt từng câu lệnh (tách theo dấu ; ở cuối dòng)
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
        $messages[] = 'Đã tạo bảng và dữ liệu mẫu.';

        $accounts = [
            ['admin',    'admin123', 'Quản trị hệ thống', 'admin@csgt.local',   '0901000001', null,           'admin'],
            ['canbo',    'canbo123', 'Đại úy Lê Văn Tâm', 'canbo@csgt.local',   '0901000002', null,           'officer'],
            ['nguoidan', '123456',   'Nguyễn Văn An',     'an.nguyen@mail.com', '0912345678', '001090012345', 'citizen'],
            ['binhtran', '123456',   'Trần Thị Bình',     'binh.tran@mail.com', '0987654321', '079195004321', 'citizen'],
        ];
        $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, email, phone, cccd, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($accounts as $a) {
            $a[1] = password_hash($a[1], PASSWORD_DEFAULT);
            $stmt->execute($a);
        }
        // Gán cán bộ lập biên bản & người gửi khiếu nại cho dữ liệu mẫu
        $officerId = $pdo->query("SELECT id FROM users WHERE username = 'canbo'")->fetchColumn();
        $binhId = $pdo->query("SELECT id FROM users WHERE username = 'binhtran'")->fetchColumn();
        $adminId = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
        $pdo->prepare('UPDATE violations SET officer_id = ?')->execute([$officerId]);
        $pdo->prepare('UPDATE complaints SET user_id = ?')->execute([$binhId]);
        $pdo->prepare("UPDATE complaints SET handled_by = ? WHERE status <> 'cho_xu_ly'")->execute([$adminId]);
        $messages[] = 'Đã tạo tài khoản mẫu.';

        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }
        $done = true;
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cài đặt - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 720px">
    <div class="card shadow-sm">
        <div class="card-header text-white" style="background:#f9b116"><h4 class="mb-0">Cài đặt <?= APP_NAME ?></h4></div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><b>Lỗi:</b> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php foreach ($messages as $m): ?>
                <div class="alert alert-success py-2 mb-2"><?= $m ?></div>
            <?php endforeach; ?>

            <?php if ($done): ?>
                <h5 class="mt-4">Tài khoản đăng nhập</h5>
                <table class="table table-sm table-bordered">
                    <tr><th>Vai trò</th><th>Tên đăng nhập</th><th>Mật khẩu</th></tr>
                    <tr><td>Quản trị viên</td><td>admin</td><td>admin123</td></tr>
                    <tr><td>Cán bộ CSGT</td><td>canbo</td><td>canbo123</td></tr>
                    <tr><td>Người dân (có xe 30A-123.45)</td><td>nguoidan</td><td>123456</td></tr>
                    <tr><td>Người dân (có xe 51G-888.99)</td><td>binhtran</td><td>123456</td></tr>
                </table>
                <div class="alert alert-warning">Hãy xóa hoặc đổi tên file <code>install.php</code> sau khi cài đặt.</div>
                <a href="index.php" class="btn btn-warning text-white">Vào trang chủ</a>
                <a href="admin/index.php" class="btn btn-dark">Vào trang quản trị</a>
            <?php else: ?>
                <p>Thông tin kết nối (sửa trong <code>config/config.php</code>):</p>
                <ul>
                    <li>Máy chủ: <b><?= DB_HOST ?>:<?= DB_PORT ?></b></li>
                    <li>Tên CSDL: <b><?= DB_NAME ?></b></li>
                    <li>Người dùng: <b><?= DB_USER ?></b></li>
                </ul>
                <div class="alert alert-danger">Nếu CSDL <b><?= DB_NAME ?></b> đã tồn tại, toàn bộ dữ liệu cũ sẽ bị xóa và tạo lại.</div>
                <form method="post">
                    <button class="btn btn-warning text-white btn-lg">Cài đặt cơ sở dữ liệu</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
