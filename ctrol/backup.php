<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/logger.php';

function setting_value($key, $default = '')
{
    global $defaultSettings;
    if (isset($defaultSettings[$key])) {
        return $defaultSettings[$key];
    }
    return $default;
}

function upsert_setting($pdo, $key, $value)
{
    $stmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function resolve_backup_dir($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        $path = '../backup';
    }

    if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
        $dir = $path;
    } else {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . $path;
    }

    return rtrim($dir, "/\\");
}

function get_backup_tables()
{
    return [
        'users',
        'images',
        'site_settings',
        'access_logs',
        'user_action_logs',
        'source_logs',
        'client_logs'
    ];
}

function build_backup_sql($pdo, $tables)
{
    $sql = "SET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!isset($row['Create Table'])) {
            continue;
        }
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $row['Create Table'] . ";\n\n";

        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(function ($col) {
                return '`' . str_replace('`', '``', $col) . '`';
            }, array_keys($record));
            $vals = array_map(function ($val) use ($pdo) {
                if ($val === null) {
                    return 'NULL';
                }
                return $pdo->quote((string)$val);
            }, array_values($record));
            $sql .= "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return $sql;
}

function prune_backups($dir, $keepDays)
{
    $keepDays = (int)$keepDays;
    if ($keepDays <= 0 || !is_dir($dir)) {
        return;
    }
    $threshold = time() - ($keepDays * 86400);
    $items = glob($dir . DIRECTORY_SEPARATOR . 'backup_*.zip');
    if (!$items) {
        return;
    }
    foreach ($items as $item) {
        if (is_file($item) && filemtime($item) < $threshold) {
            @unlink($item);
        }
    }
}

function add_dir_to_zip($zip, $dir, $baseDir, $excludeDirs = [])
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $pathName = $file->getRealPath();
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($baseDir, '', $pathName));
        $relative = ltrim($relative, '/');

        $skip = false;
        foreach ($excludeDirs as $exclude) {
            if (strpos($relative, $exclude . '/') === 0) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $zip->addFile($pathName, $relative);
        }
    }
}

function upload_to_cloud($filePath, $provider, $config)
{
    if ($provider === 'oss' || $provider === 'aliyun') {
        return upload_to_oss($filePath, $config);
    } elseif ($provider === 's3' || $provider === 'aws') {
        return upload_to_s3($filePath, $config);
    } elseif ($provider === 'cos' || $provider === 'tencent') {
        return upload_to_cos($filePath, $config);
    } elseif ($provider === 'swift' || $provider === 'openstack') {
        return upload_to_swift($filePath, $config);
    }
    return [false, '不支持的云服务商：' . $provider];
}

function upload_to_oss($filePath, $ossConfig)
{
    $autoload = __DIR__ . '/../Gallery/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('OSS\\OssClient')) {
        return [false, 'OSS SDK 未安装'];
    }

    try {
        $client = new OSS\OssClient(
            $ossConfig['key_id'],
            $ossConfig['key_secret'],
            $ossConfig['endpoint']
        );
        $prefix = trim((string)($ossConfig['prefix'] ?? ''));
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }
        $object = $prefix . 'backup/' . basename($filePath);
        $client->uploadFile($ossConfig['bucket'], $object, $filePath);
        return [true, $object];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }
}

function upload_to_s3($filePath, $s3Config)
{
    $autoload = __DIR__ . '/../Gallery/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('Aws\\S3\\S3Client')) {
        return [false, 'AWS SDK 未安装'];
    }

    try {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $s3Config['region'] ?? 'us-east-1',
            'credentials' => [
                'key'    => $s3Config['access_key'] ?? '',
                'secret' => $s3Config['secret_key'] ?? ''
            ]
        ]);

        $prefix = trim((string)($s3Config['prefix'] ?? ''));
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }
        $key = $prefix . 'backup/' . basename($filePath);

        $client->putObject([
            'Bucket' => $s3Config['bucket'] ?? '',
            'Key'    => $key,
            'Body'   => fopen($filePath, 'r')
        ]);
        return [true, $key];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }
}

