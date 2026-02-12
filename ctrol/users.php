<?php
require_once __DIR__ . '/bootstrap.php';
require_superadmin();
require_once __DIR__ . '/logger.php';
$page_title = '用户管理';
include __DIR__ . '/header.php';

function count_superadmins($pdo)
{
  $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'");
  return (int)$stmt->fetchColumn();
}

// 参数：分页与搜索
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$alerts = [];

// 处理动作（新增/修改角色/删除）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) {
    $alerts[] = ['type' => 'danger', 'text' => 'CSRF 验证失败'];
  } elseif ($action === 'create') {
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $newRole = $_POST['new_role'] ?? 'admin';

    if ($newUsername === '' || $newPassword === '') {
      $alerts[] = ['type' => 'danger', 'text' => '用户名和密码不能为空'];
    } elseif (!in_array($newRole, ['admin', 'superadmin', 'demo'], true)) {
      $alerts[] = ['type' => 'danger', 'text' => '角色不合法'];
    } elseif (strlen($newPassword) < 8) {
      $alerts[] = ['type' => 'danger', 'text' => '密码至少 8 位'];
    } else {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
      $stmt->execute([$newUsername]);
      if ($stmt->fetchColumn()) {
        $alerts[] = ['type' => 'danger', 'text' => '用户名已存在'];
      } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
        $stmt->execute([$newUsername, $hash, $newRole]);
        log_action($pdo, $_SESSION['username'] ?? 'unknown', 'create_user', 'created user username=' . $newUsername . ' role=' . $newRole);
        $alerts[] = ['type' => 'success', 'text' => '已创建用户'];
      }
    }
  } elseif ($action === 'update_role') {
    $id = intval($_POST['id'] ?? 0);
    $newRole = $_POST['role'] ?? '';
    if ($id <= 0 || !in_array($newRole, ['admin', 'superadmin', 'demo'], true)) {
      $alerts[] = ['type' => 'danger', 'text' => '参数错误'];
    } else {
      $stmt = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if (!$row) {
        $alerts[] = ['type' => 'danger', 'text' => '用户不存在'];
      } else {
        $currentRole = $row['role'] ?? '';
        $isSelf = ((int)($_SESSION['user_id'] ?? 0) === $id);
        if ($currentRole === 'superadmin' && $newRole !== 'superadmin' && count_superadmins($pdo) <= 1) {
          $alerts[] = ['type' => 'danger', 'text' => '至少保留一个超级管理员'];
        } elseif ($isSelf && $newRole !== 'superadmin' && count_superadmins($pdo) <= 1) {
          $alerts[] = ['type' => 'danger', 'text' => '不能降级当前唯一的超级管理员'];
        } else {
          $update = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
          $update->execute([$newRole, $id]);
          log_action($pdo, $_SESSION['username'] ?? 'unknown', 'update_role', 'user id=' . $id . ' role=' . $newRole);
          $alerts[] = ['type' => 'success', 'text' => '角色已更新'];
        }
      }
    }
  } elseif ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
      $stmt = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if ($row) {
        $deletedUser = $row['username'] ?? '';
        $deletedRole = $row['role'] ?? '';
        if ((int)($_SESSION['user_id'] ?? 0) === $id) {
          $alerts[] = ['type' => 'danger', 'text' => '不能删除当前登录用户'];
        } elseif ($deletedRole === 'superadmin' && count_superadmins($pdo) <= 1) {
          $alerts[] = ['type' => 'danger', 'text' => '至少保留一个超级管理员'];
        } else {
          $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
          $stmt->execute([$id]);
          log_action($pdo, $_SESSION['username'] ?? 'unknown', 'delete_user', 'deleted user id=' . $id . ' username=' . $deletedUser);
          $alerts[] = ['type' => 'success', 'text' => '已删除用户'];
        }
      }
    }
  } elseif ($action === 'batch_delete') {
    $ids = $_POST['ids'] ?? [];
    $count = 0;
    $stmtSel = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
    $stmtDel = $pdo->prepare('DELETE FROM users WHERE id = ?');
    foreach ($ids as $iid) {
      $iid = intval($iid);
      if ($iid > 0) {
        $stmtSel->execute([$iid]);
        $row = $stmtSel->fetch();
        if (!$row) {
          continue;
        }
        $uname = $row['username'] ?? '';
        $role = $row['role'] ?? '';
        if ((int)($_SESSION['user_id'] ?? 0) === $iid) {
          continue;
        }
        if ($role === 'superadmin' && count_superadmins($pdo) <= 1) {
          continue;
        }
        $stmtDel->execute([$iid]);
        log_action($pdo, $_SESSION['username'] ?? 'unknown', 'batch_delete_user', 'deleted user id=' . $iid . ' username=' . $uname);
        $count++;
      }
    }
    $alerts[] = ['type' => 'success', 'text' => '已删除 ' . $count . ' 个用户'];
  }
}

// 总数
if ($search !== '') {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE ?');
    $countStmt->execute(['%' . $search . '%']);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE username LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, '%' . $search . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} else {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stmt = $pdo->prepare('SELECT id, username, role, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
}

 $totalPages = max(1, ceil($total / $perPage));

?>
<div class="mt-3">
  <h3>用户管理</h3>
  <?php foreach ($alerts as $alert): ?>
    <div class="alert alert-<?php echo htmlspecialchars($alert['type']); ?> alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($alert['text']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endforeach; ?>

  <div class="card mb-3">
    <div class="card-header">新增管理员</div>
    <div class="card-body">
      <form class="row g-2" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="action" value="create">
        <div class="col-md-3">
          <input class="form-control" name="new_username" placeholder="用户名" required>
        </div>
        <div class="col-md-3">
          <input class="form-control" type="password" name="new_password" placeholder="密码（至少8位）" required>
        </div>
        <div class="col-md-3">
          <select class="form-select" name="new_role">
            <option value="admin" selected>管理员</option>
            <option value="superadmin">超级管理员</option>
            <option value="demo">演示账号</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary w-100">创建</button>
        </div>
      </form>
    </div>
  </div>

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
      <thead><tr><th></th><th>ID</th><th>用户名</th><th>角色</th><th>注册时间</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?php echo (int)$u['id']; ?>"></td>
          <td><?php echo htmlspecialchars($u['id']); ?></td>
          <td><?php echo htmlspecialchars($u['username']); ?></td>
          <td>
            <span class="badge bg-<?php echo ($u['role'] === 'superadmin') ? 'danger' : ($u['role'] === 'demo' ? 'warning' : 'secondary'); ?>">
              <?php echo htmlspecialchars($u['role']); ?>
            </span>
          </td>
          <td><?php echo htmlspecialchars($u['created_at']); ?></td>
          <td>
            <form method="post" class="d-inline-block">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="action" value="update_role">
              <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
              <select name="role" class="form-select form-select-sm d-inline-block w-auto">
                <option value="admin" <?php echo ($u['role'] === 'admin') ? 'selected' : ''; ?>>管理员</option>
                <option value="superadmin" <?php echo ($u['role'] === 'superadmin') ? 'selected' : ''; ?>>超级管理员</option>
                <option value="demo" <?php echo ($u['role'] === 'demo') ? 'selected' : ''; ?>>演示账号</option>
              </select>
              <button class="btn btn-sm btn-outline-primary">更新</button>
            </form>
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
