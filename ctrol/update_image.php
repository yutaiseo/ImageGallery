<?php
// update_image.php - 处理更新图片
require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once __DIR__ . '/../Gallery/cache_utils.php';

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
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'CSRF 验证失败']);
            exit;
        }
        $_SESSION['error'] = 'CSRF 验证失败';
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => '标题不能为空']);
            exit;
        }
        $_SESSION['error'] = '标题不能为空';
        header("Location: index.php");
        exit;
    }

    // 获取原始图片信息
    $stmt = $pdo->prepare("SELECT * FROM images WHERE id = ?");
    $stmt->execute([$id]);
    $originalImage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$originalImage) {
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => '图片不存在']);
            exit;
        }
        $_SESSION['error'] = '图片不存在';
        header("Location: index.php");
        exit;
    }

    $filePath = $originalImage['file_path'];
    $isRemote = $originalImage['is_remote'];

    if (!empty($_FILES['new_image']['name']) && $_FILES['new_image']['error'] === UPLOAD_ERR_OK) {
        // 删除旧图片（如果不是远程）
        if (!$isRemote) {
            $oldPath = upload_storage_path($filePath);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // 验证新文件
        $file = $_FILES['new_image'];
        $maxSize = 6 * 1024 * 1024; // 6MB

        $imageInfo = getimagesize($file['tmp_name']);
        $validTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if ($imageInfo === false || !isset($validTypes[$imageInfo['mime']]) || $file['size'] > $maxSize) {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => '新文件类型或大小无效（最大 6MB）']);
                exit;
            }
            $_SESSION['error'] = '新文件类型或大小无效（最大 6MB）';
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }

        // 上传新图片
        $extension = 'jpg'; // 始终转换为 JPEG 以便压缩
        $fileName = bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = upload_storage_path($fileName);

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // 压缩图片到 800KB，质量 75%
            if (!compress_image($targetPath, $targetPath, 800 * 1024, 75)) {
                unlink($targetPath);
                if (!empty($_POST['ajax'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => '图片压缩失败']);
                    exit;
                }
                $_SESSION['error'] = '图片压缩失败';
                header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
                exit;
            }
            $filePath = $fileName;
            $isRemote = 0;
        } else {
            if (!empty($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => '图片更新失败']);
                exit;
            }
            $_SESSION['error'] = '图片更新失败';
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
            exit;
        }
    }

    // 更新数据库
    $stmt = $pdo->prepare("UPDATE images SET title = ?, description = ?, file_path = ?, is_remote = ? WHERE id = ?");
    if ($stmt->execute([$title, $description, $filePath, $isRemote, $id])) {
        clear_home_cache();  // 清除首页缓存
        // 检查是否是AJAX请求
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $id,
                    'title' => $title,
                    'description' => $description,
                    'file_path' => $filePath,
                    'is_remote' => (int)$isRemote
                ]
            ]);
            exit;
        }
        $_SESSION['success'] = '图片更新成功';
    } else {
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => '更新失败']);
            exit;
        }
        $_SESSION['error'] = '更新失败';
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
    exit;
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
?>