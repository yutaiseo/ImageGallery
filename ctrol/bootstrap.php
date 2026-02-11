<?php
// gadmin/bootstrap.php — 后台公共引导文件
// 包含配置、会话和公共工具
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../Gallery/install_guard.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';

$defaultSettings = [];
try {
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defaultSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $defaultSettings = [];
}

// 强制后台使用 HTTPS（如配置中要求）
if (!empty($defaultSettings['force_https']) && $defaultSettings['force_https'] === '1') {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on') {
        // 不在登录页面才跳转
        $uri = $_SERVER['REQUEST_URI'];
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $uri);
        exit;
    }
}
