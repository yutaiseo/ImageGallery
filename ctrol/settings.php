<?php
require_once __DIR__ . '/bootstrap.php';
require_admin_write();
$page_title = '系统设置';

$success = '';
$error = '';

// 分组配置项
$configGroups = [
    'basic' => [
        'title' => '基础设置',
        'icon' => 'fa-cog',
        'fields' => [
            'site_title' => ['label' => '站点标题', 'type' => 'text', 'default' => 'ImageGallery', 'help' => '网站名称，显示在浏览器标题栏'],
            'site_description' => ['label' => '站点描述', 'type' => 'textarea', 'default' => '', 'help' => '网站简介，用于SEO'],
            'site_url' => ['label' => '站点地址', 'type' => 'url', 'default' => '', 'help' => '完整URL，如 https://img.example.com'],
            'site_logo' => ['label' => '站点Logo URL', 'type' => 'url', 'default' => '', 'help' => 'Logo图片地址'],
        ]
    ],
    'seo' => [
        'title' => 'SEO配置',
        'icon' => 'fa-search',
        'fields' => [
            'meta_title' => ['label' => 'Meta标题', 'type' => 'text', 'default' => '', 'help' => '搜索引擎显示的标题'],
            'meta_keywords' => ['label' => 'Meta关键词', 'type' => 'text', 'default' => '', 'help' => '多个关键词用逗号分隔'],
            'meta_description' => ['label' => 'Meta描述', 'type' => 'textarea', 'default' => '', 'help' => '搜索结果摘要'],
        ]
    ],
    'upload' => [
        'title' => '上传与压缩',
        'icon' => 'fa-image',
        'fields' => [
            'compress_quality' => ['label' => '压缩质量', 'type' => 'number', 'default' => '75', 'help' => '1-100，建议60-85，数值越高质量越好', 'min' => 1, 'max' => 100],
            'compress_max_size' => ['label' => '压缩目标大小 (KB)', 'type' => 'number', 'default' => '800', 'help' => '图片压缩后的目标大小', 'min' => 100, 'max' => 5000],
            'compress_threshold' => ['label' => '压缩阈值 (KB)', 'type' => 'number', 'default' => '800', 'help' => '仅大于此值的图片才压缩', 'min' => 100, 'max' => 5000],
            'allowed_extensions' => ['label' => '允许的文件类型', 'type' => 'text', 'default' => 'jpg,jpeg,png,gif,webp', 'help' => '多个类型用逗号分隔'],
            'max_upload_count' => ['label' => '单次最大上传数', 'type' => 'number', 'default' => '20', 'help' => '批量上传时的最大文件数', 'min' => 1, 'max' => 100],
        ]
    ],
    'security' => [
        'title' => '安全设置',
        'icon' => 'fa-shield-alt',
        'fields' => [
            'login_max_attempts' => ['label' => '登录失败阈值', 'type' => 'number', 'default' => '5', 'help' => '超过此次数将封禁IP', 'min' => 3, 'max' => 10],
            'login_block_duration' => ['label' => '临时封禁时长 (分钟)', 'type' => 'number', 'default' => '15', 'help' => '登录失败后的封禁时长', 'min' => 5, 'max' => 1440],
            'session_timeout' => ['label' => '会话超时 (分钟)', 'type' => 'number', 'default' => '120', 'help' => '无操作自动退出时间，0=永不过期', 'min' => 0, 'max' => 1440],
            'force_https' => ['label' => '强制HTTPS', 'type' => 'select', 'default' => '0', 'help' => '自动将HTTP重定向到HTTPS'],
        ]
    ],
    'worker' => [
        'title' => 'Worker配置',
        'icon' => 'fa-tasks',
        'fields' => [
            'worker_enabled' => ['label' => '启用Worker', 'type' => 'select', 'default' => '1', 'help' => '是否启用异步任务处理'],
            'worker_batch_limit' => ['label' => '单次处理任务数', 'type' => 'number', 'default' => '50', 'help' => 'Worker每次执行处理的最大任务数', 'min' => 1, 'max' => 200],
            'worker_max_attempts' => ['label' => '最大重试次数', 'type' => 'number', 'default' => '3', 'help' => '任务失败后的重试次数', 'min' => 1, 'max' => 5],
            'worker_delete_original' => ['label' => '删除原图', 'type' => 'select', 'default' => '0', 'help' => '压缩成功后是否删除原图以节省空间'],
        ]
    ],
    'gallery' => [
        'title' => '前台显示',
        'icon' => 'fa-th',
        'fields' => [
            'gallery_per_page' => ['label' => '每页显示数量', 'type' => 'number', 'default' => '12', 'help' => '前台图片列表每页显示的数量', 'min' => 6, 'max' => 100],
            'gallery_sort_order' => ['label' => '默认排序', 'type' => 'select_custom', 'default' => 'created_desc', 'help' => '图片列表的默认排序方式', 'options' => [
                'created_desc' => '最新上传',
                'created_asc' => '最早上传',
                'title_asc' => '标题A-Z',
                'title_desc' => '标题Z-A'
            ]],
            'gallery_layout' => ['label' => '默认布局', 'type' => 'select_custom', 'default' => 'grid', 'help' => '前台默认显示布局', 'options' => [
                'grid' => '网格布局',
                'masonry' => '瀑布流',
                'list' => '列表布局'
            ]],
            'show_image_count' => ['label' => '显示图片总数', 'type' => 'select', 'default' => '1', 'help' => '在前台显示图片总数统计'],
        ]
    ],
    'filing' => [
        'title' => '备案信息',
        'icon' => 'fa-file-certificate',
        'fields' => [
            'icp_number' => ['label' => 'ICP备案号', 'type' => 'text', 'default' => '', 'help' => '如：京ICP备12345678号'],
            'icp_link' => ['label' => 'ICP备案链接', 'type' => 'url', 'default' => '', 'help' => '备案查询链接'],
            'security_number' => ['label' => '公网安备号', 'type' => 'text', 'default' => '', 'help' => '如：京公网安备 11010802012345号'],
            'security_link' => ['label' => '公网安备链接', 'type' => 'url', 'default' => '', 'help' => '公安备案链接'],
        ]
    ],
    'other' => [
        'title' => '其他设置',
        'icon' => 'fa-ellipsis-h',
        'fields' => [
            'backup_path' => ['label' => '备份目录', 'type' => 'text', 'default' => '../backup', 'help' => '备份文件存储路径，建议使用相对路径'],
            'allow_registration' => ['label' => '允许注册', 'type' => 'select', 'default' => '0', 'help' => '是否开放用户注册'],
            'require_email_verification' => ['label' => '注册需邮箱验证', 'type' => 'select', 'default' => '0', 'help' => '注册时是否需要验证邮箱'],
            'enable_comments' => ['label' => '启用评论', 'type' => 'select', 'default' => '0', 'help' => '前台是否显示评论功能'],
        ]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'CSRF验证失败';
    } else {
        try {
            $updateStmt = $pdo->prepare(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );

            $updateCount = 0;
            foreach ($configGroups as $group) {
                foreach ($group['fields'] as $key => $meta) {
                    $value = $_POST[$key] ?? '';
                    if ($meta['type'] !== 'select' && $meta['type'] !== 'select_custom') {
                        $value = trim($value);
                    }
                    
                    // 验证数值范围
                    if ($meta['type'] === 'number' && $value !== '') {
                        $value = max($meta['min'] ?? 0, min($meta['max'] ?? PHP_INT_MAX, (int)$value));
                    }
                    
                    $updateStmt->execute([$key, $value]);
                    $updateCount++;
                }
            }

            $success = "设置已保存（共 {$updateCount} 项）";
            log_action($pdo, $_SESSION['username'], 'settings_update', "Updated {$updateCount} settings");
        } catch (PDOException $e) {
            $error = '保存失败：' . $e->getMessage();
            error_log('Settings update error: ' . $e->getMessage());
        }
    }
}

