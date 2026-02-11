<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = '所有字段不能为空';
    } elseif ($new_password !== $confirm_password) {
        $error = '新密码和确认密码不匹配';
    } elseif (strlen($new_password) < 8) {
        $error = '新密码至少需要 8 个字符';
    } else {
        try {
            $username = $_SESSION['username'] ?? '';
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = '用户不存在';
            } elseif (!password_verify($current_password, $user['password_hash'])) {
                $error = '当前密码不正确';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?');
                $updateStmt->execute([$new_hash, $username]);
                
                $success = '密码修改成功';
                log_action($pdo, $username, 'change_password', 'password changed successfully');
            }
        } catch (PDOException $e) {
            $error = '数据库错误，请稍后重试';
            error_log('Change password error: ' . $e->getMessage());
        }
    }
}

$pageTitle = '修改密码';
$showNavbar = true;
$includeGalleryScripts = false;
$includeClockScript = false;
include __DIR__ . '/../Gallery/header.php';
?>

<div class="container mt-4 flex-grow-1">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">修改密码</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">当前密码</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">新密码</label>
                            <input type="password" class="form-control" id="new_password" 
                                   name="new_password" required minlength="8">
                            <small class="form-text text-muted">至少 8 个字符</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">确认新密码</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">修改密码</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../Gallery/footer.php'; ?>