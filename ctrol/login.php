<?php
require_once __DIR__ . '/bootstrap.php';

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                admin_on_login();
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                log_action($pdo, $username, 'login', 'user login success');
                header('Location: /admin/index.php');
                exit;
            } else {
                $error = '用户名或密码错误';
                log_action($pdo, $username, 'login_failed', 'login attempt failed');
            }
        } catch (PDOException $e) {
            $error = '数据库错误，请稍后重试';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

$pageTitle = '管理员登录';
$showNavbar = false;
$bodyClass = 'bg-light';
include __DIR__ . '/header.php';
?>

<div class="container mt-5 flex-grow-1">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h5 class="card-title text-center mb-4">管理员登录</h5>
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">密码</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">登录</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php';