function upload_to_cos($filePath, $cosConfig)
{
    $autoload = __DIR__ . '/../Gallery/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('Cos\\Auth')) {
        return [false, '腾讯云 COS SDK 未安装'];
    }

    try {
        $config = [
            'region' => $cosConfig['region'] ?? '',
            'credentials' => [
                'secretId'  => $cosConfig['secret_id'] ?? '',
                'secretKey' => $cosConfig['secret_key'] ?? ''
            ]
        ];

        $cosClient = new Cos\Client($config);
        $prefix = trim((string)($cosConfig['prefix'] ?? ''));
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }
        $key = $prefix . 'backup/' . basename($filePath);

        $cosClient->putObject([
            'Bucket' => $cosConfig['bucket'] ?? '',
            'Key'    => $key,
            'Body'   => fopen($filePath, 'r')
        ]);
        return [true, $key];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }
}

function upload_to_swift($filePath, $swiftConfig)
{
    $autoload = __DIR__ . '/../Gallery/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!function_exists('openstack')) {
        return [false, 'OpenStack SDK 未安装'];
    }

    try {
        $openstack = openstack([
            'authUrl'      => $swiftConfig['auth_url'] ?? '',
            'user'         => $swiftConfig['username'] ?? '',
            'secret'       => $swiftConfig['password'] ?? '',
            'tenantName'   => $swiftConfig['tenant'] ?? ''
        ]);

        $objectStore = $openstack->objectStoreV1();
        $container = $objectStore->getContainer($swiftConfig['container'] ?? '');
        $prefix = trim((string)($swiftConfig['prefix'] ?? ''));
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '/') . '/';
        }
        $object = $prefix . 'backup/' . basename($filePath);

        $container->uploadObject([
            'name' => $object,
            'stream' => fopen($filePath, 'r')
        ]);
        return [true, $object];
    } catch (Exception $e) {
        return [false, $e->getMessage()];
    }
}

function create_backup($pdo, $backupDir, $options, &$message)
{
    $timestamp = date('Ymd_His');
    $fileName = 'backup_' . $timestamp . '.zip';
    $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE) !== true) {
        $message = '无法创建备份文件';
        return [false, ''];
    }

    $manifest = [
        'created_at' => date('c'),
        'include_db' => !empty($options['include_db']),
        'include_program' => !empty($options['include_program']),
        'include_uploads' => !empty($options['include_uploads'])
    ];

    if (!empty($options['include_db'])) {
        $tables = get_backup_tables();
        $sql = build_backup_sql($pdo, $tables);
        $zip->addFromString('db.sql', $sql);
    }

    if (!empty($options['include_program'])) {
        $baseDir = realpath(__DIR__ . '/..');
        $excludeDirs = ['docs', 'run', 'backup'];
        add_dir_to_zip($zip, $baseDir, $baseDir, $excludeDirs);
    }

    if (!empty($options['include_uploads'])) {
        $uploadsDir = realpath(__DIR__ . '/../Gallery/uploads');
        if ($uploadsDir && is_dir($uploadsDir)) {
            add_dir_to_zip($zip, $uploadsDir, dirname($uploadsDir));
        }
    }

    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->close();

    return [true, $filePath];
}

function list_backups($backupDir)
{
    $backups = [];
    if (!is_dir($backupDir)) {
        return $backups;
    }
    
    $files = glob($backupDir . DIRECTORY_SEPARATOR . 'backup_*.zip');
    if (!$files) {
        return $backups;
    }
    
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        
        $backup = [
            'filename' => basename($file),
            'filepath' => $file,
            'size' => filesize($file),
            'size_formatted' => format_file_size(filesize($file)),
            'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            'timestamp' => filemtime($file),
            'manifest' => null
        ];
        
        // 尝试读取 manifest.json
        $zip = new ZipArchive();
        if ($zip->open($file) === true) {
            $manifestContent = $zip->getFromName('manifest.json');
            if ($manifestContent) {
                $backup['manifest'] = json_decode($manifestContent, true);
            }
            $zip->close();
        }
        
        $backups[] = $backup;
    }
    
    // 按时间倒序排列
    usort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $backups;
}

