# 更新日志

## v1.4.1 - 紧急修复与稳定性增强 (2026-02-12)

### 修复项

**后台致命错误修复**
- ✅ 修复 `/admin/security.php` 的 `require_role()` 未定义错误
- ✅ 统一后台 CSRF 调用：`csrf_token()` / `verify_csrf()`
- ✅ 修复设置页与安全页因函数名不一致导致的提交/渲染异常

**待处理任务可手动执行**
- ✅ 新增 `ctrol/process_pending.php`
- ✅ 仪表盘/图片管理页增加“立即处理待处理任务”按钮
- ✅ 手动处理遵循系统配置（重试次数、压缩质量、压缩大小、是否删除原图）

**数据统计口径优化**
- ✅ 仪表盘新增“前台可见”统计口径（ready/null）
- ✅ 显示 pending/failed，减少前后端统计差异误解

**日志审计增强**
- ✅ `user_action_logs` 增加并兼容补齐：
  - `session_id`
  - `user_agent`
  - `request_uri`
  - `request_method`
  - `client_type`
- ✅ 日志页面支持以上字段展示与导出

**前端交互修复**
- ✅ 修复 `Gallery/assets/js/gadmin.js` 中批量操作确认后无法执行的问题

### 升级说明
- 新版本会通过 `db_migration.php` 自动补齐日志字段
- 升级后请强制刷新浏览器缓存（`Ctrl + F5`）

---

## v1.3.0 - 登录安全增强 (2026-02-12)

### 新功能

**登录安全系统**
- ✅ 登录失败次数限制（5次失败 → 临时封禁15分钟）
- ✅ IP黑名单管理（支持临时/永久封禁）
- ✅ 登录失败记录追踪（24小时内）
- ✅ 自动封禁恶意IP
- ✅ 失败次数实时提示（还剩X次机会）
- ✅ IP黑名单管理后台页面（`/admin/security.php`）
- ✅ 支持Cloudflare、Nginx Proxy真实IP获取

**增强的仪表盘**
- ✅ 存储空间统计（总大小、文件数量）
- ✅ 今日上传统计
- ✅ 7天上传趋势图表（Chart.js）
- ✅ 安全警告提示（失败登录、黑名单）
- ✅ 处理状态进度条
- ✅ 系统信息面板（PHP版本、内存、时区等）
- ✅ 操作日志图标分类

**日志增强**
- ✅ 所有操作日志添加session_id字段
- ✅ 支持真实IP记录（穿透代理）
- ✅ 登录成功自动清除失败记录

### 数据库变更

**新增表**
- `login_attempts` - 登录失败记录表
- `ip_blacklist` - IP黑名单表

**字段变更**
- `user_action_logs.session_id` - 新增会话ID字段

**升级SQL**
```sql
-- 执行升级脚本
mysql -u用户名 -p密码 数据库名 < docs/upgrade_v1.3.0.sql
```

### 安装更新

**新安装**
- 无需额外操作，安装页面已包含所有新表

**旧版本升级**
```bash
# 方法1：执行SQL脚本
mysql -u用户名 -p密码 数据库名 < docs/upgrade_v1.3.0.sql

# 方法2：宝塔面板/phpMyAdmin
复制 docs/upgrade_v1.3.0.sql 内容 → SQL标签页 → 执行
```

### 功能说明

#### 登录安全机制

1. **失败次数限制**
   - 同一用户名或IP，30分钟内失败5次触发封禁
   - 临时封禁15分钟后自动解除
   - 失败次数 ≥ 3次时显示剩余机会提示

2. **IP黑名单**
   - 临时封禁：15分钟后自动解除
   - 永久封禁：需手动解除
   - 支持手动添加可疑IP到黑名单

3. **日志记录**
   - 所有登录尝试（成功/失败）都记录
   - 包含IP、用户名、User-Agent、时间戳
   - Session ID追踪同一会话的所有操作

#### IP黑名单管理

访问：`/admin/security.php`（仅超级管理员）

- 查看所有黑名单IP（活跃/已过期）
- 手动添加IP到黑名单
- 移除黑名单IP
- 查看最近24小时登录失败记录
- 统计信息（活跃黑名单、失败次数）

#### 仪表盘增强

- **存储统计**：实时计算uploads目录大小和文件数
- **7天趋势图**：Chart.js可视化上传量变化
- **安全提示**：显示登录失败和黑名单警告
- **处理进度**：图片压缩状态百分比显示
- **系统信息**：PHP配置、内存、上传限制等

### 技术细节

**核心文件**
- `ctrol/login_security.php` - 登录安全函数库
- `ctrol/login.php` - 集成安全检查的登录页面
- `ctrol/logger.php` - 增强的日志记录（支持真实IP）
- `ctrol/security.php` - IP黑名单管理页面
- `ctrol/index.php` - 增强的仪表盘

**函数列表**
```php
is_ip_blacklisted($pdo, $ipAddress)            // 检查IP是否在黑名单
add_to_blacklist($pdo, $ip, $reason, $type)    // 添加IP到黑名单
record_login_failure($pdo, $username, $ip)     // 记录登录失败
clear_login_failures($pdo, $username, $ip)     // 清除失败记录
get_login_failure_count($pdo, $username, $ip)  // 获取失败次数
check_and_block_if_needed($pdo, $username, $ip)// 检查并自动封禁
get_client_ip()                                // 获取真实IP（支持代理）
```

### 安全建议

1. **定期清理日志**
   ```sql
   -- 清理30天前的登录失败记录
   DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
   
   -- 清理已过期的临时黑名单
   DELETE FROM ip_blacklist WHERE block_type = 'temporary' AND expires_at < NOW();
   ```

2. **配置监控告警**
   - 监控`login_attempts`表，失败次数异常时发送邮件
   - 定期检查`ip_blacklist`表的永久封禁记录

3. **强密码策略**
   - 建议修改默认管理员密码
   - 使用复杂密码（字母+数字+符号）

### 兼容性

- PHP >= 7.4
- MySQL >= 5.7
- 向后兼容：旧版本数据库自动兼容（不影响未升级用户）

### 已知问题

无

### 下一版本计划

- [ ] 双因素认证（2FA/TOTP）
- [ ] 邮件通知（登录异常、磁盘空间不足）
- [ ] API访问密钥管理
- [ ] 更多图表类型（访问量、客户端统计）

---

## v1.2.0 - 异步处理支持 (2026-02-12)

### 新功能

**异步上传处理**
- ✅ 图片上传后进入队列，后台异步压缩
- ✅ Worker定时任务处理待压缩图片
- ✅ 支持失败重试（最多3次）
- ✅ 图片状态管理（ready/pending/failed）

**Worker脚本**
- 路径：`ctrol/workers/process_upload_tasks.php`
- CLI参数：`--limit=N` `--max-attempts=N` `--verbose`
- 日志位置：`Gallery/logs/worker.log`

**数据库变更**
- 新增 `upload_tasks` 表（任务队列）
- `images` 表新增字段：`status`、`original_path`、`processed_at`

### 部署配置

**Cron定时任务（每5分钟）**
```bash
*/5 * * * * php /path/to/ctrol/workers/process_upload_tasks.php >> Gallery/logs/worker.log 2>&1
```

**Docker环境**
- 已添加cron支持到Dockerfile
- 自动启动Worker定时任务

### 文档

- [Worker使用指南](Worker使用指南.md)
- [部署说明](部署说明.md)
- [升级SQL](upgrade_v1.2.0.sql)

---

## v1.1.0 及之前版本

请参阅 [CHANGELOG.md](CHANGELOG.md)
