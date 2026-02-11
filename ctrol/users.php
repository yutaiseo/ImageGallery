<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once __DIR__ . '/logger.php';
$page_title = '用户管理';
include __DIR__ . '/header.php';

// 参数：分页与搜索
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 处理删除动作（单个或批量）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  if ($action === 'delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
      echo '<div class="alert alert-danger">CSRF 验证失败</div>';
    } else {
      $id = intval($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $deletedUser = $row['username'] ?? '';
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        log_action($pdo, $_SESSION['username'] ?? 'unknown', 'delete_user', 'deleted user id=' . $id . ' username=' . $deletedUser);
        echo '<div class="alert alert-success">已删除用户。</div>';
      }
    }
  } elseif ($action === 'batch_delete') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
      echo '<div class="alert alert-danger">CSRF 验证失败</div>';
    } else {
      $ids = $_POST['ids'] ?? [];
      $count = 0;
      $stmtSel = $pdo->prepare('SELECT username FROM users WHERE id = ?');
      $stmtDel = $pdo->prepare('DELETE FROM users WHERE id = ?');
      foreach ($ids as $iid) {
        $iid = intval($iid);
        if ($iid > 0) {
          $stmtSel->execute([$iid]);
          $row = $stmtSel->fetch();
          $uname = $row['username'] ?? '';
          $stmtDel->execute([$iid]);
          log_action($pdo, $_SESSION['username'] ?? 'unknown', 'batch_delete_user', 'deleted user id=' . $iid . ' username=' . $uname);
          $count++;
        }
      }
      echo '<div class="alert alert-success">已删除 ' . $count . ' 个用户。</div>';
    }
  }
}

// 总数
if ($search !== '') {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE ?');
    $countStmt->execute(['%' . $search . '%']);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, username, created_at FROM users WHERE username LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, '%' . $search . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} else {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stmt = $pdo->prepare('SELECT id, username, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
}

 $totalPages = max(1, ceil($total / $perPage));

?>
<div class="mt-3">
  <h3>用户列表</h3>
  <form class="row g-2 mb-3" method="get">
    <div class="col-auto">
      <input class="form-control" name="search" placeholder="搜索用户名" value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-auto">
      <button class="btn btn-secondary">搜索</button>
    </div>
  </form>

  <form method="post" id="userBatchForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <input type="hidden" name="action" value="batch_delete">
    <input type="hidden" name="id" id="singleUserId" value="">
    <div class="mb-2">
      <label><input type="checkbox" id="selectAllUsers"> 全选</label>
      <button class="btn btn-sm btn-danger ms-2 js-confirm" data-confirm="确认删除选中用户？ 此操作不可恢复">批量删除</button>
    </div>
    <table class="table table-striped">
      <thead><tr><th></th><th>ID</th><th>用户名</th><th>注册时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?php echo (int)$u['id']; ?>"></td>
          <td><?php echo htmlspecialchars($u['id']); ?></td>
          <td><?php echo htmlspecialchars($u['username']); ?></td>
          <td><?php echo htmlspecialchars($u['created_at']); ?></td>
          <td>
            <button type="button" class="btn btn-sm btn-danger js-form-action" data-form="userBatchForm" data-action="delete" data-id="<?php echo (int)$u['id']; ?>" data-confirm="确认删除该用户？">删除</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </form>

  <nav>
    <ul class="pagination">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>"><?php echo $p; ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<?php include __DIR__ . '/footer.php';