function format_file_size($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function restore_backup($pdo, $backupFile, $options, &$message)
{
    if (!file_exists($backupFile)) {
        $message = '备份文件不存在';
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($backupFile) !== true) {
        $message = '无法打开备份文件';
        return false;
    }
    
    // 创建临时目录
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_' . time();
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    
    // 解压到临时目录
    if (!$zip->extractTo($tempDir)) {
        $zip->close();
        $message = '解压备份文件失败';
        return false;
    }
    $zip->close();
    
    try {
        // 恢复数据库
        if (!empty($options['restore_db'])) {
            $sqlFile = $tempDir . DIRECTORY_SEPARATOR . 'db.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                if ($sql) {
                    $pdo->exec($sql);
                }
            }
        }
        
        // 恢复上传文件
        if (!empty($options['restore_uploads'])) {
            $uploadsSource = $tempDir . DIRECTORY_SEPARATOR . 'Gallery' . DIRECTORY_SEPARATOR . 'uploads';
            $uploadsTarget = realpath(__DIR__ . '/../Gallery/uploads');
            if (is_dir($uploadsSource) && $uploadsTarget) {
                recursive_copy($uploadsSource, $uploadsTarget);
            }
        }
        
        // 恢复程序文件
        if (!empty($options['restore_program'])) {
            $baseTarget = realpath(__DIR__ . '/..');
            
            // 恢复 ctrol 目录（排除config）
            $ctrolSource = $tempDir . DIRECTORY_SEPARATOR . 'ctrol';
            $ctrolTarget = $baseTarget . DIRECTORY_SEPARATOR . 'ctrol';
            if (is_dir($ctrolSource)) {
                recursive_copy($ctrolSource, $ctrolTarget, ['config']);
            }
            
            // 恢复 Gallery 目录（排除uploads）
            $gallerySource = $tempDir . DIRECTORY_SEPARATOR . 'Gallery';
            $galleryTarget = $baseTarget . DIRECTORY_SEPARATOR . 'Gallery';
            if (is_dir($gallerySource)) {
                recursive_copy($gallerySource, $galleryTarget, ['uploads']);
            }
        }
        
        // 清理临时目录
        recursive_delete($tempDir);
        
        $message = '恢复成功';
        return true;
        
    } catch (Exception $e) {
        recursive_delete($tempDir);
        $message = '恢复失败：' . $e->getMessage();
        return false;
    }
}

function recursive_copy($src, $dst, $exclude = [])
{
    if (!is_dir($src)) {
        return;
    }
    
    if (!is_dir($dst)) {
        @mkdir($dst, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $relativePath = str_replace($src, '', $item->getRealPath());
        $relativePath = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relativePath), '/');
        
        // 检查是否在排除列表中
        $skip = false;
        foreach ($exclude as $excludeDir) {
            if (strpos($relativePath, $excludeDir . '/') === 0 || $relativePath === $excludeDir) {
                $skip = true;
                break;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        $target = $dst . DIRECTORY_SEPARATOR . $relativePath;
        
        if ($item->isDir()) {
            if (!is_dir($target)) {
                @mkdir($target, 0755, true);
            }
        } else {
            @copy($item->getRealPath(), $target);
        }
    }
}

function recursive_delete($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getRealPath());
        } else {
            @unlink($item->getRealPath());
        }
    }
    
    @rmdir($dir);
}

$isCron = isset($_GET['cron']) && $_GET['cron'] === '1';
$cronToken = (string)setting_value('backup_cron_token', '');
if ($isCron) {
    $token = $_GET['token'] ?? '';
    if ($cronToken === '' || !hash_equals($cronToken, $token)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden';
        exit;
    }
}

if (!$isCron) {
    require_admin();
}

$success = '';
$error = '';

if (empty($cronToken)) {
    $cronToken = bin2hex(random_bytes(16));
    upsert_setting($pdo, 'backup_cron_token', $cronToken);
    $defaultSettings['backup_cron_token'] = $cronToken;
}

$backupDirSetting = setting_value('backup_path', '../backup');
$backupDir = resolve_backup_dir($backupDirSetting);
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

