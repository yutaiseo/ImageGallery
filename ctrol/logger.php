<?php
// gadmin/logger.php — 管理员操作日志工具
if (session_status() === PHP_SESSION_NONE) session_start();

function log_action($pdo, $username, $action_type, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare('INSERT INTO user_action_logs (username, action_type, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $action_type, $details, $ip]);
    } catch (Exception $e) {
        error_log('log_action error: ' . $e->getMessage());
    }
}
