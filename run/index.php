<?php
session_start();
require_once __DIR__ . '/../Gallery/cdn_assets.php';

// 检查安装是否已完成
if (!empty($_SESSION['install_complete'])) {
    $adminUser = $_SESSION['admin_user'] ?? '管理员';
    unset($_SESSION['install_complete']);
    unset($_SESSION['admin_user']);
    // 显示完成页面
    include __DIR__ . '/../run/complete.php';
    exit;
}

// 安装锁：已安装直接 403
if (file_exists('../ctrol/config/config.php')) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

$step = (int)($_GET['step'] ?? 1);
$step = max(1, min(3, $step));
$error = '';

// 处理安装步骤
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($step) {
            case 1:
                // 环境验证
                $errors = [];
                $warnings = [];
                
                // PHP版本检查（必要）
                if (version_compare(PHP_VERSION, '7.4.0') < 0) {
                    $errors[] = "需要PHP 7.4或更高版本（当前版本：" . PHP_VERSION . "）";
                }

                // 扩展检查（必要）
                $requiredExt = ['pdo_mysql', 'gd', 'fileinfo'];
                foreach ($requiredExt as $ext) {
                    if (!extension_loaded($ext)) {
                        $errors[] = "缺少必需扩展：$ext";
                    }
                }

                // 目录检查和自动创建（尝试修复，不强制阻止）
                $checkDirs = [
                    '../Gallery' => '项目目录',
                    '../Gallery/uploads' => '上传目录',
                    '../Gallery/logs' => '日志目录'
                ];
                
                foreach ($checkDirs as $dir => $label) {
                    // 尝试创建不存在的目录
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    
                    // 检查权限
                    if (is_dir($dir) && is_writable($dir)) {
                        // 权限正确，继续
                    } elseif (is_dir($dir)) {
                        // 目录存在但不可写，尝试修改权限
                        @chmod($dir, 0755);
                        if (!is_writable($dir)) {
                            $warnings[] = "目录权限可能不足，安装过程中可能无法写入文件：$label";
                        }
                    }
                }

                // 只有真正的错误才阻止安装
                if (!empty($errors)) {
                    throw new Exception(implode("<br>", $errors));
                }

                // 警告信息保存到 session，不阻止安装
                if (!empty($warnings)) {
                    $_SESSION['install_warnings'] = $warnings;
                }

                // 进入下一步
                header("Location: ?step=2");
                exit;

            case 2:
                // 数据库配置
                $dbConfig = [
                    'host' => $_POST['db_host'] ?? 'localhost',
                    'name' => trim($_POST['db_name']),
                    'user' => trim($_POST['db_user']),
                    'pass' => $_POST['db_pass']
                ];

                // 验证输入
                if (empty($dbConfig['name']) || empty($dbConfig['user'])) {
                    throw new Exception("数据库名称和用户名不能为空");
                }

                // 测试数据库连接
                try {
                    $dsn = "mysql:host={$dbConfig['host']};charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ];
                    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
                    
                    // 创建数据库
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['name']}`");
                    $pdo->exec("USE `{$dbConfig['name']}`");
                    
                    // 存储配置到Session
                    $_SESSION['install'] = ['db' => $dbConfig];
                } catch (PDOException $e) {
                    throw new Exception("数据库连接失败: " . $e->getMessage());
                }

                header("Location: ?step=3");
                exit;

            case 3:
                // 管理员账户设置
                if (!isset($_SESSION['install']['db'])) {
                    throw new Exception("安装会话数据丢失，请重新开始安装");
                }

                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $confirm = $_POST['confirm_password'];

                // 输入验证
                if (empty($username) || empty($password)) {
                    throw new Exception("用户名和密码不能为空");
                }

                if ($password !== $confirm) {
                    throw new Exception("两次输入的密码不一致");
                }

                if (strlen($password) < 8) {
                    throw new Exception("密码长度至少8位");
                }

                $_SESSION['install']['admin'] = [
                    'username' => $username
                ];

                // 生成配置文件
                $db = $_SESSION['install']['db'];
                
                // 确保目录存在
                @mkdir('../ctrol/config', 0755, true);
                @mkdir('../Gallery/uploads', 0755, true);
                @mkdir('../Gallery/logs', 0755, true);
                
                // 尝试设置目录权限
                @chmod('../ctrol', 0755);
                @chmod('../ctrol/config', 0755);
                @chmod('../Gallery/uploads', 0755);
                @chmod('../Gallery/logs', 0755);
                
                // 用简单的字符串拼接方式构建配置文件
                $configContent = "<?php\n";
                $configContent .= "session_start();\n\n";
                
                // 数据库配置
                $configContent .= "// 数据库配置\n";
                $configContent .= "\$db_host = '" . addslashes($db['host']) . "';\n";
                $configContent .= "\$db_name = '" . addslashes($db['name']) . "';\n";
                $configContent .= "\$db_user = '" . addslashes($db['user']) . "';\n";
                $configContent .= "\$db_pass = '" . addslashes($db['pass']) . "';\n\n";
                
                // PDO 连接
                $configContent .= "try {\n";
                $configContent .= "    \$pdo = new PDO(\n";
                $configContent .= "        \"mysql:host=\$db_host;dbname=\$db_name;charset=utf8mb4\",\n";
                $configContent .= "        \$db_user,\n";
                $configContent .= "        \$db_pass,\n";
                $configContent .= "        [\n";
                $configContent .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
                $configContent .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
                $configContent .= "            PDO::ATTR_EMULATE_PREPARES => false\n";
                $configContent .= "        ]\n";
                $configContent .= "    );\n";
                $configContent .= "} catch (PDOException \$e) {\n";
                $configContent .= "    die(\"数据库连接失败: \" . \$e->getMessage());\n";
                $configContent .= "}\n\n";
                
                // 创建数据表
                $configContent .= "// 创建数据表（如果不存在）\n";
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS users (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        username VARCHAR(50) NOT NULL UNIQUE,\n";
                $configContent .= "        password_hash VARCHAR(255) NOT NULL,\n";
                $configContent .= "        role VARCHAR(20) NOT NULL DEFAULT 'user',\n";
                $configContent .= "        created_at DATETIME DEFAULT CURRENT_TIMESTAMP\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                // 检查并添加列的函数
                $configContent .= "// 检查并添加列的函数\n";
                $configContent .= "function addColumnIfNotExists(\$pdo, \$table, \$column, \$definition) {\n";
                $configContent .= "    try {\n";
                $configContent .= "        \$stmt = \$pdo->query(\"SHOW COLUMNS FROM \$table LIKE '\$column'\");\n";
                $configContent .= "        if (\$stmt->rowCount() === 0) {\n";
                $configContent .= "            \$pdo->exec(\"ALTER TABLE \$table ADD COLUMN \$column \$definition\");\n";
                $configContent .= "        }\n";
                $configContent .= "    } catch (PDOException \$e) {\n";
                $configContent .= "        error_log(\"添加列错误: \" . \$e->getMessage());\n";
                $configContent .= "    }\n";
                $configContent .= "}\n\n";
                
                // 创建其他表
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS images (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        title VARCHAR(255) NOT NULL,\n";
                $configContent .= "        description TEXT,\n";
                $configContent .= "        file_path VARCHAR(512) NOT NULL,\n";
                $configContent .= "        is_remote BOOLEAN NOT NULL DEFAULT 0,\n";
                $configContent .= "        is_deleted BOOLEAN NOT NULL DEFAULT 0,\n";
                $configContent .= "        deleted_at DATETIME,\n";
                $configContent .= "        deleted_by VARCHAR(50),\n";
                $configContent .= "        created_at DATETIME DEFAULT CURRENT_TIMESTAMP\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS site_settings (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        setting_key VARCHAR(100) NOT NULL UNIQUE,\n";
                $configContent .= "        setting_value TEXT,\n";
                $configContent .= "        description VARCHAR(255)\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS access_logs (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
                $configContent .= "        username VARCHAR(50),\n";
                $configContent .= "        page VARCHAR(255) NOT NULL,\n";
                $configContent .= "        ip_address VARCHAR(45) NOT NULL,\n";
                $configContent .= "        referrer VARCHAR(255),\n";
                $configContent .= "        user_agent TEXT\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS user_action_logs (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
                $configContent .= "        username VARCHAR(50),\n";
                $configContent .= "        action_type VARCHAR(50),\n";
                $configContent .= "        details TEXT,\n";
                $configContent .= "        ip_address VARCHAR(45),\n";
                $configContent .= "        INDEX idx_timestamp (timestamp),\n";
                $configContent .= "        INDEX idx_username (username),\n";
                $configContent .= "        INDEX idx_action_type (action_type)\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS source_logs (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        source VARCHAR(255) NOT NULL,\n";
                $configContent .= "        visits INT NOT NULL DEFAULT 0,\n";
                $configContent .= "        percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                $configContent .= "\$pdo->exec(\"\n";
                $configContent .= "    CREATE TABLE IF NOT EXISTS client_logs (\n";
                $configContent .= "        id INT PRIMARY KEY AUTO_INCREMENT,\n";
                $configContent .= "        client_type VARCHAR(100) NOT NULL,\n";
                $configContent .= "        visits INT NOT NULL DEFAULT 0,\n";
                $configContent .= "        percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00\n";
                $configContent .= "    )\n";
                $configContent .= "\");\n\n";
                
                // 创建管理员账户
                $configContent .= "// 创建管理员账户\n";
                $configContent .= "\$stmt = \$pdo->prepare(\"SELECT id FROM users WHERE username = ?\");\n";
                $configContent .= "\$stmt->execute(['" . addslashes($username) . "']);\n";
                $configContent .= "if (\$stmt->rowCount() === 0) {\n";
                $configContent .= "    \$hashedPassword = password_hash('" . addslashes($password) . "', PASSWORD_DEFAULT);\n";
                $configContent .= "    \$pdo->prepare(\"INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)\")\n";
                $configContent .= "        ->execute(['" . addslashes($username) . "', \$hashedPassword, 'admin']);\n";
                $configContent .= "} else {\n";
                $configContent .= "    \$pdo->prepare(\"UPDATE users SET role = 'admin' WHERE username = ?\")\n";
                $configContent .= "        ->execute(['" . addslashes($username) . "']);\n";
                $configContent .= "}\n\n";
                
                // APP_INSTALLED 标记和辅助函数
                $configContent .= "define('APP_INSTALLED', true);\n\n";
                
                $configContent .= "// 统一图片URL与上传路径处理\n";
                $configContent .= "function build_image_url(\$filePath, \$isRemote = 0) {\n";
                $configContent .= "    if (\$isRemote === 1 || strpos(\$filePath, 'http://') === 0 || strpos(\$filePath, 'https://') === 0) {\n";
                $configContent .= "        return \$filePath;\n";
                $configContent .= "    }\n";
                $configContent .= "    \$path = ltrim(\$filePath, '/');\n";
                $configContent .= "    if (strpos(\$path, 'uploads/') === 0) {\n";
                $configContent .= "        return '/' . \$path;\n";
                $configContent .= "    }\n";
                $configContent .= "    return '/uploads/' . \$path;\n";
                $configContent .= "}\n\n";
                
                $configContent .= "function upload_storage_path(\$fileName) {\n";
                $configContent .= "    return __DIR__ . '/../../Gallery/uploads/' . ltrim(\$fileName, '/');\n";
                $configContent .= "}\n";
                $configContent .= "?>\n";

                // 写入配置文件
                $configFile = '../ctrol/config/config.php';
                $configDir = dirname($configFile);
                
                // 确保配置目录存在
                if (!is_dir($configDir)) {
                    if (!@mkdir($configDir, 0755, true)) {
                        throw new Exception("无法创建配置目录");
                    }
                }
                
                // 尝试写入配置
                if (file_put_contents($configFile, $configContent) === false) {
                    throw new Exception("无法写入配置文件，请检查目录权限或联系服务器管理员");
                }

                // 创建必要的目录
                $requiredDirs = [
                    '../Gallery/uploads',
                    '../Gallery/logs'
                ];
                
                foreach ($requiredDirs as $dir) {
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    @chmod($dir, 0755);
                }

                // 完成安装 - 直接显示完成页面
                $adminUser = $username;
                require_once __DIR__ . '/../Gallery/cdn_assets.php';
                require_once __DIR__ . '/../run/complete.php';
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装向导</title>
    <?php render_cdn_css(['bootstrap_css']); ?>
    <link rel="stylesheet" href="../Gallery/assets/css/install.css?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/css/install.css'); ?>">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card step-card shadow">
        <div class="card-body">
            <h2 class="text-center mb-4">📷 图片管理系统安装向导</h2>
            <div class="mb-4">
                <!-- 路由配置折叠面板 -->
                <div class="accordion" id="routeConfigAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#routeConfigPanel" aria-expanded="false" aria-controls="routeConfigPanel">
                                📋 路由配置帮助 (如果访问 404 请查看)
                            </button>
                        </h2>
                        <div id="routeConfigPanel" class="accordion-collapse collapse" data-bs-parent="#routeConfigAccordion">
                            <div class="accordion-body pt-2">
                                <p class="mb-3"><strong>安装入口：</strong> /install/ | <strong>后台入口：</strong> /admin/</p>
                                <p class="text-muted mb-3">如访问报 404，请按环境配置路由：</p>
                                
                                <div class="route-config" data-env="baota">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="route-config-header">🌐 宝塔/aapanel (Nginx)</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary copy-config-btn" data-target="baota">📋 复制</button>
                                    </div>
                                    <pre><code>root /path/to/Gallery;
index index.php;
location /install/ { try_files $uri $uri/ /index.php?$query_string; }
location /admin/ { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }</code></pre>
                                </div>
                                
                                <div class="route-config" data-env="1panel">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="route-config-header">🌐 1panel (Nginx)</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary copy-config-btn" data-target="1panel">📋 复制</button>
                                    </div>
                                    <pre><code>root /path/to/Gallery;
index index.php;
location /install/ { try_files $uri $uri/ /index.php?$query_string; }
location /admin/ { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass 127.0.0.1:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }</code></pre>
                                </div>
                                
                                <div class="route-config" data-env="apache">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="route-config-header">🖥️ Apache (示例)</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary copy-config-btn" data-target="apache">📋 复制</button>
                                    </div>
                                    <pre><code>DocumentRoot /path/to/Gallery
&lt;Directory "/path/to/Gallery"&gt;
    AllowOverride All
    Require all granted
&lt;/Directory&gt;</code></pre>
                                </div>
                                
                                <div class="route-config" data-env="docker">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="route-config-header">🐳 Docker (Nginx + PHP-FPM)</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary copy-config-btn" data-target="docker">📋 复制</button>
                                    </div>
                                    <pre><code># 容器内站点根目录指向 /path/to/Gallery
root /path/to/Gallery;
index index.php;
location /install/ { try_files $uri $uri/ /index.php?$query_string; }
location /admin/ { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass php:9000; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }</code></pre>
                                </div>
                                
                                <div class="route-config">
                                    <div class="route-config-header">⚙️ 自建环境 (Nginx)</div>
                                    <p class="mb-0">同 Nginx 示例，确保站点根目录为 Gallery，且 /install/、/admin/ 路由放行。</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="progress-wrapper">
                <div class="progress-info">
                    <span>安装进度</span>
                    <span><?php echo $step; ?>/3</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo ($step * 100 / 3); ?>%"></div>
                </div>
                <div class="form-text">建议在安装完成后删除 install 目录。</div>
            </div>
            
            <!-- 加载状态面板 -->
            <div id="installLoading" class="install-loading" style="display: none;">
                <div class="install-loading-content">
                    <div class="spinner"></div>
                    <h4 id="loadingTitle">处理中...</h4>
                    <p id="loadingMessage">请稍候，系统正在处理...</p>
                    <div class="install-loading-progress">
                        <div class="install-loading-bar" id="loadingBar"></div>
                    </div>
                    <p class="install-loading-status" id="loadingStatus">准备中...</p>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show install-alert install-alert-danger" role="alert">
                    <div class="install-alert-heading">❌ 安装错误</div>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['install_warnings'])): ?>
                <div class="alert alert-warning alert-dismissible fade show install-alert install-alert-warning" role="alert">
                    <div class="install-alert-heading">⚠️ 安装警告</div>
                    <ul class="mb-0">
                        <?php foreach ($_SESSION['install_warnings'] as $warn): ?>
                            <li><?= htmlspecialchars($warn) ?></li>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['install_warnings']); ?>
            <?php endif; ?>

            <?php switch ($step): case 1: ?>
                <!-- 步骤1: 系统检查 -->
                <h4 class="step-title">🔍 步骤1/3 - 系统环境检查</h4>
                <div class="alert install-alert install-alert-info">
                    请确保以下条件满足系统要求
                </div>
                
                <ul class="requirement-list">
                    <li class="requirement-item <?= version_compare(PHP_VERSION, '7.4.0') >=0 ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                        PHP版本 ≥ 7.4.0
                        <span class="float-end">当前版本: <?= PHP_VERSION ?></span>
                    </li>
                    <?php foreach (['pdo_mysql', 'gd', 'fileinfo'] as $ext): ?>
                    <li class="requirement-item <?= extension_loaded($ext) ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                        扩展要求: <?= $ext ?>
                        <span class="float-end"><?= extension_loaded($ext) ? '✔ 已安装' : '✖ 未安装' ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php 
                    $dirs = [
                        '..' => '项目根目录',
                        '../Gallery/uploads' => '上传目录',
                        '../Gallery/logs' => '日志目录'
                    ];
                    foreach ($dirs as $dir => $label): 
                        $isWritable = is_writable($dir);
                        if (!is_dir($dir)) {
                            @mkdir($dir, 0755, true);
                            $isWritable = is_writable($dir);
                        }
                        if (!$isWritable) {
                            @chmod($dir, 0755);
                            $isWritable = is_writable($dir);
                        }
                    ?>
                    <li class="requirement-item <?= $isWritable ? 'bg-success text-white' : 'bg-warning' ?>">
                        目录权限: <?= $label ?>
                        <span class="float-end"><?= $isWritable ? '✔ 可写' : '⚠️ 只读(可继续安装)' ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <form method="post">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            继续安装 <span class="ms-2">→</span>
                        </button>
                    </div>
                </form>

            <?php break; case 2: ?>
                <!-- 步骤2: 数据库配置 -->
                <h4 class="step-title">💾 步骤2/3 - 数据库配置</h4>
                <div class="alert alert-danger alert-dismissible fade show install-alert install-alert-danger" role="alert">
                    <div class="install-alert-heading">⚠️ 建议</div>                    
                    <ul class="mb-0">
                        <li>请使用具备建库权限的数据库账号</li>
                        <li>请妥善保存管理员账号密码</li>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">数据库主机</label>
                            <input type="text" name="db_host" class="form-control" 
                                   value="localhost" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">数据库名称</label>
                            <input type="text" name="db_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">数据库用户</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">数据库密码</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="?step=1" class="btn btn-secondary">← 上一步</a>
                        <button type="submit" class="btn btn-primary">下一步 →</button>
                    </div>
                </form>

            <?php break; case 3: ?>
                <!-- 步骤3: 管理员设置 -->
                <h4 class="step-title">👤 步骤3/3 - 管理员账户</h4>
                <form method="post">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">管理员用户名</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">密码</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">确认密码</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="?step=2" class="btn btn-secondary">← 上一步</a>
                        <button type="submit" class="btn btn-success">完成安装 🚀</button>
                    </div>
                </form>

            <?php endswitch; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== 一键复制功能 =====
    document.querySelectorAll('.copy-config-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('data-target');
            const configBox = document.querySelector(`[data-env="${target}"]`);
            const code = configBox.querySelector('code').textContent;
            
            navigator.clipboard.writeText(code).then(() => {
                // 显示复制成功提示
                const originalText = this.textContent;
                this.textContent = '✅ 已复制！';
                this.classList.add('btn-success');
                this.classList.remove('btn-outline-primary');
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-primary');
                }, 2000);
                
                // 保存用户选择
                localStorage.setItem('install_selected_env', target);
            }).catch(err => {
                alert('复制失败: ' + err);
            });
        });
    });
    
    // ===== 自动保存选择的环境 =====
    const savedEnv = localStorage.getItem('install_selected_env');
    if (savedEnv) {
        const savedBtn = document.querySelector(`[data-target="${savedEnv}"]`);
        if (savedBtn) {
            // 高亮已保存的环境
            const configBox = savedBtn.closest('.route-config');
            configBox.style.borderLeft = '3px solid #4caf50';
            configBox.style.backgroundColor = 'rgba(76, 175, 80, 0.05)';
        }
    }
    
    // ===== 环境选择事件 =====
    document.querySelectorAll('.route-config').forEach(box => {
        box.addEventListener('click', function() {
            const env = this.getAttribute('data-env');
            // 移除所有高亮
            document.querySelectorAll('.route-config').forEach(b => {
                b.style.borderLeft = '';
                b.style.backgroundColor = '';
            });
            // 给当前项添加高亮
            this.style.borderLeft = '3px solid #4caf50';
            this.style.backgroundColor = 'rgba(76, 175, 80, 0.05)';
            // 保存选择
            localStorage.setItem('install_selected_env', env);
        });
    });

    // ===== 表单提交时显示加载状态 =====
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const loadingDiv = document.getElementById('installLoading');
            const loadingTitle = document.getElementById('loadingTitle');
            const loadingMessage = document.getElementById('loadingMessage');
            const loadingStatus = document.getElementById('loadingStatus');
            const loadingBar = document.getElementById('loadingBar');

            if (!loadingDiv) return;

            // 显示加载面板
            loadingDiv.style.display = 'flex';

            // 根据表单步骤更新文本
            const step = new URLSearchParams(window.location.search).get('step') || '1';
            const steps = {
                '1': {
                    title: '🔍 环境检查中...',
                    message: '正在检查系统环境...',
                    status: '验证 PHP 版本、扩展和目录权限...'
                },
                '2': {
                    title: '💾 数据库配置中...',
                    message: '正在连接和配置数据库...',
                    status: '创建数据库、验证连接、初始化表结构...'
                },
                '3': {
                    title: '👤 完成安装...',
                    message: '正在创建管理员账户...',
                    status: '生成配置文件、写入数据库、完成安装...'
                }
            };

            const stepInfo = steps[step] || steps['1'];
            if (loadingTitle) loadingTitle.textContent = stepInfo.title;
            if (loadingMessage) loadingMessage.textContent = stepInfo.message;
            if (loadingStatus) loadingStatus.textContent = stepInfo.status;

            if (!loadingBar) return;

            // 模拟进度条动画
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) progress = 90;
                loadingBar.style.width = progress + '%';
            }, 200);

            // 页面卸载时清除定时器
            window.addEventListener('beforeunload', () => {
                clearInterval(interval);
            });
        });
    });
});
</script>

<?php render_cdn_js(['bootstrap_js']); ?>

</body>
</html>