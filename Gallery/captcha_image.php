<?php
session_start();

$mode = $_GET['mode'] ?? 'text';
if (!extension_loaded('gd')) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'GD not available';
    exit;
}

if ($mode === 'rotate') {
    $deg = (int)($_SESSION['captcha_rotate_degrees'] ?? 0);
    $size = 140;
    $img = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($img, 245, 247, 250);
    $fg = imagecolorallocate($img, 37, 114, 222);
    imagefill($img, 0, 0, $bg);

    $center = $size / 2;
    $arrowLen = 40;
    imageline($img, $center, $center + $arrowLen / 2, $center, $center - $arrowLen, $fg);
    imageline($img, $center, $center - $arrowLen, $center - 10, $center - $arrowLen + 12, $fg);
    imageline($img, $center, $center - $arrowLen, $center + 10, $center - $arrowLen + 12, $fg);

    $rotated = imagerotate($img, $deg, $bg);
    imagedestroy($img);

    header('Content-Type: image/png');
    imagepng($rotated);
    imagedestroy($rotated);
    exit;
}

$text = $_SESSION['captcha_text'] ?? '';
if ($text === '') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

$width = 160;
$height = 50;
$img = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($img, 245, 247, 250);
$fg = imagecolorallocate($img, 32, 90, 160);
$noise = imagecolorallocate($img, 180, 190, 200);
imagefill($img, 0, 0, $bg);

for ($i = 0; $i < 6; $i++) {
    imageline($img, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $noise);
}

for ($i = 0; $i < 80; $i++) {
    imagesetpixel($img, random_int(0, $width - 1), random_int(0, $height - 1), $noise);
}

$fontSize = 5;
$offsetX = 18;
$offsetY = 18;
for ($i = 0; $i < strlen($text); $i++) {
    imagestring($img, $fontSize, $offsetX + ($i * 22), $offsetY + random_int(-3, 3), $text[$i], $fg);
}

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
