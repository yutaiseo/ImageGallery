<?php
/**
 * 缓存工具函数
 */

function clear_home_cache() {
    $cacheFile = __DIR__ . '/cache/index.html.cache';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

function is_cache_valid() {
    $cacheFile = __DIR__ . '/cache/index.html.cache';
    if (!file_exists($cacheFile)) {
        return false;
    }
    
    // 检查文件大小（避免缓存文件异常膨胀）
    $fileSize = filesize($cacheFile);
    $maxSize = 2 * 1024 * 1024;  // 2MB 上限
    if ($fileSize > $maxSize) {
        @unlink($cacheFile);
        return false;
    }
    
    $cacheAge = time() - filemtime($cacheFile);
    return $cacheAge < 300;  // 5分钟有效期
}

function save_cache($html) {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    // 检查目录权限
    if (!is_writable($cacheDir)) {
        error_log('缓存目录不可写: ' . $cacheDir);
        return false;
    }
    
    $cacheFile = $cacheDir . '/index.html.cache';
    
    // 检查 HTML 内容大小防止异常
    if (strlen($html) > 2 * 1024 * 1024) {
        error_log('缓存内容过大，拒绝保存: ' . strlen($html) . ' bytes');
        return false;
    }
    
    // 原子性写入：先写临时文件，再重命名
    $tempFile = $cacheFile . '.tmp';
    $written = @file_put_contents($tempFile, $html, LOCK_EX);
    
    if ($written === false) {
        error_log('缓存文件写入失败: ' . $cacheFile);
        return false;
    }
    
    if (!@rename($tempFile, $cacheFile)) {
        @unlink($tempFile);
        error_log('缓存文件重命名失败: ' . $cacheFile);
        return false;
    }
    
    return true;
}

function get_cache_stats() {
    $cacheFile = __DIR__ . '/cache/index.html.cache';
    if (!file_exists($cacheFile)) {
        return [
            'exists' => false,
            'size' => 0,
            'age' => 0,
            'valid' => false
        ];
    }
    
    $size = filesize($cacheFile);
    $age = time() - filemtime($cacheFile);
    
    return [
        'exists' => true,
        'size' => $size,
        'age' => $age,
        'valid' => is_cache_valid(),
        'size_mb' => round($size / 1024 / 1024, 2)
    ];
}
?>