if (!is_dir($backupDir) || !is_writable($backupDir)) {
    $error = '备份目录不可写：' . $backupDir;
}

$includeDb = setting_value('backup_include_db', '1') === '1';
$includeProgram = setting_value('backup_include_program', '1') === '1';
$includeUploads = setting_value('backup_include_uploads', '1') === '1';
$cloudEnabled = setting_value('backup_cloud_enabled', '0') === '1';
$cloudProvider = setting_value('backup_cloud_provider', 'oss');
$backupEnabled = setting_value('backup_enabled', '0') === '1';
$backupTime = trim((string)setting_value('backup_time', '02:00'));
$backupKeepDays = (int)setting_value('backup_keep_days', '7');
$lastRun = trim((string)setting_value('backup_last_run', ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isCron) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'CSRF 验证失败';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_settings') {
            $backupEnabled = !empty($_POST['backup_enabled']) ? '1' : '0';
            $backupTime = trim((string)($_POST['backup_time'] ?? ''));
            $backupKeepDays = (int)($_POST['backup_keep_days'] ?? 7);
            $includeDb = !empty($_POST['backup_include_db']) ? '1' : '0';
            $includeProgram = !empty($_POST['backup_include_program']) ? '1' : '0';
            $includeUploads = !empty($_POST['backup_include_uploads']) ? '1' : '0';
            $cloudEnabled = !empty($_POST['backup_cloud_enabled']) ? '1' : '0';
            $cloudProvider = trim((string)($_POST['backup_cloud_provider'] ?? 'oss'));

            upsert_setting($pdo, 'backup_enabled', $backupEnabled);
            upsert_setting($pdo, 'backup_time', $backupTime);
            upsert_setting($pdo, 'backup_keep_days', (string)$backupKeepDays);
            upsert_setting($pdo, 'backup_include_db', $includeDb);
            upsert_setting($pdo, 'backup_include_program', $includeProgram);
            upsert_setting($pdo, 'backup_include_uploads', $includeUploads);
            upsert_setting($pdo, 'backup_cloud_enabled', $cloudEnabled);
            upsert_setting($pdo, 'backup_cloud_provider', $cloudProvider);

            $success = '备份设置已保存';
        } elseif ($action === 'manual_backup' && empty($error)) {
            $options = [
                'include_db' => $includeDb,
                'include_program' => $includeProgram,
                'include_uploads' => $includeUploads
            ];
            $message = '';
            list($ok, $filePath) = create_backup($pdo, $backupDir, $options, $message);
            if ($ok) {
                $uploadMessage = '';
                if ($cloudEnabled) {
                    $configPath = __DIR__ . '/config/cloud_config.json';
                    if (!file_exists($configPath)) {
                        $configPath = __DIR__ . '/config/oss_config.json';
                    }
                    $cloudConfig = json_decode(@file_get_contents($configPath), true);
                    if (!empty($cloudConfig['enabled'])) {
                        list($uploadOk, $cloudInfo) = upload_to_cloud($filePath, $cloudProvider, $cloudConfig);
                        if (!$uploadOk) {
                            $uploadMessage = '（云端上传失败：' . $cloudInfo . '）';
                        } else {
                            $uploadMessage = '（云端已上传：' . $cloudInfo . '）';
                        }
                    } else {
                        $uploadMessage = '（云服务未启用）';
                    }
                }
                $success = '备份完成：' . basename($filePath) . $uploadMessage;
                $lastRun = date('Y-m-d H:i:s');
                upsert_setting($pdo, 'backup_last_run', $lastRun);
                upsert_setting($pdo, 'backup_last_status', 'success');
                upsert_setting($pdo, 'backup_last_message', $success);
                upsert_setting($pdo, 'backup_last_file', basename($filePath));
                log_action($pdo, $_SESSION['username'] ?? 'unknown', 'backup_manual', 'file=' . basename($filePath));
                prune_backups($backupDir, $backupKeepDays);
            } else {
                $error = $message;
                upsert_setting($pdo, 'backup_last_run', date('Y-m-d H:i:s'));
                upsert_setting($pdo, 'backup_last_status', 'failed');
                upsert_setting($pdo, 'backup_last_message', $error);
            }
        } elseif ($action === 'regen_token') {
            $cronToken = bin2hex(random_bytes(16));
            upsert_setting($pdo, 'backup_cron_token', $cronToken);
            $success = '已生成新的计划任务 Token';
        } elseif ($action === 'restore_backup') {
            $backupFilename = $_POST['backup_file'] ?? '';
            $backupFilePath = $backupDir . DIRECTORY_SEPARATOR . $backupFilename;
            
            if (!file_exists($backupFilePath) || strpos($backupFilename, '..') !== false) {
                $error = '备份文件不存在';
            } else {
                $restoreOptions = [
                    'restore_db' => !empty($_POST['restore_db']),
                    'restore_program' => !empty($_POST['restore_program']),
                    'restore_uploads' => !empty($_POST['restore_uploads'])
                ];
                
                $message = '';
                $result = restore_backup($pdo, $backupFilePath, $restoreOptions, $message);
                
                if ($result) {
                    $success = '恢复成功！' . $message;
                    log_action($pdo, $_SESSION['username'] ?? 'unknown', 'backup_restore', 'file=' . $backupFilename);
                } else {
                    $error = '恢复失败：' . $message;
                }
            }
        } elseif ($action === 'delete_backup') {
            $backupFilename = $_POST['backup_file'] ?? '';
            $backupFilePath = $backupDir . DIRECTORY_SEPARATOR . $backupFilename;
            
            if (!file_exists($backupFilePath) || strpos($backupFilename, '..') !== false) {
                $error = '备份文件不存在';
            } else {
                if (@unlink($backupFilePath)) {
                    $success = '备份文件已删除';
                    log_action($pdo, $_SESSION['username'] ?? 'unknown', 'backup_delete', 'file=' . $backupFilename);
                } else {
                    $error = '删除失败';
                }
            }
        }
    }
}

