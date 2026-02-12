<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_security.php';

require_admin();
require_superadmin(); // 只有超级管理员可以管理IP黑名单

$message = '';
$messageType = 'success';

// 处理添加IP到黑名单
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'CSRF验证失败';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $ipAddress = trim($_POST['ip_address'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $blockType = $_POST['block_type'] ?? 'temporary';
            
            if (empty($ipAddress)) {
                $message = 'IP地址不能为空';
                $messageType = 'danger';
            } elseif (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                $message = 'IP地址格式不正确';
                $messageType = 'danger';
            } else {
                if (add_to_blacklist($pdo, $ipAddress, $reason, $blockType, $_SESSION['username'])) {
                    $message = 'IP已添加到黑名单';
                    log_action($pdo, $_SESSION['username'], 'ip_blacklist_add', "IP: $ipAddress, Type: $blockType");
                } else {
                    $message = '添加失败，请稍后重试';
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'remove') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("SELECT ip_address FROM ip_blacklist WHERE id = ?");
                    $stmt->execute([$id]);
                    $ipData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("DELETE FROM ip_blacklist WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $message = 'IP已从黑名单移除';
                        log_action($pdo, $_SESSION['username'], 'ip_blacklist_remove', "IP: " . ($ipData['ip_address'] ?? 'unknown'));
                    } else {
                        $message = '移除失败';
                        $messageType = 'danger';
                    }
                } catch (PDOException $e) {
                    $message = '操作失败：' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

// 获取黑名单列表
try {
    $stmt = $pdo->query("
        SELECT 
            id, 
            ip_address, 
            reason, 
            block_type, 
            created_at, 
            expires_at, 
            created_by,
            CASE 
                WHEN block_type = 'permanent' THEN '永久'
                WHEN expires_at > NOW() THEN CONCAT(TIMESTAMPDIFF(MINUTE, NOW(), expires_at), '分钟')
                ELSE '已过期'
            END as remaining_time,
            CASE 
                WHEN block_type = 'permanent' THEN 1
                WHEN expires_at > NOW() THEN 1
                ELSE 0
            END as is_active
        FROM ip_blacklist 
        ORDER BY created_at DESC
    ");
    $blacklistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $blacklistItems = [];
    $message = '获取黑名单失败：' . $e->getMessage();
    $messageType = 'danger';
}

// 获取最近登录失败记录
try {
    $stmt = $pdo->query("
        SELECT 
            username,
            ip_address,
            COUNT(*) as failure_count,
            MAX(attempt_time) as last_attempt,
            is_blocked
        FROM login_attempts 
        WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY username, ip_address, is_blocked
        ORDER BY failure_count DESC, last_attempt DESC
        LIMIT 50
    ");
    $recentFailures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentFailures = [];
}

$pageTitle = 'IP黑名单管理';
include __DIR__ . '/header.php';
?>

<div class="container-fluid mt-4">
    <h2><i class="bi bi-shield-lock"></i> IP黑名单管理</h2>
    <p class="text-muted">管理被封禁的IP地址，防止恶意登录攻击</p>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- 添加IP到黑名单 -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 添加IP到黑名单</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="ip_address" class="form-label">IP地址</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" 
                                   placeholder="例如：192.168.1.100" required>
                            <small class="text-muted">支持IPv4和IPv6格式</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="block_type" class="form-label">封禁类型</label>
                            <select class="form-select" id="block_type" name="block_type" required>
                                <option value="temporary">临时封禁（15分钟）</option>
                                <option value="permanent">永久封禁</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">封禁原因</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="例如：多次登录失败、恶意攻击等"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-shield-plus"></i> 添加到黑名单
                        </button>
                    </form>
                </div>
            </div>

            <!-- 统计信息 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up"></i> 统计信息</h6>
                </div>
                <div class="card-body">
                    <?php
                    $activeCount = count(array_filter($blacklistItems, fn($item) => $item['is_active']));
                    $expiredCount = count($blacklistItems) - $activeCount;
                    $failureCount = count($recentFailures);
                    ?>
                    <ul class="list-unstyled mb-0">
                        <li><strong>活跃黑名单：</strong> <?= $activeCount ?> 个</li>
                        <li><strong>已过期：</strong> <?= $expiredCount ?> 个</li>
                        <li><strong>24小时失败记录：</strong> <?= $failureCount ?> 条</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- IP黑名单列表 -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> 黑名单列表</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($blacklistItems)): ?>
                    <p class="text-muted text-center py-4">暂无黑名单记录</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>IP地址</th>
                                    <th>类型</th>
                                    <th>状态</th>
                                    <th>剩余时间</th>
                                    <th>添加时间</th>
                                    <th>操作者</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklistItems as $item): ?>
                                <tr class="<?= $item['is_active'] ? '' : 'table-secondary' ?>">
                                    <td><code><?= htmlspecialchars($item['ip_address']) ?></code></td>
                                    <td>
                                        <?php if ($item['block_type'] === 'permanent'): ?>
                                        <span class="badge bg-danger">永久</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">临时</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['is_active']): ?>
                                        <span class="badge bg-success">生效中</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">已过期</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['remaining_time']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($item['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($item['created_by'] ?? '-') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#detailModal<?= $item['id'] ?>">
                                            详情
                                        </button>
                                        <form method="post" class="d-inline" 
                                              onsubmit="return confirm('确定要移除此IP黑名单吗？')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">移除</button>
                                        </form>
                                    </td>
                                </tr>
                                
                                <!-- 详情模态框 -->
                                <div class="modal fade" id="detailModal<?= $item['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">黑名单详情</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <table class="table table-sm">
                                                    <tr>
                                                        <th>IP地址：</th>
                                                        <td><code><?= htmlspecialchars($item['ip_address']) ?></code></td>
                                                    </tr>
                                                    <tr>
                                                        <th>封禁类型：</th>
                                                        <td><?= $item['block_type'] === 'permanent' ? '永久封禁' : '临时封禁' ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>封禁原因：</th>
                                                        <td><?= htmlspecialchars($item['reason'] ?: '未填写') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>添加时间：</th>
                                                        <td><?= $item['created_at'] ?></td>
                                                    </tr>
                                                    <?php if ($item['expires_at']): ?>
                                                    <tr>
                                                        <th>过期时间：</th>
                                                        <td><?= $item['expires_at'] ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <th>操作者：</th>
                                                        <td><?= htmlspecialchars($item['created_by'] ?? '系统自动') ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 最近登录失败记录 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> 最近24小时登录失败记录</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentFailures)): ?>
                    <p class="text-muted text-center py-3">暂无失败记录</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>用户名</th>
                                    <th>IP地址</th>
                                    <th>失败次数</th>
                                    <th>最后尝试</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentFailures as $failure): ?>
                                <tr class="<?= $failure['failure_count'] >= 3 ? 'table-warning' : '' ?>">
                                    <td><?= htmlspecialchars($failure['username']) ?></td>
                                    <td><code><?= htmlspecialchars($failure['ip_address']) ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $failure['failure_count'] >= 5 ? 'danger' : ($failure['failure_count'] >= 3 ? 'warning' : 'secondary') ?>">
                                            <?= $failure['failure_count'] ?> 次
                                        </span>
                                    </td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($failure['last_attempt'])) ?></td>
                                    <td>
                                        <?php if ($failure['is_blocked']): ?>
                                        <span class="badge bg-danger">已封禁</span>
                                        <?php elseif ($failure['failure_count'] >= 3): ?>
                                        <span class="badge bg-warning">警告</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php';
