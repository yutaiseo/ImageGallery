<?php
session_start();
require_once __DIR__ . '/cdn_assets.php';
require_once __DIR__ . '/csrf.php';

$pageTitle = $pageTitle ?? '图片管理系统';
$bodyClass = $bodyClass ?? 'bg-light';
$showNavbar = $showNavbar ?? true;
$extraCdnCssKeys = $extraCdnCssKeys ?? [];
$extraCssFiles = $extraCssFiles ?? [];
$extraHeadHtml = $extraHeadHtml ?? '';

$isLoggedIn = !empty($_SESSION['loggedin']);
$isAdmin = !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';

// 设置HTTP安全头部
$csp = "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.bootcdn.net https://cdn.staticfile.net https://ajax.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://static.cloudflareinsights.com; " .
    "style-src 'self' 'unsafe-inline' https://cdn.bootcdn.net https://cdn.staticfile.net https://ajax.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; " .
    "img-src 'self' data: https: http:; " .
    "font-src 'self' https://cdn.bootcdn.net https://cdn.staticfile.net https://ajax.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com; " .
    "connect-src 'self' https: http:";
header("Content-Security-Policy: {$csp}");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
if (ob_get_level() > 0) {
    ob_end_clean();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- CDN 预连接 -->
    <link rel="dns-prefetch" href="https://cdn.bootcdn.net">
    <link rel="dns-prefetch" href="https://cdn.staticfile.net">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://ajax.googleapis.com" crossorigin>
    
    <?php render_cdn_css(['bootstrap_css', 'fontawesome_css']); ?>
    <?php if (!empty($extraCdnCssKeys)) render_cdn_css($extraCdnCssKeys); ?>
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo filemtime(__DIR__ . '/assets/css/app.css'); ?>">
    <?php foreach ($extraCssFiles as $cssHref): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref); ?>">
    <?php endforeach; ?>
    <?php if (!empty($extraHeadHtml)) echo $extraHeadHtml; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
<?php if ($showNavbar): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="/index.php">图片系统</a>
        <div class="d-flex align-items-center">
            <span id="current-time" class="current-time"></span>
            <?php if ($isAdmin): ?>
                <a href="/admin/change_password.php" class="btn btn-light me-2">修改密码</a>
                <a href="/admin/index.php" class="btn btn-info me-2">管理后台</a>
                <a href="/admin/logout.php?token=<?php echo htmlspecialchars(csrf_token()); ?>" class="btn btn-danger">退出登录</a>
            <?php elseif ($isLoggedIn): ?>
                <a href="/admin/change_password.php" class="btn btn-light me-2">修改密码</a>
                <a href="/admin/logout.php?token=<?php echo htmlspecialchars(csrf_token()); ?>" class="btn btn-danger">退出登录</a>
            <?php else: ?>
                <a href="/admin/login.php" class="btn btn-light">管理员登录</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php endif; ?>