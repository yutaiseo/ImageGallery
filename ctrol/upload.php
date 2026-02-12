<?php
// upload.php - 简单上传接口（如果需要AJAX上传，可扩展）
require_once __DIR__ . '/bootstrap.php';
require_admin_write();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF 验证失败']);
        exit;
    }
    $file = $_FILES['image'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    $imageInfo = getimagesize($file['tmp_name']);
    $validTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if ($imageInfo === false || !isset($validTypes[$imageInfo['mime']]) || $file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => '文件类型或大小无效']);
        exit;
    }

    $extension = $validTypes[$imageInfo['mime']];
    $fileName = bin2hex(random_bytes(8)) . '.' . $extension;
    $targetPath = upload_storage_path($fileName);

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => true, 'url' => '/uploads/' . $fileName]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '上传失败']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => '无效请求']);
}
?>