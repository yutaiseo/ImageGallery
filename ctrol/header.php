<?php
// gadmin/header.php — 后台头部与导航（轻量）
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../Gallery/cdn_assets.php';
require_once __DIR__ . '/../Gallery/csrf.php';
$isSuperadmin = !empty($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
$isDemo = !empty($_SESSION['role']) && $_SESSION['role'] === 'demo';
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>管理后台 - <?php echo htmlspecialchars($page_title ?? '控制台'); ?></title>
  
  <!-- CDN 预连接 -->
  <link rel="dns-prefetch" href="https://cdn.bootcdn.net">
  <link rel="dns-prefetch" href="https://cdn.staticfile.net">
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  
  <?php render_cdn_css(['bootstrap_css', 'fontawesome_css']); ?>
  <link rel="stylesheet" href="../assets/css/admins.css?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/css/admins.css'); ?>">
</head>
<body class="gadmin-body">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top gadmin-nav">
  <div class="container-fluid">
    <a class="navbar-brand" href="/admin/index.php">管理后台</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#gadminNavbar" aria-controls="gadminNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="gadminNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/admin/index.php"><i class="fas fa-chart-line"></i> 仪表盘</a></li>
        <?php if (!$isDemo): ?>
        <li class="nav-item"><a class="nav-link" href="/admin/uploader.php"><i class="fas fa-cloud-upload-alt"></i> 上传图片</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="/admin/images.php"><i class="fas fa-images"></i> 图片管理</a></li>
        <?php if ($isSuperadmin): ?>
        <li class="nav-item"><a class="nav-link" href="/admin/users.php"><i class="fas fa-users"></i> 用户管理</a></li>
        <?php endif; ?>
        <?php if (!$isDemo): ?>
        <li class="nav-item"><a class="nav-link" href="/admin/recycle.php"><i class="fas fa-trash"></i> 回收站</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/settings.php"><i class="fas fa-cog"></i> 设置</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/change_password.php"><i class="fas fa-key"></i> 修改密码</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/cloud.php"><i class="fas fa-cloud"></i> 云服务</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/backup.php"><i class="fas fa-database"></i> 备份</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="/admin/logs.php"><i class="fas fa-file-alt"></i> 日志</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-light btn-sm" href="/index.php"><i class="fas fa-globe"></i> 前台</a>
        <span class="navbar-text text-light d-none d-lg-inline">|</span>
        <span class="navbar-text text-light"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? '管理员'); ?><?php if ($isDemo): ?> (demo)<?php endif; ?></span>
        <a class="btn btn-outline-light btn-sm" href="/admin/logout.php?token=<?php echo htmlspecialchars(csrf_token()); ?>"><i class="fas fa-sign-out-alt"></i> 退出</a>
      </div>
    </div>
  </div>
</nav>
<div class="container-fluid">
  <div class="row">
    <main class="col-12">
