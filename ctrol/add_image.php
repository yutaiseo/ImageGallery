<?php
// add_image.php - 处理添加图片
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../Gallery/cache_utils.php';
require_admin_write();

// 压缩图片到指定大小和质量
function compress_image($sourcePath, $targetPath, $maxSize = 800 * 1024, $quality = 75) {
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }

    $mime = $imageInfo['mime'];
    
    // 创建图像资源
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    // 如果原始文件小于目标大小，直接复制
    if (filesize($sourcePath) <= $maxSize) {
        imagedestroy($image);
        return copy($sourcePath, $targetPath);
    }

    // 逐步降低质量直到满足大小要求
    $currentQuality = $quality;
    $compressed = false;
    
    do {
        ob_start();
        imagejpeg($image, null, $currentQuality);
        $data = ob_get_clean();
        
        if (strlen($data) <= $maxSize) {
            file_put_contents($targetPath, $data);
            $compressed = true;
            break;
        }
        
        $currentQuality -= 5;
    } while ($currentQuality > 5);

    // 如果即使质量很低仍未满足，则缩小尺寸
    if (!$compressed) {
        $scale = sqrt($maxSize / strlen($data));
        $newWidth = (int)($imageInfo[0] * $scale);
        $newHeight = (int)($imageInfo[1] * $scale);
        
        if ($newWidth < 50) $newWidth = 50;
        if ($newHeight < 50) $newHeight = 50;
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $imageInfo[0], $imageInfo[1]);
        
        ob_start();
        imagejpeg($resized, null, 45);
        $data = ob_get_clean();
        file_put_contents($targetPath, $data);
        
        imagedestroy($resized);
    }

    imagedestroy($image);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $_SESSION['error'] = 'CSRF 验证失败';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? '';

    if (empty($title) || empty($type)) {
        $_SESSION['error'] = '标题和类型不能为空';
        header("Location: index.php");
        exit;
    }

    $filePath = '';
    $isRemote = 0;

    if ($type === 'remote') {
        $remoteUrl = trim($_POST['remote_url'] ?? '');
        if (empty($remoteUrl) || !preg_match('/\.(jpeg|jpg|gif|png|webp)$/i', $remoteUrl) || strpos($remoteUrl, 'http') !== 0) {
            $_SESSION['error'] = '无效的远程图片URL';
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }
        $filePath = $remoteUrl;
        $isRemote = 1;
    } elseif ($type === 'local') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = '本地上传失败';
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }

        $file = $_FILES['image'];
        $maxSize = 6 * 1024 * 1024; // 6MB
        $imageInfo = getimagesize($file['tmp_name']);
        $validTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        if ($imageInfo === false || !isset($validTypes[$imageInfo['mime']]) || $file['size'] > $maxSize) {
            $_SESSION['error'] = '文件类型或大小无效（最大 6MB）';
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }

        $extension = $validTypes[$imageInfo['mime']];
        $fileName = bin2hex(random_bytes(8)) . '.jpg'; // 始终转换为 JPEG 以便压缩
        $targetPath = upload_storage_path($fileName);

        // 先移动文件，然后压缩
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // 压缩图片到 800KB，质量 75%
            if (!compress_image($targetPath, $targetPath, 800 * 1024, 75)) {
                unlink($targetPath);
                $_SESSION['error'] = '图片压缩失败';
                header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
                exit;
            }
            $filePath = $fileName;
        } else {
            $_SESSION['error'] = '文件上传失败';
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }
    }

    // 插入数据库
    $stmt = $pdo->prepare("INSERT INTO images (title, description, file_path, is_remote, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt->execute([$title, $description, $filePath, $isRemote])) {
        clear_home_cache();  // 清除首页缓存
        $_SESSION['success'] = '图片添加成功';
    } else {
        $_SESSION['error'] = '添加失败';
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
?>