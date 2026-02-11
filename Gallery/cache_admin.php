<?php
/**
 * 缓存监控和管理面板
 * 访问: /Gallery/cache_admin.php
 */

session_start();

// 检查是否登录且是管理员（简单验证）
if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'], true)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '无权限访问']);
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/cache_utils.php';

// 处理清除缓存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if ($_POST['action'] === 'clear_cache') {
        clear_home_cache();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '缓存已清除']);
        exit;
    }
}

// 获取缓存统计信息
$stats = get_cache_stats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>缓存监控</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .stats {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            background: #f9f9f9;
            margin: 20px 0;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .stat-row:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-weight: bold;
            color: #666;
        }
        .stat-value {
            color: #333;
            font-family: 'Courier New', monospace;
        }
        .status-valid {
            color: #28a745;
            font-weight: bold;
        }
        .status-invalid {
            color: #dc3545;
            font-weight: bold;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .alert {
            padding: 12px;
            margin: 15px 0;
            border-radius: 4px;
            display: none;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>首页缓存监控</h1>
        
        <div class="stats">
            <div class="stat-row">
                <span class="stat-label">缓存存在：</span>
                <span class="stat-value"><?php echo $stats['exists'] ? '是' : '否'; ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">缓存大小：</span>
                <span class="stat-value"><?php echo $stats['size_mb'] . ' MB (' . $stats['size'] . ' bytes)'; ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">缓存年龄：</span>
                <span class="stat-value"><?php echo $stats['age'] . ' 秒'; ?></span>
            </div>
            <div class="stat-row">
                <span class="stat-label">缓存有效性：</span>
                <span class="stat-value <?php echo $stats['valid'] ? 'status-valid' : 'status-invalid'; ?>">
                    <?php echo $stats['valid'] ? '✓ 有效' : '✗ 无效/已过期'; ?>
                </span>
            </div>
        </div>

        <div id="message"></div>

        <form onclick="clearCache(event); return false;">
            <button type="submit" class="btn btn-danger">清除缓存</button>
        </form>

        <p style="color: #999; font-size: 12px; margin-top: 30px;">
            缓存文件路径：<code>/Gallery/cache/index.html.cache</code><br>
            缓存策略：5分钟自动过期<br>
            最大文件大小：2 MB（超过自动删除）
        </p>
    </div>

    <script>
        function clearCache(e) {
            if (!confirm('确定要清除缓存吗？')) {
                e.preventDefault();
                return;
            }
            
            fetch('cache_admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=clear_cache'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('message').innerHTML = '<div class="alert alert-success">' + data.message + '，页面即将刷新...</div>';
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(err => console.error(err));
        }
    </script>
</body>
</html>
