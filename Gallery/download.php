<?php
// download.php — 通过图片 ID 安全提供下载或重定向远程 URL
require 'install_guard.php';
require_once __DIR__ . '/../ctrol/config/config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid id';
    exit;
}

$stmt = $pdo->prepare('SELECT file_path, is_remote, title, is_deleted FROM images WHERE id = ?');
$stmt->execute([$id]);
$img = $stmt->fetch();
if (!$img) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

if (!empty($img['is_deleted'])) {
    http_response_code(410);
    echo 'This image is deleted';
    exit;
}

if (!empty($img['is_remote'])) {
    // 远程资源直接重定向
    header('Location: ' . $img['file_path']);
    exit;
}

$file = __DIR__ . '/' . ltrim($img['file_path'], '/');
if (!file_exists($file) || !is_file($file)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// 安全：确保文件在 uploads 目录下
$uploadsReal = realpath(__DIR__ . '/uploads');
$fileReal = realpath($file);
if ($uploadsReal === false || $fileReal === false || strpos($fileReal, $uploadsReal) !== 0) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fileReal) ?: 'application/octet-stream';
finfo_close($finfo);

// 优先使用内部加速（X-Accel-Redirect 或 X-Sendfile），否则回退到 readfile
$use_x_accel = false; // 若使用 nginx 的 X-Accel，请设置为 true，并配置 $x_accel_location
$x_accel_location = '/protected_uploads'; // nginx 内部 location 对应路径
$use_x_sendfile = false; // Apache mod_xsendfile

if ($use_x_accel) {
    // nginx: 需要将 uploads 映射到内部路径，例如 location /protected_uploads/ { internal; alias /path/to/site/uploads/; }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($fileReal) . '"');
    // 发送内部重定向到 nginx
    $internalPath = rtrim($x_accel_location, '/') . '/' . basename($fileReal);
    header('X-Accel-Redirect: ' . $internalPath);
    exit;
} elseif ($use_x_sendfile) {
    // Apache mod_xsendfile
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($fileReal) . '"');
    header('X-Sendfile: ' . $fileReal);
    exit;
} else {
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($fileReal) . '"');
    header('Content-Length: ' . filesize($fileReal));
    readfile($fileReal);
    exit;
}
