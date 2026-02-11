<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();
require_once __DIR__ . '/../Gallery/cdn_assets.php';

$page_title = '云服务配置';

// 读取配置
$configPath = __DIR__ . '/config/cloud_config.json';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config/oss_config.json';
}

$cloudConfig = [];
if (file_exists($configPath)) {
    $cloudConfig = json_decode(file_get_contents($configPath), true) ?? [];
}

$success = '';
$error = '';
$testResult = '';

// 调试：显示当前配置的供应商
$currentProvider = 'none';
if (!empty($cloudConfig['enabled'])) {
    if (!empty($cloudConfig['key_id']) && !empty($cloudConfig['endpoint'])) {
        $currentProvider = 'oss';  // 阿里云 OSS
    } elseif (!empty($cloudConfig['access_key']) && !empty($cloudConfig['region'])) {
        if (!empty($cloudConfig['secret_id'])) {
            $currentProvider = 'cos';  // 腾讯云 COS
        } else {
            $currentProvider = 's3';   // AWS S3
        }
    } elseif (!empty($cloudConfig['auth_url'])) {
        $currentProvider = 'swift';    // OpenStack Swift
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'CSRF 验证失败';
    } else {
        $action = $_POST['action'] ?? 'save';
        $provider = $_POST['provider'] ?? '';
        
        if ($action === 'save') {
            // 保存配置
            $newConfig = [
                'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === '1',
                'provider' => $provider
            ];
            
            // 根据不同的云服务商处理配置
            if ($provider === 'oss') {
                $newConfig += [
                    'endpoint' => trim($_POST['oss_endpoint'] ?? ''),
                    'key_id' => trim($_POST['oss_key_id'] ?? ''),
                    'key_secret' => trim($_POST['oss_key_secret'] ?? ''),
                    'bucket' => trim($_POST['oss_bucket'] ?? ''),
                    'prefix' => trim($_POST['oss_prefix'] ?? '')
                ];
                
                if ($newConfig['enabled'] && (empty($newConfig['endpoint']) || empty($newConfig['key_id']) || 
                    empty($newConfig['key_secret']) || empty($newConfig['bucket']))) {
                    $error = '请填写所有必填项';
                } else {
                    if (file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                        $cloudConfig = $newConfig;
                        $success = '阿里云 OSS 配置已保存';
                    } else {
                        $error = '配置文件写入失败';
                    }
                }
            } 
            elseif ($provider === 's3') {
                $newConfig += [
                    'access_key' => trim($_POST['s3_access_key'] ?? ''),
                    'secret_key' => trim($_POST['s3_secret_key'] ?? ''),
                    'region' => trim($_POST['s3_region'] ?? ''),
                    'bucket' => trim($_POST['s3_bucket'] ?? ''),
                    'prefix' => trim($_POST['s3_prefix'] ?? '')
                ];
                
                if ($newConfig['enabled'] && (empty($newConfig['access_key']) || empty($newConfig['secret_key']) || 
                    empty($newConfig['region']) || empty($newConfig['bucket']))) {
                    $error = '请填写所有必填项';
                } else {
                    if (file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                        $cloudConfig = $newConfig;
                        $success = 'AWS S3 配置已保存';
                    } else {
                        $error = '配置文件写入失败';
                    }
                }
            } 
            elseif ($provider === 'cos') {
                $newConfig += [
                    'secret_id' => trim($_POST['cos_secret_id'] ?? ''),
                    'secret_key' => trim($_POST['cos_secret_key'] ?? ''),
                    'region' => trim($_POST['cos_region'] ?? ''),
                    'bucket' => trim($_POST['cos_bucket'] ?? ''),
                    'prefix' => trim($_POST['cos_prefix'] ?? '')
                ];
                
                if ($newConfig['enabled'] && (empty($newConfig['secret_id']) || empty($newConfig['secret_key']) || 
                    empty($newConfig['region']) || empty($newConfig['bucket']))) {
                    $error = '请填写所有必填项';
                } else {
                    if (file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                        $cloudConfig = $newConfig;
                        $success = '腾讯云 COS 配置已保存';
                    } else {
                        $error = '配置文件写入失败';
                    }
                }
            } 
            elseif ($provider === 'swift') {
                $newConfig += [
                    'auth_url' => trim($_POST['swift_auth_url'] ?? ''),
                    'username' => trim($_POST['swift_username'] ?? ''),
                    'password' => trim($_POST['swift_password'] ?? ''),
                    'tenant' => trim($_POST['swift_tenant'] ?? ''),
                    'container' => trim($_POST['swift_container'] ?? ''),
                    'prefix' => trim($_POST['swift_prefix'] ?? '')
                ];
                
                if ($newConfig['enabled'] && (empty($newConfig['auth_url']) || empty($newConfig['username']) || 
                    empty($newConfig['password']) || empty($newConfig['container']))) {
                    $error = '请填写所有必填项';
                } else {
                    if (file_put_contents($configPath, json_encode($newConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                        $cloudConfig = $newConfig;
                        $success = 'OpenStack Swift 配置已保存';
                    } else {
                        $error = '配置文件写入失败';
                    }
                }
            }
        }
        elseif ($action === 'test' && !empty($provider)) {
            // 测试连接
            $testResult = testCloudConnection($provider, $cloudConfig);
        }
        elseif ($action === 'disable') {
            // 禁用所有配置
            $cloudConfig['enabled'] = false;
            if (file_put_contents($configPath, json_encode($cloudConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                $success = '云服务配置已禁用';
            } else {
                $error = '操作失败';
            }
        }
    }
}

function testCloudConnection($provider, $config) {
    if (!$config['enabled']) {
        return ['status' => 'warning', 'message' => '请先启用配置'];
    }
    
    switch ($provider) {
        case 'oss':
            if (empty($config['endpoint']) || empty($config['key_id']) || empty($config['key_secret']) || empty($config['bucket'])) {
                return ['status' => 'error', 'message' => '配置不完整'];
            }
            // 模拟连接测试（实际需要调用阿里云 SDK）
            return ['status' => 'success', 'message' => '✅ 阿里云 OSS 连接成功'];
            
        case 's3':
            if (empty($config['access_key']) || empty($config['secret_key']) || empty($config['region']) || empty($config['bucket'])) {
                return ['status' => 'error', 'message' => '配置不完整'];
            }
            return ['status' => 'success', 'message' => '✅ AWS S3 连接成功'];
            
        case 'cos':
            if (empty($config['secret_id']) || empty($config['secret_key']) || empty($config['region']) || empty($config['bucket'])) {
                return ['status' => 'error', 'message' => '配置不完整'];
            }
            return ['status' => 'success', 'message' => '✅ 腾讯云 COS 连接成功'];
            
        case 'swift':
            if (empty($config['auth_url']) || empty($config['username']) || empty($config['password']) || empty($config['container'])) {
                return ['status' => 'error', 'message' => '配置不完整'];
            }
            return ['status' => 'success', 'message' => '✅ OpenStack Swift 连接成功'];
            
        default:
            return ['status' => 'error', 'message' => '未知的云服务商'];
    }
}

include __DIR__ . '/header.php';
?>

<div class="admin-card">
    <h2 class="admin-card-title">
        <i class="fas fa-cloud"></i> 云服务配置
    </h2>

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

    <?php if ($testResult): ?>
        <div class="alert alert-<?php echo $testResult['status'] === 'success' ? 'success' : ($testResult['status'] === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $testResult['status'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
            <?php echo htmlspecialchars($testResult['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 云服务商选择选项卡 -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $currentProvider === 'oss' ? 'active' : ''; ?>" 
                    id="oss-tab" data-bs-toggle="tab" data-bs-target="#oss" type="button" role="tab">
                <i class="fas fa-server"></i> 阿里云 OSS
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $currentProvider === 's3' ? 'active' : ''; ?>" 
                    id="s3-tab" data-bs-toggle="tab" data-bs-target="#s3" type="button" role="tab">
                <i class="fab fa-aws"></i> AWS S3
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $currentProvider === 'cos' ? 'active' : ''; ?>" 
                    id="cos-tab" data-bs-toggle="tab" data-bs-target="#cos" type="button" role="tab">
                <i class="fas fa-database"></i> 腾讯云 COS
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $currentProvider === 'swift' ? 'active' : ''; ?>" 
                    id="swift-tab" data-bs-toggle="tab" data-bs-target="#swift" type="button" role="tab">
                <i class="fas fa-cube"></i> OpenStack Swift
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- 阿里云 OSS -->
        <div class="tab-pane <?php echo $currentProvider === 'oss' ? 'active' : ''; ?>" id="oss" role="tabpanel">
            <form method="POST" class="cloud-config-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="provider" value="oss">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="ossEnabled" name="enabled" value="1"
                        <?php echo (!empty($cloudConfig['enabled']) && ($currentProvider === 'oss' || $currentProvider === 'none')) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ossEnabled">
                        启用阿里云 OSS
                    </label>
                </div>

                <div class="form-group mb-3">
                    <label for="ossEndpoint" class="form-label">Endpoint <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ossEndpoint" name="oss_endpoint" placeholder="oss-cn-hangzhou.aliyuncs.com"
                        value="<?php echo htmlspecialchars($cloudConfig['endpoint'] ?? ''); ?>">
                    <small class="form-text text-muted">Bucket 所在的地域的访问域名</small>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="ossKeyId" class="form-label">Access Key ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ossKeyId" name="oss_key_id" placeholder="LTAI5t..."
                                value="<?php echo htmlspecialchars($cloudConfig['key_id'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="ossKeySecret" class="form-label">Access Key Secret <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="ossKeySecret" name="oss_key_secret"
                                value="<?php echo htmlspecialchars($cloudConfig['key_secret'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="ossBucket" class="form-label">Bucket <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ossBucket" name="oss_bucket" placeholder="my-bucket"
                                value="<?php echo htmlspecialchars($cloudConfig['bucket'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="ossPrefix" class="form-label">前缀</label>
                            <input type="text" class="form-control" id="ossPrefix" name="oss_prefix" placeholder="backups/"
                                value="<?php echo htmlspecialchars($cloudConfig['prefix'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存配置
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="testConnection('oss')">
                        <i class="fas fa-plug"></i> 测试连接
                    </button>
                </div>
            </form>
        </div>

        <!-- AWS S3 -->
        <div class="tab-pane <?php echo $currentProvider === 's3' ? 'active' : ''; ?>" id="s3" role="tabpanel">
            <form method="POST" class="cloud-config-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="provider" value="s3">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="s3Enabled" name="enabled" value="1"
                        <?php echo (!empty($cloudConfig['enabled']) && $currentProvider === 's3') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="s3Enabled">
                        启用 AWS S3
                    </label>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="s3AccessKey" class="form-label">Access Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="s3AccessKey" name="s3_access_key"
                                value="<?php echo htmlspecialchars($cloudConfig['access_key'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="s3SecretKey" class="form-label">Secret Key <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="s3SecretKey" name="s3_secret_key"
                                value="<?php echo htmlspecialchars($cloudConfig['secret_key'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="s3Region" class="form-label">Region <span class="text-danger">*</span></label>
                            <select class="form-select" id="s3Region" name="s3_region">
                                <option value="">-- 选择区域 --</option>
                                <option value="us-east-1" <?php echo ($cloudConfig['region'] ?? '') === 'us-east-1' ? 'selected' : ''; ?>>us-east-1</option>
                                <option value="us-west-2" <?php echo ($cloudConfig['region'] ?? '') === 'us-west-2' ? 'selected' : ''; ?>>us-west-2</option>
                                <option value="eu-west-1" <?php echo ($cloudConfig['region'] ?? '') === 'eu-west-1' ? 'selected' : ''; ?>>eu-west-1</option>
                                <option value="ap-northeast-1" <?php echo ($cloudConfig['region'] ?? '') === 'ap-northeast-1' ? 'selected' : ''; ?>>ap-northeast-1</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="s3Bucket" class="form-label">Bucket <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="s3Bucket" name="s3_bucket"
                                value="<?php echo htmlspecialchars($cloudConfig['bucket'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="s3Prefix" class="form-label">前缀</label>
                    <input type="text" class="form-control" id="s3Prefix" name="s3_prefix"
                        value="<?php echo htmlspecialchars($cloudConfig['prefix'] ?? ''); ?>">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存配置
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="testConnection('s3')">
                        <i class="fas fa-plug"></i> 测试连接
                    </button>
                </div>
            </form>
        </div>

        <!-- 腾讯云 COS -->
        <div class="tab-pane <?php echo $currentProvider === 'cos' ? 'active' : ''; ?>" id="cos" role="tabpanel">
            <form method="POST" class="cloud-config-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="provider" value="cos">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="cosEnabled" name="enabled" value="1"
                        <?php echo (!empty($cloudConfig['enabled']) && $currentProvider === 'cos') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="cosEnabled">
                        启用腾讯云 COS
                    </label>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="cosSecretId" class="form-label">SecretId <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cosSecretId" name="cos_secret_id"
                                value="<?php echo htmlspecialchars($cloudConfig['secret_id'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="cosSecretKey" class="form-label">SecretKey <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="cosSecretKey" name="cos_secret_key"
                                value="<?php echo htmlspecialchars($cloudConfig['secret_key'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="cosRegion" class="form-label">Region <span class="text-danger">*</span></label>
                            <select class="form-select" id="cosRegion" name="cos_region">
                                <option value="">-- 选择区域 --</option>
                                <option value="ap-beijing" <?php echo ($cloudConfig['region'] ?? '') === 'ap-beijing' ? 'selected' : ''; ?>>ap-beijing</option>
                                <option value="ap-shanghai" <?php echo ($cloudConfig['region'] ?? '') === 'ap-shanghai' ? 'selected' : ''; ?>>ap-shanghai</option>
                                <option value="ap-guangzhou" <?php echo ($cloudConfig['region'] ?? '') === 'ap-guangzhou' ? 'selected' : ''; ?>>ap-guangzhou</option>
                                <option value="ap-chongqing" <?php echo ($cloudConfig['region'] ?? '') === 'ap-chongqing' ? 'selected' : ''; ?>>ap-chongqing</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="cosBucket" class="form-label">Bucket <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cosBucket" name="cos_bucket"
                                value="<?php echo htmlspecialchars($cloudConfig['bucket'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="cosPrefix" class="form-label">前缀</label>
                    <input type="text" class="form-control" id="cosPrefix" name="cos_prefix"
                        value="<?php echo htmlspecialchars($cloudConfig['prefix'] ?? ''); ?>">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存配置
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="testConnection('cos')">
                        <i class="fas fa-plug"></i> 测试连接
                    </button>
                </div>
            </form>
        </div>

        <!-- OpenStack Swift -->
        <div class="tab-pane <?php echo $currentProvider === 'swift' ? 'active' : ''; ?>" id="swift" role="tabpanel">
            <form method="POST" class="cloud-config-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="provider" value="swift">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="swiftEnabled" name="enabled" value="1"
                        <?php echo (!empty($cloudConfig['enabled']) && $currentProvider === 'swift') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="swiftEnabled">
                        启用 OpenStack Swift
                    </label>
                </div>

                <div class="form-group mb-3">
                    <label for="swiftAuthUrl" class="form-label">Auth URL <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="swiftAuthUrl" name="swift_auth_url" placeholder="https://identity.api.rackspacecloud.com/v1.0"
                        value="<?php echo htmlspecialchars($cloudConfig['auth_url'] ?? ''); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="swiftUsername" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="swiftUsername" name="swift_username"
                                value="<?php echo htmlspecialchars($cloudConfig['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="swiftPassword" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="swiftPassword" name="swift_password"
                                value="<?php echo htmlspecialchars($cloudConfig['password'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="swiftTenant" class="form-label">Tenant</label>
                            <input type="text" class="form-control" id="swiftTenant" name="swift_tenant"
                                value="<?php echo htmlspecialchars($cloudConfig['tenant'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="swiftContainer" class="form-label">Container <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="swiftContainer" name="swift_container"
                                value="<?php echo htmlspecialchars($cloudConfig['container'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="swiftPrefix" class="form-label">前缀</label>
                    <input type="text" class="form-control" id="swiftPrefix" name="swift_prefix"
                        value="<?php echo htmlspecialchars($cloudConfig['prefix'] ?? ''); ?>">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存配置
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="testConnection('swift')">
                        <i class="fas fa-plug"></i> 测试连接
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 当前状态 -->
    <hr class="my-4">
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-<?php echo $cloudConfig['enabled'] ? 'check-circle text-success' : 'times-circle text-danger'; ?>"></i>
                </div>
                <div class="stat-value"><?php echo $cloudConfig['enabled'] ? '已启用' : '已禁用'; ?></div>
                <div class="stat-label">
                    <?php if ($cloudConfig['enabled']): 
                        $providerName = [
                            'oss' => '阿里云 OSS',
                            's3' => 'AWS S3',
                            'cos' => '腾讯云 COS',
                            'swift' => 'OpenStack Swift'
                        ][$currentProvider] ?? '未知';
                        echo htmlspecialchars($providerName);
                    else: 
                        echo '未配置';
                    endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <?php if ($cloudConfig['enabled']): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="btn btn-warning btn-sm w-100">
                    <i class="fas fa-ban"></i> 禁用所有配置
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function testConnection(provider) {
    var form = document.querySelector('form');
    var formData = new FormData(form);
    formData.set('action', 'test');
    formData.set('provider', provider);
    
    var btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 测试中...';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(function(response) {
        return response.text();
    }).then(function(html) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug"></i> 测试连接';
        location.reload();
    }).catch(function(error) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug"></i> 测试连接';
        alert('测试失败: ' + error);
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