// 获取当前设置
$settings = [];
try {
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

include __DIR__ . '/header.php';
?>
<div class="container-fluid mt-4">
    <h2><i class="fas fa-cog"></i> 系统设置</h2>
    <p class="text-muted">配置站点基本信息、上传参数、安全策略等</p>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <!-- Tab导航 -->
        <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
            <?php $first = true; foreach ($configGroups as $groupKey => $group): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $first ? 'active' : ''; ?>" 
                        id="tab-<?php echo $groupKey; ?>" 
                        data-bs-toggle="tab" 
                        data-bs-target="#content-<?php echo $groupKey; ?>" 
                        type="button" 
                        role="tab">
                    <i class="fas <?php echo $group['icon']; ?>"></i>
                    <?php echo htmlspecialchars($group['title']); ?>
                </button>
            </li>
            <?php $first = false; endforeach; ?>
        </ul>

        <!-- Tab内容 -->
        <div class="tab-content" id="settingsTabContent">
            <?php $first = true; foreach ($configGroups as $groupKey => $group): ?>
            <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                 id="content-<?php echo $groupKey; ?>" 
                 role="tabpanel">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas <?php echo $group['icon']; ?>"></i>
                            <?php echo htmlspecialchars($group['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($group['fields'] as $key => $field): ?>
                            <?php $value = $settings[$key] ?? $field['default']; ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="<?php echo $key; ?>">
                                    <?php echo htmlspecialchars($field['label']); ?>
                                </label>

                                <?php if ($field['type'] === 'textarea'): ?>
                                    <textarea class="form-control" 
                                              id="<?php echo $key; ?>" 
                                              name="<?php echo $key; ?>" 
                                              rows="3"><?php echo htmlspecialchars($value); ?></textarea>
                                
                                <?php elseif ($field['type'] === 'select'): ?>
                                    <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                        <option value="1" <?php echo $value === '1' ? 'selected' : ''; ?>>启用</option>
                                        <option value="0" <?php echo ($value === '0' || $value === '') ? 'selected' : ''; ?>>关闭</option>
                                    </select>
                                
                                <?php elseif ($field['type'] === 'select_custom'): ?>
                                    <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>">
                                        <?php foreach ($field['options'] as $optKey => $optLabel): ?>
                                        <option value="<?php echo $optKey; ?>" <?php echo $value === $optKey ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($optLabel); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                
                                <?php elseif ($field['type'] === 'number'): ?>
                                    <input class="form-control" 
                                           type="number" 
                                           id="<?php echo $key; ?>" 
                                           name="<?php echo $key; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>"
                                           min="<?php echo $field['min'] ?? 0; ?>"
                                           max="<?php echo $field['max'] ?? ''; ?>">
                                
                                <?php else: ?>
                                    <input class="form-control" 
                                           type="<?php echo $field['type']; ?>" 
                                           id="<?php echo $key; ?>" 
                                           name="<?php echo $key; ?>" 
                                           value="<?php echo htmlspecialchars($value); ?>">
                                <?php endif; ?>

                                <?php if (!empty($field['help'])): ?>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($field['help']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php $first = false; endforeach; ?>
        </div>

        <!-- 保存按钮 -->
        <div class="mt-4 mb-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> 保存所有设置
            </button>
            <a href="/admin/index.php" class="btn btn-outline-secondary btn-lg ms-2">
                <i class="fas fa-times"></i> 取消
            </a>
        </div>
    </form>

    <!-- 配置说明 -->
    <div class="card mt-4 border-info">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0"><i class="fas fa-lightbulb"></i> 配置说明</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-image text-primary"></i> 图片压缩</h6>
                    <ul class="small">
                        <li><strong>压缩质量：</strong>建议60-85，质量越高文件越大</li>
                        <li><strong>目标大小：</strong>压缩后的目标文件大小，默认800KB</li>
                        <li><strong>压缩阈值：</strong>只有大于此值的图片才会压缩</li>
                        <li><strong>删除原图：</strong>启用后可节省50%以上存储空间</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-shield-alt text-success"></i> 安全设置</h6>
                    <ul class="small">
                        <li><strong>登录阈值：</strong>建议3-5次，过低可能误伤正常用户</li>
                        <li><strong>封禁时长：</strong>建议15-30分钟</li>
                        <li><strong>会话超时：</strong>0=永不过期，建议60-120分钟</li>
                        <li><strong>强制HTTPS：</strong>生产环境强烈建议启用</li>
                    </ul>
                </div>
                <div class="col-md-6 mt-3">
                    <h6><i class="fas fa-tasks text-warning"></i> Worker配置</h6>
                    <ul class="small">
                        <li><strong>批量限制：</strong>单次处理任务数，建议20-100</li>
                        <li><strong>重试次数：</strong>失败后的重试次数，建议2-3次</li>
                        <li><strong>Worker频率：</strong>通过Cron配置，建议每5分钟</li>
                    </ul>
                </div>
                <div class="col-md-6 mt-3">
                    <h6><i class="fas fa-th text-info"></i> 前台显示</h6>
                    <ul class="small">
                        <li><strong>每页数量：</strong>建议6-24之间</li>
                        <li><strong>排序方式：</strong>影响用户浏览体验</li>
                        <li><strong>默认布局：</strong>可在前台切换</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
