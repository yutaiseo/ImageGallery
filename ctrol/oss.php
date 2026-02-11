<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once __DIR__ . '/../Gallery/cdn_assets.php';

// 读取配置（放在 ctrol/config，避免暴露在 web root）
$configPath = __DIR__ . '/config/oss_config.json';
$ossConfig = json_decode(file_get_contents($configPath), true);

// 初始化默认配置
$defaultConfig = [
    'enabled' => false,
    'endpoint' => '',
    'key_id' => '',
    'key_secret' => '',
    'bucket' => '',
    'prefix' => ''
];

// 读取或创建配置文件
if (file_exists($configPath)) {
    $ossConfig = json_decode(file_get_contents($configPath), true);
} else {
    $ossConfig = $defaultConfig;
    file_put_contents($configPath, json_encode($defaultConfig, JSON_PRETTY_PRINT));
}

$success = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'CSRF 验证失败';
    } else {
    $newConfig = [
        'enabled' => isset($_POST['enabled']),
        'endpoint' => trim($_POST['endpoint']),
        'key_id' => trim($_POST['key_id']),
        'key_secret' => trim($_POST['key_secret']),
        'bucket' => trim($_POST['bucket']),
        'prefix' => trim($_POST['prefix'])
    ];
    
    // 验证必填字段（如果启用OSS）
    if ($newConfig['enabled']) {
        if (empty($newConfig['endpoint']) || 
            empty($newConfig['key_id']) || 
            empty($newConfig['key_secret']) || 
            empty($newConfig['bucket'])) {
            $error = "OSS启用时，所有字段都必须填写";
        } else {
            $ossConfig = $newConfig;
            file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT));
            $success = "OSS配置已成功更新！";
        }
    } else {
        $ossConfig = $newConfig;
        file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT));
        $success = "本地存储配置已更新，OSS已禁用";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSS配置管理</title>
    <?php render_cdn_css(['bootstrap_css']); ?>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">OSS存储配置</h5>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                                    <?php echo $ossConfig['enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enabled">
                                    启用OSS存储
                                </label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="endpoint" class="form-label">OSS Endpoint</label>
                                <input type="text" class="form-control" id="endpoint" name="endpoint" 
                                       value="<?php echo htmlspecialchars($ossConfig['endpoint']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="key_id" class="form-label">Access Key ID</label>
                                <input type="text" class="form-control" id="key_id" name="key_id" 
                                       value="<?php echo htmlspecialchars($ossConfig['key_id']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="key_secret" class="form-label">Access Key Secret</label>
                                <input type="password" class="form-control" id="key_secret" name="key_secret" 
                                       value="<?php echo htmlspecialchars($ossConfig['key_secret']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bucket" class="form-label">Bucket名称</label>
                                <input type="text" class="form-control" id="bucket" name="bucket" 
                                       value="<?php echo htmlspecialchars($ossConfig['bucket']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="prefix" class="form-label">文件前缀（可选）</label>
                                <input type="text" class="form-control" id="prefix" name="prefix" 
                                       value="<?php echo htmlspecialchars($ossConfig['prefix']); ?>">
                                <div class="form-text">例如: user-uploads/ 或 project/images/</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">保存配置</button>
                            </div>
                        </form>
                        
                        <div class="mt-4">
                            <a href="/upload.php" class="btn btn-sm btn-outline-primary">
                                返回上传页面
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>