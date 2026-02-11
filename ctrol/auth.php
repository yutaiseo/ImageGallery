<?php
// gadmin/auth.php — 简单的认证辅助函数（轻耦合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_admin()
{
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: /admin/login.php');
        exit;
    }
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['admin', 'superadmin'], true)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden: admin only';
        exit;
    }
}

function require_superadmin()
{
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: /admin/login.php');
        exit;
    }
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden: superadmin only';
        exit;
    }
}

function is_superadmin()
{
    return !empty($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function current_user()
{
    return $_SESSION['username'] ?? null;
}

// 用于在登录成功后调用的安全函数
function admin_on_login()
{
    // 防止 session fixation
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    // 初始化 CSRF
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
