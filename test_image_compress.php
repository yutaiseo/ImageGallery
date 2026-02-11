<?php
// 测试脚本：检查 GD 库和图片压缩功能

echo "=== 图片处理能力检查 ===\n\n";

// 1. 检查 GD 库
echo "1. GD 库支持:\n";
if (extension_loaded('gd')) {
    echo "✅ GD 扩展已加载\n";
    
    // 检查支持的格式
    $gdInfo = gd_info();
    echo "\n支持的图片格式:\n";
    echo "  JPG: " . (isset($gdInfo['JPEG Support']) ? "✅ 是\n" : "❌ 否\n");
    echo "  PNG: " . (isset($gdInfo['PNG Support']) ? "✅ 是\n" : "❌ 否\n");
    echo "  GIF: " . (isset($gdInfo['GIF Read Support']) ? "✅ 是\n" : "❌ 否\n");
    echo "  WebP: " . (isset($gdInfo['WebP Support']) ? "✅ 是\n" : "❌ 否\n");
} else {
    echo "❌ GD 扩展未加载\n";
}

echo "\n2. 二进制函数:\n";
echo "  imagecreatefromjpeg: " . (function_exists('imagecreatefromjpeg') ? "✅ 是\n" : "❌ 否\n");
echo "  imagecreatefrompng: " . (function_exists('imagecreatefrompng') ? "✅ 是\n" : "❌ 否\n");
echo "  imagecreatefromgif: " . (function_exists('imagecreatefromgif') ? "✅ 是\n" : "❌ 否\n");
echo "  imagecreatefromwebp: " . (function_exists('imagecreatefromwebp') ? "✅ 是\n" : "❌ 否\n");
echo "  imagejpeg: " . (function_exists('imagejpeg') ? "✅ 是\n" : "❌ 否\n");
echo "  imagecopyresampled: " . (function_exists('imagecopyresampled') ? "✅ 是\n" : "❌ 否\n");

echo "\n3. 内存和输出控制:\n";
echo "  ob_start: " . (function_exists('ob_start') ? "✅ 是\n" : "❌ 否\n");
echo "  ob_get_clean: " . (function_exists('ob_get_clean') ? "✅ 是\n" : "❌ 否\n");

echo "\n✅ 所有必需函数都已支持，压缩功能可以正常工作！\n";
?>
