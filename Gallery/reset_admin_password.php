<?php
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo "CLI only\n";
    exit;
}

$configPath = __DIR__ . '/../ctrol/config/config.php';
if (!file_exists($configPath)) {
    echo "config.php not found.\n";
    exit(1);
}

require $configPath;

function random_password($len = 12) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*?';
    $pw = '';
    for ($i = 0; $i < $len; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pw;
}

$username = $argv[1] ?? '';
if ($username === '') {
    $stmt = $pdo->query("SELECT username FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $username = $stmt->fetchColumn();
    if (!$username) {
        $stmt = $pdo->query("SELECT username FROM users ORDER BY id ASC LIMIT 1");
        $username = $stmt->fetchColumn();
    }
}

if (!$username) {
    echo "No user found.\n";
    exit(1);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
$userId = $stmt->fetchColumn();
if (!$userId) {
    echo "User not found: {$username}\n";
    exit(1);
}

$newPassword = random_password(12);
$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);

$output = "username={$username}\npassword={$newPassword}\n";
$baseDir = rtrim(sys_get_temp_dir(), '/\\');
$dirName = 'img_admin_reset_' . bin2hex(random_bytes(12));
$dirPath = $baseDir . DIRECTORY_SEPARATOR . $dirName;
if (!mkdir($dirPath, 0700, true) && !is_dir($dirPath)) {
    echo "Failed to create secure directory.\n";
    exit(1);
}
$fileName = 'cred_' . bin2hex(random_bytes(12)) . '.txt';
$savePath = $dirPath . DIRECTORY_SEPARATOR . $fileName;
file_put_contents($savePath, $output);
@chmod($savePath, 0600);
@chmod($dirPath, 0700);

echo "Username: {$username}\n";
echo "Password: {$newPassword}\n";
echo "Saved to: {$savePath}\n";
echo "Delete the file after you store the password securely.\n";
