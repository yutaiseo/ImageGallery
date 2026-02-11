<?php
/**
 * 生成测试图片数据到数据库
 * 访问 /Gallery/generate_test_images.php?count=100 来生成 100 张测试图片
 */

require_once __DIR__ . '/../ctrol/config/config.php';

$count = max(1, min(500, (int)($_GET['count'] ?? 50)));

// 图片列表（使用免费的图片 URLs）
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

$inserted = 0;

// 使用事务加快插入速度
$pdo->exec('START TRANSACTION');

try {
    $stmt = $pdo->prepare('
        INSERT INTO images (title, description, file_path, is_remote, created_at) 
        VALUES (?, ?, ?, 1, NOW())
    ');
    
    for ($i = 1; $i <= $count; $i++) {
        $title = $titles[($i - 1) % count($titles)] . ' #' . $i;
        $desc = $descriptions[($i - 1) % count($descriptions)];
        $imageUrl = $imageUrls[($i - 1) % count($imageUrls)] . '&t=' . $i;
        
        if ($stmt->execute([$title, $desc, $imageUrl])) {
            $inserted++;
        }
    }
    
    $pdo->exec('COMMIT');
    
    echo "✅ 成功生成 $inserted 张测试图片！\n";
    echo "现在有足够的数据来测试分页功能。\n";
    echo "刷新首页：<a href='/'>返回首页</a>";
    
} catch (Exception $e) {
    $pdo->exec('ROLLBACK');
    echo "❌ 错误: " . $e->getMessage();
}
?>
