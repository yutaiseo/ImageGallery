<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once __DIR__ . '/logger.php';
$isDemo = is_demo();
$page_title = '图片管理';
include __DIR__ . '/header.php';

// 参数：分页与搜索
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 处理删除动作（软删除，移动到回收站）及批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($isDemo) {
    echo '<div class="alert alert-warning">演示账号为只读模式，无法执行操作。</div>';
  } else {
  $action = $_POST['action'];
  if ($action === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
      echo '<div class="alert alert-danger">CSRF 验证失败</div>';
    } else {
      $id = intval($_POST['id'] ?? 0);
      if ($id > 0) {
        // 标记为已删除（软删除）
        $stmt = $pdo->prepare('UPDATE images SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?');
        $stmt->execute([$_SESSION['username'] ?? 'unknown', $id]);
        log_action($pdo, $_SESSION['username'] ?? 'unknown', 'soft_delete_image', 'moved image id=' . $id . ' to recycle bin');
        echo '<div class="alert alert-success">已移入回收站。</div>';
      }
    }
  } elseif ($action === 'batch_delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
      echo '<div class="alert alert-danger">CSRF 验证失败</div>';
    } else {
      $ids = $_POST['ids'] ?? [];
      $count = 0;
      $stmt = $pdo->prepare('UPDATE images SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?');
      foreach ($ids as $iid) {
        $iid = intval($iid);
        if ($iid > 0) {
          $stmt->execute([$_SESSION['username'] ?? 'unknown', $iid]);
          $count++;
        }
      }
      log_action($pdo, $_SESSION['username'] ?? 'unknown', 'batch_soft_delete', 'moved ' . $count . ' images to recycle bin');
      echo '<div class="alert alert-success">已将 ' . $count . ' 张图片移入回收站。</div>';
    }
  }
  }
}

// 列表查询（仅显示未删除图片，并包含 is_remote 字段）
try {
  if ($search !== '') {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM images WHERE is_deleted = 0 AND title LIKE ?');
    $countStmt->execute(['%' . $search . '%']);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote, created_at FROM images WHERE is_deleted = 0 AND title LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, '%' . $search . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();
  } else {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM images WHERE is_deleted = 0')->fetchColumn();
    $stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote, created_at FROM images WHERE is_deleted = 0 ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();
  }
} catch (PDOException $e) {
  // 如果 is_deleted 列不存在，尝试查询不带该条件的版本
  error_log("查询失败（列不存在）: " . $e->getMessage());
  if ($search !== '') {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM images WHERE title LIKE ?');
    $countStmt->execute(['%' . $search . '%']);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote, created_at FROM images WHERE title LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, '%' . $search . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();
  } else {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM images')->fetchColumn();
    $stmt = $pdo->prepare('SELECT id, title, description, file_path, is_remote, created_at FROM images ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();
  }
}

$totalPages = max(1, ceil($total / $perPage));

?>
<div class="mt-3 admin-card">
  <h3 class="admin-card-title"><i class="fas fa-images"></i>图片列表</h3>
  <form class="row g-3 mb-4 align-items-end" method="get">
    <div class="col-12 col-md">
      <label class="form-label"><i class="fas fa-search"></i> 搜索标题</label>
      <input class="form-control" name="search" placeholder="输入关键词搜索..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-auto">
      <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> 搜索</button>
    </div>
  </form>
  <form method="post" id="batchForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <input type="hidden" name="action" value="batch_delete">
    <input type="hidden" name="id" id="singleImageId" value="">
    <div class="mb-2">
      <?php if (!$isDemo): ?>
      <label class="form-check"><input type="checkbox" id="selectAll" class="form-check-input"> <span>全选所有图片</span></label>
      <button type="submit" class="btn btn-sm btn-danger ms-3 js-confirm" data-confirm="确认将选中的图片移入回收站？"><i class="fas fa-trash"></i> 批量删除</button>
      <?php endif; ?>
    </div>
    <div class="row g-4 mt-2">
    <?php foreach ($images as $img): ?>
      <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card h-100">
          <?php $imgUrl = build_image_url($img['file_path'], (int)$img['is_remote']); ?>
          <div style="height: 200px; background-size: cover; background-position: center; background-image: url('<?php echo htmlspecialchars($imgUrl); ?>');" class="card-img-top"></div>
          <div class="card-body d-flex flex-column">
            <?php if (!$isDemo): ?>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="ids[]" value="<?php echo (int)$img['id']; ?>" id="img_<?php echo (int)$img['id']; ?>">
              <label class="form-check-label" for="img_<?php echo (int)$img['id']; ?>">选中此项</label>
            </div>
            <?php endif; ?>
            <h6 class="card-title"><?php echo htmlspecialchars($img['title']); ?></h6>
            <p class="card-text"><small class="text-muted"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($img['created_at']); ?></small></p>
            <div class="d-flex gap-2 mt-auto">
              <a class="btn btn-sm btn-primary flex-grow-1" href="../download.php?id=<?php echo (int)$img['id']; ?>"><i class="fas fa-download"></i> 下载</a>
              <?php if (!$isDemo): ?>
              <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal"
                      data-image-id="<?php echo (int)$img['id']; ?>"
                      data-image-title="<?php echo htmlspecialchars($img['title']); ?>"
                      data-image-description="<?php echo htmlspecialchars($img['description'] ?? ''); ?>"
                      data-image-url="<?php echo htmlspecialchars($imgUrl); ?>"
                      title="编辑图片"><i class="fas fa-edit"></i></button>
              <button type="button" class="btn btn-sm btn-danger js-form-action" data-form="batchForm" data-action="delete" data-id="<?php echo (int)$img['id']; ?>" data-confirm="确认删除此图片吗？" title="删除图片"><i class="fas fa-trash"></i></button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </form>
  <?php if (!$isDemo): ?>
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="update_image.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <input type="hidden" name="id" id="editImageId">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-image"></i> 编辑图片信息</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label"><i class="fas fa-heading"></i> 图片标题 <span class="text-danger">*</span></label>
              <input type="text" name="title" id="editImageTitle" class="form-control" placeholder="输入图片标题..." required>
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="fas fa-align-left"></i> 描述信息</label>
              <textarea name="description" id="editImageDescription" class="form-control" rows="3" placeholder="输入图片描述..."></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="fas fa-photo-video"></i> 当前图片预览</label>
              <img src="" id="editImagePreview" class="img-fluid mb-2 edit-image-preview" alt="图片预览">
            </div>
            <div class="mb-3">
              <label class="form-label"><i class="fas fa-cloud-upload-alt"></i> 替换图片</label>
              <input type="file" name="new_image" class="form-control" accept="image/jpeg, image/png, image/gif, image/webp">
              <small class="form-text text-muted"><i class="fas fa-info-circle"></i> 留空则不更新图片（支持的格式：JPG, PNG, GIF, WebP，最大 2MB）</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> 取消</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存修改</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <nav class="mt-4">
    <ul class="pagination justify-content-center">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<?php include __DIR__ . '/footer.php';
