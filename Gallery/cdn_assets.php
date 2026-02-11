<?php
require_once __DIR__ . '/cdn_helper.php';

function render_cdn_css(array $keys)
{
    foreach ($keys as $key) {
        $url = cdn_asset_url($key);
        if ($url) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . PHP_EOL;
        }
    }
}

function render_cdn_js(array $keys)
{
    foreach ($keys as $key) {
        $url = cdn_asset_url($key);
        if ($url) {
            echo '<script src="' . htmlspecialchars($url) . '"></script>' . PHP_EOL;
        }
    }
}
