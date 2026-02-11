<?php
// 最基础的状态 - 无缓冲，无复杂处理

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $path !== null ? $path : '/';

if (preg_match('#^/install(?:/|$)#', $path)) {
    $sub = trim(substr($path, strlen('/install')), '/');
    $sub = preg_replace('/\.php$/i', '', $sub);
    if ($sub === '' || $sub === 'index') {
        require_once __DIR__ . '/../run/index.php';
        exit;
    }
    if ($sub === 'complete') {
        require_once __DIR__ . '/../run/complete.php';
        exit;
    }
    http_response_code(404);
    exit;
}

if (preg_match('#^/admin(?:/|$)#', $path)) {
    $sub = trim(substr($path, strlen('/admin')), '/');
    if ($sub === '') {
        $sub = 'index';
    }
    $sub = preg_replace('/\.php$/i', '', $sub);
    $allowed = [
        'index', 'users', 'images', 'recycle', 'settings', 'logs', 'backup', 'cloud',
        'upload', 'uploader', 'update_image', 'delete_image', 'add_image', 'oss',
        'change_password', 'login', 'logout', 'admin'
    ];
    if (!in_array($sub, $allowed, true)) {
        http_response_code(404);
        exit;
    }
    require_once __DIR__ . '/../ctrol/' . $sub . '.php';
    exit;
}

include 'install_guard.php';
require_once __DIR__ . '/../ctrol/config/config.php';
$migrationPath = __DIR__ . '/db_migration.php';
if (file_exists($migrationPath)) {
    require_once $migrationPath;
} else {
    error_log('Missing migration file: ' . $migrationPath);
}
require_once __DIR__ . '/csrf.php';

// 执行数据库迁移和升级
if (isset($pdo) && function_exists('ensure_database_schema')) {
    ensure_database_schema($pdo);
}

define('IS_HOME_PAGE', true);  // 标记为首页，footer 会保存缓存

// 检查是否登录
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'];
$isAdmin = !empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin'], true);
$perPage = 20;  // 首屏仅20张，极速响应
?>
<?php require __DIR__ . '/header.php'; ?>
<div id="pageConfig" class="d-none" data-is-admin="<?= $isAdmin ? '1' : '0' ?>" data-per-page="<?= (int)$perPage ?>" data-csrf="<?= htmlspecialchars(csrf_token()) ?>"></div>
<div class="container mt-4 flex-grow-1">
    <h2 class="mb-4">图片库</h2>

    <?php if ($isAdmin): ?>
    <!-- 添加图片按钮（仅登录用户可见） -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addModal">添加图片</button>
    <?php endif; ?>

    <!-- 图片列表 -->
    <div class="masonry-container" id="imageList"></div>
    <div class="pagination-container d-flex justify-content-center" id="paginationContainer"></div>
</div>

<!-- 首页初始化脚本：直接注入第一页数据，避免额外的 API 请求 -->
<script>
(function() {
  var perPage = <?php echo (int)$perPage; ?>;
  var isAdmin = <?php echo $isAdmin ? '1' : '0'; ?>;
  var csrfToken = '<?php echo htmlspecialchars(csrf_token()); ?>';
  
  window.galleryConfig = { perPage, isAdmin, csrfToken };
  
  // 第一页数据（从服务器嵌入，避免额外的 API 请求）
  window.initialData = <?php 
    // 直接查询数据库获取第一页
    try {
      $stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote FROM images WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT ? OFFSET ?');
      $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
      $stmt->bindValue(2, 0, PDO::PARAM_INT);
      $stmt->execute();
      
      $countStmt = $pdo->query('SELECT COUNT(*) FROM images WHERE is_deleted = 0');
      $total = (int)$countStmt->fetchColumn();
      
      $items = [];
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
          'id' => (int)$row['id'],
          'title' => $row['title'],
          'description' => $row['description'],
          'file_path' => $row['file_path'],
          'is_remote' => (int)$row['is_remote']
        ];
      }
      
      echo json_encode([
        'items' => $items,
        'total' => $total,
        'page' => 1,
        'per_page' => $perPage
      ], JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
      echo json_encode(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage]);
    }
  ?>;
})();
</script>
<?php include 'modals.php'; ?>

<!-- 全屏图片浏览器 -->
<div class="image-viewer" id="imageViewer">
    <div class="image-viewer-content">
        <div class="image-viewer-controls">
            <button class="close-btn" id="closeImageViewer">
                <i class="fas fa-times"></i>
            </button>
            <div class="image-counter" id="imageCounter">1/0</div>
        </div>

        <div class="image-viewer-img is-loading" id="imageViewerImg"></div>
        <div class="image-viewer-spinner" id="imageViewerSpinner"></div>

        <div class="image-viewer-info">
            <h3 id="imageViewerTitle"></h3>
            <p id="imageViewerDescription"></p>
        </div>

        <div class="image-viewer-nav">
            <button class="nav-btn" id="prevImageBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="nav-btn" id="nextImageBtn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>