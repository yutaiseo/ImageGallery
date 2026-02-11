<?php
// 不需要 session_start()，由 index.php 调用已经启动过了

// 如果没有传入 $adminUser，尝试从 session 获取
if (empty($adminUser)) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $adminUser = $_SESSION['admin_user'] ?? $_SESSION['install']['admin']['username'] ?? '管理员';
}

// 清理安装会话
unset($_SESSION['install']);
unset($_SESSION['install_complete']);
unset($_SESSION['admin_user']);

// 删除自身（安全措施）
function deleteInstallFolder()
{
    $dir = __DIR__;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($dir);
}

// 建议手动删除安装目录
// deleteInstallFolder();

require_once __DIR__ . '/../Gallery/cdn_assets.php';

// 下方CSS可以直接放在 head 中替换,需要去掉 run/index.php 中的 CSS 引用，避免重复加载，加这条注释是为了提醒开发者注意这一点，同时也说明了为什么这段 CSS 代码存在于这里，而不是在一个单独的 CSS 文件中。这是为了确保安装完成页面的样式能够正确加载，同时避免在安装过程中重复加载同样的 CSS 文件，从而提高性能和用户体验。而且安装完成页面的样式可能比较特殊，可能不适合放在公共的 CSS 文件中，因此直接在这里定义也是合理的。要替换掉 run/index.php 中的 CSS 引用，可以找到以下代码行：
//```php
//<link rel="stylesheet" href="../assets/css/install.css?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/css/install.css'); \?\>"> --- IGNORE ---
// ```
// 并将其删除或注释掉，以避免重复加载同样的 CSS 文件。
// <link rel="stylesheet" href="../assets/css/install.css?v=<?php //echo time(); \?\>"> 
?>
<!DOCTYPE html>
<html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>安装完成</title>
        <?php render_cdn_css(['bootstrap_css']); ?>
        <link rel="stylesheet" href="../assets/css/install.css?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/css/install.css'); ?>">
        <style>
            .success-card { border: none; border-radius: 12px; box-shadow: 0 8px 24px rgba(76, 175, 80, 0.15); animation: slideUp 0.5s ease; }
            @keyframes slideUp { from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
            } .success-icon { font-size: 4rem; animation: bounce 0.6s ease infinite; }
            @keyframes bounce { 0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            } .info-box { background: #f0f7ff; border-left: 4px solid #2196f3; border-radius: 6px; padding: 1rem; margin: 1rem 0; }
            .warning-box { background: #fff3e0; border-left: 4px solid #ff9800; border-radius: 6px; padding: 1rem; margin: 1rem 0; }
        </style>
    </head>
    <body class="bg-light">
        <div class="container py-5" style="max-width: 600px;">
            <div class="card success-card">
                <div class="card-body text-center py-5">
                    <div class="success-icon text-success">✅</div>
                    <h2 class="text-success mb-3 mt-3">安装完成！</h2>
                    <p class="text-muted mb-4">图片管理系统已成功安装并配置，现在您可以开始使用了</p>

                    <?php if ($adminUser): ?>
                        <div class="info-box">
                            <strong>📝 管理员账号</strong>
                            <p class="mb-0 mt-2 font-monospace"><?php echo htmlspecialchars($adminUser); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="info-box alert alert-warning alert-dismissible fade show" role="alert">
                        <strong>⚠️ 重要安全提示</strong>
                        <!-- 放在安全提示下面或页面底部 -->
                        <p class="text-center mt-4 fw-bold text-primary">
                            <span id="countdown">10</span> 秒后自动跳转到后台管理...
                            <br><small id="redirecting" class="text-muted">正在跳转中</small>
                        </p>
                        <p class="mb-0 mt-2 text-start">请立即通过 FTP 或服务器文件管理器<strong>删除整个 /install 和 /run 目录</strong>，以保护系统安全。</p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>

                    <div class="mt-4 d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <a href="/admin/" class="btn btn-primary btn-lg">🔑 前往后台登录</a>
                        <a href="/" class="btn btn-outline-primary btn-lg">🏠 返回首页</a>
                    </div>
                </div>
            </div>
        </div>
        <?php render_cdn_js(['bootstrap_js']); ?>
        <script>
            let seconds = 10;
            const countdownEl = document.getElementById('countdown');
            const redirectingEl = document.getElementById('redirecting');

            const timer = setInterval(() => {
                seconds--;
                if (seconds >= 0) {
                    countdownEl.textContent = seconds;
                }

                if (seconds <= 0) {
                    clearInterval(timer);
                    redirectingEl.textContent = '正在跳转中...';
                    // 跳转到后台（可改成 window.location.href = '/admin/';
                    window.location.href = '/admin/';
                }
            }, 1000);  // 每秒更新一次

            // 可选：用户点击任意地方或按钮时取消自动跳转
            document.body.addEventListener('click', () => clearInterval(timer));
        </script>
    </body>
</html>