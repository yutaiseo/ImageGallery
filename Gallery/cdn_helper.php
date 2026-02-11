<?php
function cdn_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $json = ' {
        "providers": {
            "bootcdn": {"base": "https://cdn.bootcdn.net"},
            "staticfile": {"base": "https://cdn.staticfile.net"},
            "google": {"base": "https://ajax.googleapis.com"},
            "cdnjs": {"base": "https://cdnjs.cloudflare.com"},
            "jsdelivr": {"base": "https://cdn.jsdelivr.net"},
            "unpkg": {"base": "https://unpkg.com"}
        },
        "priority": {
            "cn": ["bootcdn", "staticfile", "cdnjs", "jsdelivr", "unpkg", "google"],
            "global": ["google", "cdnjs", "jsdelivr", "unpkg", "staticfile", "bootcdn"]
        },
        "assets": {
            "bootstrap_css": {
                "bootcdn": "ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css",
                "staticfile": "bootstrap/5.3.3/css/bootstrap.min.css",
                "cdnjs": "ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css",
                "jsdelivr": "npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
                "unpkg": "bootstrap@5.3.3/dist/css/bootstrap.min.css"
            },
            "bootstrap_js": {
                "bootcdn": "ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js",
                "staticfile": "bootstrap/5.3.3/js/bootstrap.bundle.min.js",
                "cdnjs": "ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js",
                "jsdelivr": "npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js",
                "unpkg": "bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            },
            "hammer_js": {
                "bootcdn": "ajax/libs/hammer.js/2.0.8/hammer.min.js",
                "staticfile": "hammerjs/2.0.8/hammer.min.js",
                "cdnjs": "ajax/libs/hammer.js/2.0.8/hammer.min.js",
                "jsdelivr": "npm/hammerjs@2.0.8/hammer.min.js",
                "unpkg": "hammerjs@2.0.8/hammer.min.js"
            },
            "fontawesome_css": {
                "bootcdn": "ajax/libs/font-awesome/6.5.1/css/all.min.css",
                "staticfile": "font-awesome/6.5.1/css/all.min.css",
                "cdnjs": "ajax/libs/font-awesome/6.5.1/css/all.min.css",
                "jsdelivr": "npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css",
                "unpkg": "@fortawesome/fontawesome-free@6.5.1/css/all.min.css"
            }
        }
    }';

    $config = json_decode($json, true);
    return $config ?: [];
}

function cdn_fetch_ip($url, $source)
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 0.6,
            'method' => 'GET',
            'header' => "User-Agent: img-cdn-helper\r\n"
        ]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    $ip = trim((string)$resp);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $_SESSION['cdn_public_ip_source'] = $source;
        return $ip;
    }
    return '';
}

function cdn_client_ip()
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    foreach ($candidates as $raw) {
        if (!$raw) {
            continue;
        }
        $parts = explode(',', $raw);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '';
}

function cdn_public_ip()
{
    if (!empty($_SESSION['cdn_public_ip']) && !empty($_SESSION['cdn_public_ip_at'])) {
        if (time() - (int)$_SESSION['cdn_public_ip_at'] < 3600) {
            return (string)$_SESSION['cdn_public_ip'];
        }
    }

    $ip = cdn_client_ip();
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '';
    }

    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $_SESSION['cdn_public_ip'] = $ip;
        $_SESSION['cdn_public_ip_at'] = time();
        return $ip;
    }

    // 不要在这里同步等待获取IP，改用客户端异步检测
    // 直接返回空，让 cdn_region() 根据语言决策
    return '';
}

function cdn_region()
{
    $lang = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $ip = cdn_public_ip();
    $cached_region = $_SESSION['cdn_preferred_region'] ?? '';

    // 如果有缓存的最优区域，直接用
    if (!empty($cached_region) && in_array($cached_region, ['cn', 'global'], true)) {
        return $cached_region;
    }

    // 快速判断：根据语言
    if (strpos($lang, 'zh') !== false) {
        return 'cn';
    }

    // 已有公网IP且来自CN
    $source = $_SESSION['cdn_public_ip_source'] ?? '';
    if ($source === 'cn' || ($ip !== '' && is_chinese_ip($ip))) {
        return 'cn';
    }

    return 'global';
}

function is_chinese_ip($ip)
{
    // 简单判断：CN 的 IP 段（这是示例，实际可用 GeoIP 库）
    // 为避免阻塞，这里只做快速判断，不做实时查询
    return false;  // 保守估计，unless 有明确信息
}

function cdn_asset_url($key)
{
    $config = cdn_config();
    $assets = $config['assets'][$key] ?? [];
    $providers = $config['providers'] ?? [];
    
    // 优先使用缓存的最快 CDN
    $fastest = $_SESSION['cdn_fastest'] ?? '';
    if (!empty($fastest) && !empty($assets[$fastest])) {
        $base = $providers[$fastest]['base'] ?? '';
        if ($base !== '') {
            return rtrim($base, '/') . '/' . ltrim($assets[$fastest], '/');
        }
    }
    
    // 回退到基于地区的优先级
    $region = cdn_region();
    $priority = $config['priority'][$region] ?? ($config['priority']['global'] ?? []);

    foreach ($priority as $provider) {
        if (empty($assets[$provider])) {
            continue;
        }
        $base = $providers[$provider]['base'] ?? '';
        if ($base === '') {
            continue;
        }
        return rtrim($base, '/') . '/' . ltrim($assets[$provider], '/');
    }

    return '';
}
