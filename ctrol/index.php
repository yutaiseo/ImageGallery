<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$page_title = '仪表盘';
include __DIR__ . '/header.php';

// 简单统计
$userCount = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetchColumn();
$imageCount = $pdo->query('SELECT COUNT(*) AS c FROM images')->fetchColumn();

?>
<div class="mt-3">
  <h3>仪表盘</h3>
  <div class="row">
    <div class="col-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">用户数</h5>
          <p class="card-text display-6"><?php echo (int)$userCount; ?></p>
        </div>
      </div>
    </div>
    <div class="col-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">图片数</h5>
          <p class="card-text display-6"><?php echo (int)$imageCount; ?></p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>