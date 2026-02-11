<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
$page_title = '设置';

$success = '';
$error = '';

$fields = [
    'site_title' => ['label' => '站点标题', 'type' => 'text'],
    'site_description' => ['label' => '站点描述', 'type' => 'textarea'],
    'site_url' => ['label' => '站点地址', 'type' => 'url'],
    'site_logo' => ['label' => '站点 Logo (URL)', 'type' => 'url'],
    'meta_title' => ['label' => 'Meta 标题', 'type' => 'text'],
    'meta_keywords' => ['label' => 'Meta 关键词', 'type' => 'text'],
    'meta_description' => ['label' => 'Meta 描述', 'type' => 'textarea'],
    'allow_registration' => ['label' => '允许注册', 'type' => 'select'],
    'require_email_verification' => ['label' => '注册需邮箱验证', 'type' => 'select'],
    'force_https' => ['label' => '强制 HTTPS', 'type' => 'select'],
    'icp_number' => ['label' => 'ICP备案号', 'type' => 'text'],
    'icp_link' => ['label' => 'ICP备案链接', 'type' => 'url'],
    'security_number' => ['label' => '公网安备号', 'type' => 'text'],
    'security_link' => ['label' => '公网安备链接', 'type' => 'url'],
    'backup_path' => ['label' => '备份目录', 'type' => 'text']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'CSRF 验证失败';
    } else {
        $updateStmt = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($fields as $key => $meta) {
            $value = $_POST[$key] ?? '';
            if ($meta['type'] !== 'select') {
                $value = trim($value);
            }
            $updateStmt->execute([$key, $value]);
        }

        $success = '设置已保存';
    }
}

$settings = [];
$stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include __DIR__ . '/header.php';
?>
<div class="mt-3">
    <h3>站点设置</h3>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" class="card p-4 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div class="row g-3">
            <?php foreach ($fields as $key => $meta): ?>
                <?php $value = $settings[$key] ?? ''; ?>
                <div class="col-12">
                    <label class="form-label" for="<?php echo $key; ?>">
                        <?php echo htmlspecialchars($meta['label']); ?>
                    </label>

                    <?php if ($meta['type'] === 'textarea'): ?>
                        <textarea class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>" rows="3"><?php echo htmlspecialchars($value); ?></textarea>
                    <?php elseif ($meta['type'] === 'select'): ?>
                        <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                            <option value="1" <?php echo $value === '1' ? 'selected' : ''; ?>>启用</option>
                            <option value="0" <?php echo $value === '0' ? 'selected' : ''; ?>>关闭</option>
                        </select>
                    <?php else: ?>
                        <input class="form-control" type="<?php echo $meta['type']; ?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                        <?php if ($key === 'icp_number' || $key === 'security_number'): ?>
                            <div class="form-text">服务器在国外可留空，未填写则前台不展示。</div>
                        <?php elseif ($key === 'backup_path'): ?>
                            <div class="form-text">建议设置为 ../backup（Gallery 同级目录），路径需可写。</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
