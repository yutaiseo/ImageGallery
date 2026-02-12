# 宝塔 Nginx 配置调试指南

> 通用部署与路由配置请先看 [部署说明](部署说明.md)，本文仅覆盖宝塔特有问题。

## 问题症状

访问 `/install/` 或 `/admin/` 返回 404，虽然已设置了 location 路由。

## 根本原因

1. **重写规则冲突**：宝塔的 `/www/server/panel/vhost/rewrite/youdomain.com.conf` 可能包含全局规则，拦截了 /install/ 或 /admin/ 请求
2. **Location 匹配顺序**：敏感文件过滤在路由配置前执行，导致请求被拦截
3. **目录不存在**：如果 /www/wwwroot/youdomain.com/Gallery/install/ 不存在，try_files 会失败

## 解决方案

### 方案 A：直接修改宝塔配置（推荐）

将以下配置替换现有的对应部分，确保**路由在敏感规则之前**：

```nginx
server {
    listen 80;
    server_name youdomain.com;
    index index.php index.html index.htm default.php default.htm default.html;
    root /www/wwwroot/youdomain.com/Gallery;

    #CERT-APPLY-CHECK--START
    include /www/server/panel/vhost/nginx/well-known/youdomain.com.conf;
    #CERT-APPLY-CHECK--END
    
    include /www/server/panel/vhost/nginx/extension/youdomain.com/*.conf;

    # ========== 重点：路由配置放在最前面 ==========
    # 网站路由结构（必须在敏感文件过滤之前）
    location /install/ { 
        try_files $uri $uri/ /install/index.php?$query_string; 
    }
    location /admin/ { 
        try_files $uri $uri/ /admin/index.php?$query_string; 
    }
    
    # PHP 处理
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    # ========== 路由配置结束 ==========

    error_page 404 /404.html;

    include enable-php-74.conf;

    # 重写规则（宝塔自动生成，可能需要排除 /install/ 和 /admin/）
    include /www/server/panel/vhost/rewrite/youdomain.com.conf;

    # 禁止访问的敏感文件（排除路由目录）
    location ~* ^/(?!install/|admin/).*\.(user\.ini|htaccess|env.*|DS_Store|gitignore|composer\.json|package\.json|\.sql)$ {
        return 404;
    }

    # 禁止访问的敏感目录
    location ~* /(\.git|\.svn|\.vscode|\.idea|node_modules)/ {
        return 404;
    }

    location ~ \.well-known {
        allow all;
    }

    if ( $uri ~ "^/\.well-known/.*\.(php|jsp|py|js|css|lua|ts|go|zip|tar\.gz|rar|7z|sql|bak)$" ) {
        return 403;
    }

    location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$ {
        expires 30s;
        error_log /dev/null;
        access_log /dev/null;
    }

    location ~ .*\.(js|css)$ {
        expires 12s;
        error_log /dev/null;
        access_log /dev/null;
    }

    gzip on;
    gzip_min_length 1m;
    gzip_buffers 4 16k;
    gzip_http_version 1.1;
    gzip_comp_level 3;
    gzip_types text/plain application/javascript application/x-javascript text/javascript text/css application/xml application/json;
    gzip_vary on;
    gzip_proxied expired no-cache no-store private auth;
    gzip_disable "MSIE [1-6]\.";

    access_log /www/wwwlogs/your-domain.com.log;
    error_log /www/wwwlogs/your-domain.com.error.log;
}
```

### 方案 B：检查并修改宝塔重写规则文件

编辑 `/www/server/panel/vhost/rewrite/your-domain.com.conf`：

1. 打开宝塔面板 → 网站 → your-domain.com → 反向代理/重写规则
2. 查看是否有类似以下的规则，若有，添加排除：

```nginx
# 例：如果存在这样的全局规则
if (!-f $request_filename){
    set $rule_0 1;
}
if (!-d $request_filename){
    set $rule_0 "${rule_0}1";
}
# 修改为排除路由目录
if ($uri !~ "^/(install|admin)/") {
    if (!-f $request_filename){
        set $rule_0 1;
    }
    if (!-d $request_filename){
        set $rule_0 "${rule_0}1";
    }
}
```

### 方案 C：通过 Nginx 配置文件添加路由（推荐备选）

如果伪静态配置有问题，可以直接在 Nginx 配置中添加：

1. 宝塔面板 → 网站 → your-domain.com → 配置文件
2. 在 `include /www/server/panel/vhost/rewrite/your-domain.com.conf;` **之后**，添加：

```nginx
# 网站路由结构（在 rewrite 包含之后）
location /install/ { 
    try_files $uri $uri/ /install/index.php?$query_string; 
}
location /admin/ { 
    try_files $uri $uri/ /admin/index.php?$query_string; 
}
```

3. 保存并重启 Nginx（不需要额外的 PHP location 块）

## 验证步骤

配置完成后，执行：

```bash
# 1. 检查 Nginx 配置语法
nginx -t

# 2. 重启 Nginx
systemctl restart nginx
# 或通过宝塔面板：网站 → 重启

# 3. 测试访问
curl -I http://your-domain.com/install/
# 应返回 200（或 302 重定向），而非 404

# 4. 查看错误日志
tail -f /www/wwwlogs/your-domain.com.error.log
```

## 目录结构检查

确保文件物理存在：

```bash
# 检查 Gallery 目录
ls -la /www/wwwroot/your-domain.com/Gallery/

# 检查 install 目录
ls -la /www/wwwroot/your-domain.com/Gallery/install/

# 检查 admin 目录
ls -la /www/wwwroot/your-domain.com/Gallery/admin/
```

如果目录不存在，需要先创建或从代码库复制。

## 常见错误

| 症状 | 原因 | 解决 |
|------|------|------|
| /install/ 404 | 重写规则拦截 | 确保路由在重写规则前 |
| /admin/ 404 | 敏感词过滤 | 检查正则表达式是否包含 admin |
| 502 Bad Gateway | PHP-FPM 未运行或端口错误 | 检查 fastcgi_pass 127.0.0.1:9000 |
| 重定向循环 | try_files 指向不存在的 PHP | 确保 /install/index.php 存在 |

## 备注

- 若需伪静态规则，请参考 [部署说明](部署说明.md) 中的 Nginx 伪静态示例。

看是否有具体的错误信息（如 rewrite loop、file not found 等）。

### 3. 最后手段：Nginx 配置

如果伪静态都不行，直接在 Nginx 配置中添加（不包含 PHP 处理块）：

```nginx
# 在 include enable-php-74.conf; 之后添加

location /install/ { 
    try_files $uri $uri/ /install/index.php?$query_string; 
}
location /admin/ { 
    try_files $uri $uri/ /admin/index.php?$query_string; 
}
# 不要添加 location ~ \.php$ 块，宝塔已经通过 enable-php-74.conf 处理了
```

然后测试语法并重启：
```bash
nginx -t
systemctl restart nginx
```
