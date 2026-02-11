<?php
// 接收客户端发送的最快 CDN 信息，保存到 session
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$cdn = $data['cdn'] ?? '';

if (empty($cdn)) {
    http_response_code(400);
    exit;
}

$validCdns = ['bootcdn', 'staticfile', 'cdnjs', 'jsdelivr', 'unpkg', 'google'];
if (!in_array($cdn, $validCdns, true)) {
    http_response_code(400);
    exit;
}

// 保存到 session（下次 cdn_region() 会检查这个）
$_SESSION['cdn_preferred_region'] = ($cdn === 'google') ? 'global' : (in_array($cdn, ['bootcdn', 'staticfile']) ? 'cn' : 'global');
$_SESSION['cdn_fastest'] = $cdn;
$_SESSION['cdn_fastest_at'] = time();

header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>
