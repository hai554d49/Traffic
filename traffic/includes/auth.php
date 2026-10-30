<?php
/**
 * Xác thực & phân quyền.
 * Vai trò: admin (quản trị), officer (cán bộ CSGT), citizen (người dân).
 */

function current_user()
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = fetch_one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
            if (!$user) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function is_logged_in()
{
    return current_user() !== null;
}

function is_staff()
{
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'officer'], true);
}

function is_admin()
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login()
{
    if (!is_logged_in()) {
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
}

function require_staff()
{
    require_login();
    if (!is_staff()) {
        http_response_code(403);
        die('Bạn không có quyền truy cập trang này.');
    }
}

function require_admin()
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Chức năng này chỉ dành cho quản trị viên.');
        redirect('admin/index.php');
    }
}

function role_labels()
{
    return ['admin' => 'Quản trị viên', 'officer' => 'Cán bộ CSGT', 'citizen' => 'Người dân'];
}
