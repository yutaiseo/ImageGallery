<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$page_title = '操作日志';
include __DIR__ . '/header.php';

// 简单分页
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// 导出CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=action_logs.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','timestamp','username','action_type','details','ip_address']);
        $stmt = $pdo->prepare('SELECT id, timestamp, username, action_type, details, ip_address FROM user_action_logs ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) {
        // 表不存在，返回 404
        error_log("导出日志失败: " . $e->getMessage());
        header('HTTP/1.1 404 Not Found');
        echo '操作日志表不存在';
        exit;
    }
}

// 列表显示
try {
    $stmt = $pdo->prepare('SELECT id, timestamp, username, action_type, details, ip_address FROM user_action_logs ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    $count = (int)$pdo->query('SELECT COUNT(*) FROM user_action_logs')->fetchColumn();
} catch (PDOException $e) {
    // 如果表不存在，显示空列表
    error_log("查询日志表失败: " . $e->getMessage());
    $logs = [];
    $count = 0;
}
$totalPages = max(1, ceil($count / $perPage));

?>
<div class="mt-3">
  <h3>管理员操作日志</h3>
  <div class="mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="?export=csv">导出当前页 CSV</a>
  </div>
  <table class="table table-sm">
    <thead><tr><th>ID</th><th>时间</th><th>用户名</th><th>操作</th><th>详情</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?php echo htmlspecialchars($l['id']); ?></td>
        <td><?php echo htmlspecialchars($l['timestamp']); ?></td>
        <td><?php echo htmlspecialchars($l['username']); ?></td>
        <td><?php echo htmlspecialchars($l['action_type']); ?></td>
        <td><?php echo htmlspecialchars($l['details']); ?></td>
        <td><?php echo htmlspecialchars($l['ip_address']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

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
