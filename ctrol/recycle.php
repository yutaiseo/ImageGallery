<?php
require_once __DIR__ . '/bootstrap.php';
require_admin_write();
require_once __DIR__ . '/logger.php';
$page_title = '回收站';
include __DIR__ . '/header.php';

// 参数：分页
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 处理恢复与永久删除（含批量）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) {
    echo '<div class="alert alert-danger">CSRF 验证失败</div>';
  } else {
    if ($action === 'restore') {
      $id = intval($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE images SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?');
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['username'] ?? 'unknown', 'restore_image', 'restored image id=' . $id);
        echo '<div class="alert alert-success">已恢复图片。</div>';
      }
    } elseif ($action === 'permanent_delete') {
      $id = intval($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('SELECT file_path, title FROM images WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
          $file = __DIR__ . '/../' . ltrim($row['file_path'], '/');
          if (file_exists($file)) @unlink($file);
        }
        $stmt = $pdo->prepare('DELETE FROM images WHERE id = ?');
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['username'] ?? 'unknown', 'permanent_delete_image', 'permanently deleted image id=' . $id . ' title=' . ($row['title'] ?? ''));
        echo '<div class="alert alert-success">已永久删除图片。</div>';
      }
    } elseif ($action === 'batch_restore') {
      $ids = $_POST['ids'] ?? [];
      $count = 0;
      $stmt = $pdo->prepare('UPDATE images SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?');
      foreach ($ids as $iid) {
        $iid = intval($iid);
        if ($iid > 0) {
          $stmt->execute([$iid]);
          $count++;
        }
      }
      log_action($pdo, $_SESSION['username'] ?? 'unknown', 'batch_restore', 'restored ' . $count . ' images');
      echo '<div class="alert alert-success">已恢复 ' . $count . ' 张图片。</div>';
    } elseif ($action === 'batch_permanent_delete') {
      $ids = $_POST['ids'] ?? [];
      $count = 0;
      foreach ($ids as $iid) {
        $iid = intval($iid);
        if ($iid > 0) {
          $stmt = $pdo->prepare('SELECT file_path, title FROM images WHERE id = ?');
          $stmt->execute([$iid]);
          $row = $stmt->fetch();
          if ($row) {
            $file = __DIR__ . '/../' . ltrim($row['file_path'], '/');
            if (file_exists($file)) @unlink($file);
          }
          $stmt = $pdo->prepare('DELETE FROM images WHERE id = ?');
          $stmt->execute([$iid]);
          $count++;
        }
      }
      log_action($pdo, $_SESSION['username'] ?? 'unknown', 'batch_permanent_delete', 'permanently deleted ' . $count . ' images');
      echo '<div class="alert alert-success">已永久删除 ' . $count . ' 张图片。</div>';
    }
  }
}

// 查询回收站条目
try {
  $countStmt = $pdo->prepare('SELECT COUNT(*) FROM images WHERE is_deleted = 1');
  $countStmt->execute();
  $total = (int)$countStmt->fetchColumn();

  $stmt = $pdo->prepare('SELECT id, title, file_path, is_remote, deleted_at, deleted_by FROM images WHERE is_deleted = 1 ORDER BY deleted_at DESC LIMIT ? OFFSET ?');
  $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
  $stmt->bindValue(2, $offset, PDO::PARAM_INT);
  $stmt->execute();
  $images = $stmt->fetchAll();
} catch (PDOException $e) {
  // 如果 is_deleted 等列不存在，显示空列表
  error_log("查询回收站失败: " . $e->getMessage());
  $total = 0;
  $images = [];
}

$totalPages = max(1, ceil($total / $perPage));

?>
<div class="mt-3">
  <h3>回收站</h3>
  <form method="post" id="recycleBatchForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <div class="mb-2">
      <label><input type="checkbox" id="recycleSelectAll"> 全选</label>
      <button class="btn btn-sm btn-success ms-2 js-recycle-action" type="submit" data-action="batch_restore" data-confirm="确认恢复选中图片？">批量恢复</button>
      <button class="btn btn-sm btn-danger ms-2 js-recycle-action" type="submit" data-action="batch_permanent_delete" data-confirm="确认永久删除选中图片？ 此操作不可恢复">批量永久删除</button>
    </div>
    <input type="hidden" name="action" value="">
    <input type="hidden" name="id" id="singleRecycleId" value="">
    <div class="row">
    <?php foreach ($images as $img): ?>
      <div class="col-3 mb-3">
        <div class="card">
        <?php $imgUrl = build_image_url($img['file_path'], (int)$img['is_remote']); ?>
        <img src="<?php echo htmlspecialchars($imgUrl); ?>" class="card-img-top image-card-thumb" alt="<?php echo htmlspecialchars($img['title']); ?>">
          <div class="card-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="ids[]" value="<?php echo (int)$img['id']; ?>" id="rimg_<?php echo (int)$img['id']; ?>">
              <label class="form-check-label" for="rimg_<?php echo (int)$img['id']; ?>">选择</label>
            </div>
            <h6 class="card-title"><?php echo htmlspecialchars($img['title']); ?></h6>
            <p class="card-text"><small class="text-muted">删除时间: <?php echo htmlspecialchars($img['deleted_at']); ?> by <?php echo htmlspecialchars($img['deleted_by']); ?></small></p>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-primary" href="../download.php?id=<?php echo (int)$img['id']; ?>">下载</a>
              <button type="button" class="btn btn-sm btn-success js-form-action" data-form="recycleBatchForm" data-action="restore" data-id="<?php echo (int)$img['id']; ?>" data-confirm="确认恢复此图片？">恢复</button>
              <button type="button" class="btn btn-sm btn-danger js-form-action" data-form="recycleBatchForm" data-action="permanent_delete" data-id="<?php echo (int)$img['id']; ?>" data-confirm="确认永久删除此图片？ 此操作不可恢复">永久删除</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </form>
  <nav>
    <ul class="pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<?php include __DIR__ . '/footer.php';
