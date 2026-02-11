<?php
// admin.php - 管理员入口（可选，重定向到index.php）
require_once __DIR__ . '/bootstrap.php';
require_admin();
header('Location: index.php');
exit;