// 处理下载请求
if (isset($_GET['download']) && !$isCron) {
    $backupFilename = $_GET['download'];
    $backupFilePath = $backupDir . DIRECTORY_SEPARATOR . $backupFilename;
    
    if (file_exists($backupFilePath) && strpos($backupFilename, '..') === false && preg_match('/^backup_\d{8}_\d{6}\.zip$/', $backupFilename)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $backupFilename . '"');
        header('Content-Length: ' . filesize($backupFilePath));
        readfile($backupFilePath);
        exit;
    }
}

if ($isCron) {
    if ($backupEnabled && $backupTime !== '') {
        $now = new DateTime();
        $timeParts = explode(':', $backupTime);
        $hour = isset($timeParts[0]) ? (int)$timeParts[0] : 0;
        $minute = isset($timeParts[1]) ? (int)$timeParts[1] : 0;
        $scheduled = (new DateTime('today'))->setTime($hour, $minute, 0);
        $lastRunAt = $lastRun ? new DateTime($lastRun) : null;

        if ($now >= $scheduled && (!$lastRunAt || $lastRunAt < $scheduled)) {
            $options = [
                'include_db' => $includeDb,
                'include_program' => $includeProgram,
                'include_uploads' => $includeUploads
            ];
            $message = '';
            list($ok, $filePath) = create_backup($pdo, $backupDir, $options, $message);
            if ($ok) {
                if ($cloudEnabled) {
                    $configPath = __DIR__ . '/config/cloud_config.json';
                    if (!file_exists($configPath)) {
                        $configPath = __DIR__ . '/config/oss_config.json';
                    }
                    $cloudConfig = json_decode(@file_get_contents($configPath), true);
                    if (!empty($cloudConfig['enabled'])) {
                        upload_to_cloud($filePath, $cloudProvider, $cloudConfig);
                    }
                }
                $lastRun = date('Y-m-d H:i:s');
                upsert_setting($pdo, 'backup_last_run', $lastRun);
                upsert_setting($pdo, 'backup_last_status', 'success');
                upsert_setting($pdo, 'backup_last_message', 'cron');
                upsert_setting($pdo, 'backup_last_file', basename($filePath));
                prune_backups($backupDir, $backupKeepDays);
                echo 'ok';
                exit;
            }
        }
    }
    echo 'skip';
    exit;
}

