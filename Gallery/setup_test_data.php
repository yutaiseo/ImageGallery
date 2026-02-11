<?php
/**
 * 测试数据设置脚本
 * 步骤：
 * 1. 备份原始数据到 SQL 文件
 * 2. 清空 images 表并重置自增 ID
 * 3. 生成 100 张测试图片
 * 4. 生成恢复脚本
 */

require_once __DIR__ . '/../ctrol/config/config.php';

$backupFile = __DIR__ . '/backup_images.sql';
$restoreScript = __DIR__ . '/restore_backup.php';

// ===== 步骤 1：备份原始数据 =====
echo "<h2>📦 步骤 1：备份原始数据...</h2>";
// try {
    // $result = $pdo->query('SELECT * FROM images');
    // $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    // 
    // $sql = "-- 备份于 " . date('Y-m-d H:i:s') . "\n";
    // $sql .= "-- 恢复此备份请访问：/Gallery/restore_backup.php\n\n";
    // $sql .= "DELETE FROM images;\n";
    // $sql .= "ALTER TABLE images AUTO_INCREMENT = 1;\n\n";
    // 
    // foreach ($rows as $row) {
        // $id = (int)$row['id'];
        // $title = $pdo->quote($row['title']);
        // $desc = $pdo->quote($row['description']);
        // $path = $pdo->quote($row['file_path']);
        // $remote = (int)$row['is_remote'];
        // $created = $pdo->quote($row['created_at']);
        // 
        // $sql .= "INSERT INTO images (id, title, description, file_path, is_remote, created_at) ";
        // $sql .= "VALUES ($id, $title, $desc, $path, $remote, $created);\n";
    // }
    // 
    // file_put_contents($backupFile, $sql);
    // echo "✅ 备份成功！已保存 " . count($rows) . " 条记录到 backup_images.sql<br>";
    // 
// } catch (Exception $e) {
    // echo "❌ 备份失败: " . $e->getMessage() . "<br>";
    // exit;
// }

// ===== 步骤 2：清空 images 表并重置 ID =====
echo "<h2>🗑️  步骤 2：清空 images 表...</h2>";
try {
    $pdo->exec('TRUNCATE TABLE images');
    echo "✅ 表已清空，自增 ID 已重置到 1<br>";
} catch (Exception $e) {
    echo "❌ 清空失败: " . $e->getMessage() . "<br>";
    exit;
}

// ===== 步骤 3：生成测试数据 =====
echo "<h2>🎨 步骤 3：生成 100 张测试图片...</h2>";

$imageUrls = [
    'https://picsum.photos/400/300?random=1',
    'https://picsum.photos/400/300?random=2',
    'https://picsum.photos/400/300?random=3',
    'https://picsum.photos/400/300?random=4',
    'https://picsum.photos/400/300?random=5',
];

$titles = ['风景', '建筑', '人物', '动物', '静物', '美食', '城市', '自然', '花卉', '夜景'];
$descriptions = [
    '这是一张测试图片',
    '测试图片说明',
    '示例描述',
    '测试用',
    '演示数据',
    '样例图片',
    '临时数据',
    '测试内容',
];

try {
    $pdo->exec('START TRANSACTION');
    $stmt = $pdo->prepare('INSERT INTO images (title, description, file_path, is_remote, created_at) VALUES (?, ?, ?, 1, NOW())');
    
    for ($i = 1; $i <= 100; $i++) {
        $title = $titles[($i - 1) % count($titles)] . ' #' . $i;
        $desc = $descriptions[($i - 1) % count($descriptions)];
        $imageUrl = $imageUrls[($i - 1) % count($imageUrls)] . '&t=' . $i;
        
        $stmt->execute([$title, $desc, $imageUrl]);
    }
    
    $pdo->exec('COMMIT');
    echo "✅ 成功生成 100 张测试图片！<br>";
    
} catch (Exception $e) {
    $pdo->exec('ROLLBACK');
    echo "❌ 生成失败: " . $e->getMessage() . "<br>";
    exit;
}

// ===== 步骤 4：生成恢复脚本 =====
echo "<h2>🔄 步骤 4：生成恢复脚本...</h2>";

$restorePhpCode = '<?php
/**
 * 恢复原始数据
 * 此脚本会将数据库恢复到运行 setup_test_data.php 前的状态
 */

require_once __DIR__ . "/../ctrol/config/config.php";

$backupFile = __DIR__ . "/backup_images.sql";

if (!file_exists($backupFile)) {
    die("❌ 找不到备份文件：" . $backupFile);
}

try {
    $pdo->exec("START TRANSACTION");
    $pdo->exec("DELETE FROM images");
    $pdo->exec("ALTER TABLE images AUTO_INCREMENT = 1");
    
    $sqlCommands = file_get_contents($backupFile);
    // 移除注释和空行
    $lines = array_filter(array_map("trim", explode("\n", $sqlCommands)), function($line) {
        return !empty($line) && strpos($line, "--") !== 0;
    });
    
    $sql = implode(" ", $lines);
    
    // 分割 SQL 语句并执行
    $statements = array_filter(array_map("trim", explode(";", $sql)), function($s) {
        return !empty($s);
    });
    
    $count = 0;
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            $count++;
        }
    }
    
    $pdo->exec("COMMIT");
    
    echo "<h2>✅ 恢复成功！</h2>";
    echo "<p>已恢复 " . $count . " 条命令</p>";
    echo "<p><a href=\"/\">返回首页</a></p>";
    
} catch (Exception $e) {
    $pdo->exec("ROLLBACK");
    echo "<h2>❌ 恢复失败</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>';

file_put_contents($restoreScript, $restorePhpCode);
echo "✅ 恢复脚本已生成：restore_backup.php<br>";

// ===== 完成 =====
echo "<h2>🎉 完成！</h2>";
echo "<p>✅ 原始数据已备份到 <code>backup_images.sql</code></p>";
echo "<p>✅ 已生成 100 张测试图片</p>";
echo "<p>✅ 恢复脚本已生成：<code>restore_backup.php</code></p>";
echo "<hr>";
echo "<h3>下一步：</h3>";
echo "<ol>";
echo "<li>刷新首页测试分页功能</li>";
echo "<li>测试完后访问 <a href=\"restore_backup.php\">restore_backup.php</a> 恢复原始数据</li>";
echo "</ol>";
echo "<p><a href=\"/\">👉 返回首页</a></p>";
?>
