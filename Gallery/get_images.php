<?php
$timeStart = microtime(true);
$timeConnect = 0;
$timeCount = 0;
$timeQuery = 0;

// 计时：连接数据库
$t0 = microtime(true);
include 'install_guard.php';
require_once __DIR__ . '/../ctrol/config/config.php';
$timeConnect = (microtime(true) - $t0) * 1000;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');  // 禁用缓存，每次都从服务器获取
header('X-Content-Type-Options: nosniff');
header('Vary: Accept-Encoding');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(60, (int)($_GET['per_page'] ?? 12)));

// 计时：COUNT 查询
$t1 = microtime(true);
$countStmt = $pdo->query('SELECT COUNT(*) FROM images WHERE is_deleted = 0');
$total = (int)$countStmt->fetchColumn();
$timeCount = (microtime(true) - $t1) * 1000;

// 计时：分页查询
$offset = ($page - 1) * $perPage;
$t2 = microtime(true);
$stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote FROM images WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$timeQuery = (microtime(true) - $t2) * 1000;

$items = [];
foreach ($rows as $row) {
    $items[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'file_path' => $row['file_path'],
        'is_remote' => (int)$row['is_remote']
    ];
}

$t3 = microtime(true);
$json = json_encode([
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage
], JSON_UNESCAPED_SLASHES);
$timeJson = (microtime(true) - $t3) * 1000;

$timeTotal = (microtime(true) - $timeStart) * 1000;

// 添加 Server-Timing 头
header(sprintf('Server-Timing: connect;dur=%.1f, count;dur=%.1f, query;dur=%.1f, json;dur=%.1f, total;dur=%.1f', 
    $timeConnect, $timeCount, $timeQuery, $timeJson, $timeTotal));

echo $json;
