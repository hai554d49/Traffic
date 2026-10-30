<?php
/**
 * File khởi tạo, được require ở đầu mọi trang.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Mã QR thanh toán quá hạn sẽ tự chuyển sang "hết hạn"
expire_old_payments();
