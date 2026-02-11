<?php
// delete_image.php - 处理删除图片
require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once __DIR__ . '/../Gallery/cache_utils.php';

if (isset($_GET['id'])) {
    $token = $_GET['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $_SESSION['error'] = 'CSRF 验证失败';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
    $id = (int)$_GET['id'];

    // 获取图片信息
    $stmt = $pdo->prepare("SELECT * FROM images WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($image) {
        // 删除文件（如果不是远程）
        if (!$image['is_remote']) {
            $filePath = upload_storage_path($image['file_path']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 删除数据库记录
        $stmt = $pdo->prepare("DELETE FROM images WHERE id = ?");
        if ($stmt->execute([$id])) {
            clear_home_cache();  // 清除首页缓存
            $_SESSION['success'] = '图片删除成功';
        } else {
            $_SESSION['error'] = '删除失败';
        }
    } else {
        $_SESSION['error'] = '图片不存在';
    }
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>