$page_title = '备份';

// 获取备份列表
$backupList = list_backups($backupDir);

include __DIR__ . '/header.php';
?>
<div class="mt-3">
    <h3>备份管理</h3>

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

    <div class="card p-4 shadow-sm mb-4">
        <h5>手动备份</h5>
        <div class="mb-2">备份目录：<?php echo htmlspecialchars($backupDirSetting); ?></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="manual_backup">
            <button type="submit" class="btn btn-primary">立即备份</button>
        </form>
        <div class="form-text mt-2">备份内容按当前设置执行（数据库/上传文件/云端）。</div>
    </div>

    <div class="card p-4 shadow-sm mb-4">
        <h5>定期备份设置</h5>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="save_settings">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="backup_enabled" name="backup_enabled" <?php echo $backupEnabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="backup_enabled">启用定期备份</label>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="backup_time">备份时间 (HH:MM)</label>
                    <input class="form-control" type="text" id="backup_time" name="backup_time" value="<?php echo htmlspecialchars($backupTime); ?>" placeholder="02:00">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="backup_keep_days">保留天数</label>
                    <input class="form-control" type="number" id="backup_keep_days" name="backup_keep_days" value="<?php echo (int)$backupKeepDays; ?>" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">最近一次</label>
                    <input class="form-control" type="text" value="<?php echo htmlspecialchars($lastRun ?: '暂无'); ?>" readonly>
                </div>
            </div>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="backup_include_db" name="backup_include_db" <?php echo $includeDb ? 'checked' : ''; ?>>
                <label class="form-check-label" for="backup_include_db">备份数据库</label>
            </div>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="backup_include_uploads" name="backup_include_uploads" <?php echo $includeUploads ? 'checked' : ''; ?>>
                <label class="form-check-label" for="backup_include_uploads">备份上传文件</label>
            </div>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="backup_include_program" name="backup_include_program" <?php echo $includeProgram ? 'checked' : ''; ?>>
                <label class="form-check-label" for="backup_include_program">备份程序结构（不含 docs/run/backup）</label>
            </div>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="backup_cloud_enabled" name="backup_cloud_enabled" <?php echo $cloudEnabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="backup_cloud_enabled">同步到云服务</label>
            </div>
            <div class="mt-2">
                <label class="form-label" for="backup_cloud_provider">云服务商</label>
                <select class="form-select" id="backup_cloud_provider" name="backup_cloud_provider">
                    <option value="oss" <?php echo $cloudProvider === 'oss' ? 'selected' : ''; ?>>阿里云 OSS</option>
                    <option value="s3" <?php echo $cloudProvider === 's3' ? 'selected' : ''; ?>>AWS S3</option>
                    <option value="cos" <?php echo $cloudProvider === 'cos' ? 'selected' : ''; ?>>腾讯云 COS</option>
                    <option value="swift" <?php echo $cloudProvider === 'swift' ? 'selected' : ''; ?>>OpenStack Swift</option>
                </select>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </div>

    <div class="card p-4 shadow-sm">
        <h5>服务器定期备份（Cron）</h5>
        <div class="mb-2">调用地址（示例）：</div>
        <div class="bg-light p-2 rounded">/admin/backup.php?cron=1&amp;token=<?php echo htmlspecialchars($cronToken); ?></div>
        <form method="post" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="regen_token">
            <button type="submit" class="btn btn-outline-secondary btn-sm">重新生成 Token</button>
        </form>
        <div class="form-text mt-2">建议通过系统计划任务定时访问该地址。</div>
    </div>

    <div class="card p-4 shadow-sm mt-4">
        <h5><i class="fas fa-archive"></i> 备份文件列表</h5>
        <?php if (empty($backupList)): ?>
            <div class="alert alert-info">暂无备份文件</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>文件名</th>
                            <th>创建时间</th>
                            <th>大小</th>
                            <th>包含内容</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backupList as $backup): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($backup['filename']); ?></code>
                                </td>
                                <td><?php echo htmlspecialchars($backup['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($backup['size_formatted']); ?></td>
                                <td>
                                    <?php if ($backup['manifest']): ?>
                                        <?php if (!empty($backup['manifest']['include_db'])): ?>
                                            <span class="badge bg-primary">数据库</span>
                                        <?php endif; ?>
                                        <?php if (!empty($backup['manifest']['include_program'])): ?>
                                            <span class="badge bg-success">程序</span>
                                        <?php endif; ?>
                                        <?php if (!empty($backup['manifest']['include_uploads'])): ?>
                                            <span class="badge bg-warning">上传文件</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">未知</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="?download=<?php echo urlencode($backup['filename']); ?>" 
                                           class="btn btn-outline-primary" title="下载">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-success" 
                                                onclick="showRestoreModal('<?php echo htmlspecialchars($backup['filename']); ?>', <?php echo htmlspecialchars(json_encode($backup['manifest'])); ?>)" 
                                                title="恢复">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" 
                                                title="删除">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 恢复确认模态框 -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> 恢复备份</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="restoreForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="backup_file" id="restoreBackupFile">
                    
                    <div class="alert alert-warning">
                        <strong>警告：</strong> 恢复操作将覆盖当前数据！请确保您了解此操作的后果。
                    </div>
                    
                    <p><strong>备份文件：</strong> <code id="restoreFileName"></code></p>
                    <p><strong>创建时间：</strong> <span id="restoreCreatedAt"></span></p>
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>选择要恢复的内容：</strong></label>
                        <div class="form-check" id="restoreDbOption">
                            <input class="form-check-input" type="checkbox" name="restore_db" id="restoreDb">
                            <label class="form-check-label" for="restoreDb">
                                恢复数据库
                            </label>
                        </div>
                        <div class="form-check" id="restoreProgramOption">
                            <input class="form-check-input" type="checkbox" name="restore_program" id="restoreProgram">
                            <label class="form-check-label" for="restoreProgram">
                                恢复程序文件
                            </label>
                        </div>
                        <div class="form-check" id="restoreUploadsOption">
                            <input class="form-check-input" type="checkbox" name="restore_uploads" id="restoreUploads">
                            <label class="form-check-label" for="restoreUploads">
                                恢复上传文件
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>提示：</strong> 建议在恢复前先创建当前状态的备份。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-warning">确认恢复</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showRestoreModal(filename, manifest) {
    document.getElementById('restoreBackupFile').value = filename;
    document.getElementById('restoreFileName').textContent = filename;
    
    if (manifest && manifest.created_at) {
        document.getElementById('restoreCreatedAt').textContent = manifest.created_at;
    } else {
        document.getElementById('restoreCreatedAt').textContent = '未知';
    }
    
    // 根据备份内容显示/隐藏选项
    const dbOption = document.getElementById('restoreDbOption');
    const programOption = document.getElementById('restoreProgramOption');
    const uploadsOption = document.getElementById('restoreUploadsOption');
    
    if (manifest) {
        dbOption.style.display = manifest.include_db ? 'block' : 'none';
        programOption.style.display = manifest.include_program ? 'block' : 'none';
        uploadsOption.style.display = manifest.include_uploads ? 'block' : 'none';
        
        // 默认选中所有可用选项
        document.getElementById('restoreDb').checked = manifest.include_db || false;
        document.getElementById('restoreProgram').checked = manifest.include_program || false;
        document.getElementById('restoreUploads').checked = manifest.include_uploads || false;
    } else {
        // 如果没有manifest，显示所有选项
        dbOption.style.display = 'block';
        programOption.style.display = 'block';
        uploadsOption.style.display = 'block';
    }
    
    var modal = new bootstrap.Modal(document.getElementById('restoreModal'));
    modal.show();
}

function deleteBackup(filename) {
    if (!confirm('确定要删除备份文件 ' + filename + ' 吗？此操作不可恢复！')) {
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">' +
                     '<input type="hidden" name="action" value="delete_backup">' +
                     '<input type="hidden" name="backup_file" value="' + filename + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
