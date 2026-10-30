<?php
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    log_activity('Đăng xuất', current_user()['username']);
}
$_SESSION = [];
session_destroy();
session_start();
flash('info', 'Bạn đã đăng xuất.');
redirect('index.php');
