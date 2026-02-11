<?php
include 'install_guard.php';
require_once __DIR__ . '/../ctrol/config/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote FROM images WHERE id = ? AND is_deleted = 0');
$stmt->execute([$id]);
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$image) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '图片不存在']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data' => [
        'id' => (int)$image['id'],
        'title' => $image['title'],
        'description' => $image['description'],
        'file_path' => $image['file_path'],
        'is_remote' => (int)$image['is_remote']
    ]
]);
