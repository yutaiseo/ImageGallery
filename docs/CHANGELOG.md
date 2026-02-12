# 管理后台变更记录

## v1.4.1 - 2026-02-12 - 紧急修复与可运维增强

**版本标签：** Hotfix | 稳定性修复

**修复内容：**

**1. 安全页致命错误修复**（`/admin/security.php`）
- 修复 `require_role()` 未定义导致的 Fatal error
- 改为使用 `require_superadmin()` 权限校验
- 修复 CSRF 函数名不一致问题（统一使用 `csrf_token()` / `verify_csrf()`）

**2. 设置页渲染失败修复**（`/admin/settings.php`）
- 修复 CSRF 函数调用错误导致页面无法正常提交/渲染的问题
- 统一使用共享 CSRF helper，避免白屏与提交失败

**3. 待处理图片支持手动触发处理**
- 新增 `ctrol/process_pending.php`，支持后台一键处理 pending 任务
- 仪表盘与图片管理页新增“立即处理待处理任务”入口
- 支持读取系统配置：`worker_max_attempts`、`compress_quality`、`compress_max_size`、`worker_delete_original`

**4. 前后端统计口径对齐**
- 仪表盘新增“前台可见”统计（`is_deleted=0 AND status=ready/null`）
- 保留总量统计并明确区分 pending/failed，减少“前台数量与后台数量不一致”的认知误差

**5. 日志审计增强**
- `user_action_logs` 增加（自动补齐）字段：
  - `user_agent`
  - `request_uri`
  - `request_method`
  - `client_type`
  - `session_id`（兼容补齐）
- 日志页面 `ctrol/logs.php` 支持动态展示与导出以上字段

**6. 确认按钮点击失效修复**
- 修复 `Gallery/assets/js/gadmin.js` 中批量操作事件对 `form.action` 的错误使用
- 改为读写隐藏字段 `input[name="action"]`，恢复“确认/恢复/删除”等按钮可点击执行

**文件修改：**
- `ctrol/security.php`
- `ctrol/settings.php`
- `ctrol/process_pending.php`（新增）
- `ctrol/index.php`
- `ctrol/images.php`
- `ctrol/login.php`
- `ctrol/logger.php`
- `ctrol/logs.php`
- `Gallery/assets/js/gadmin.js`
- `Gallery/db_migration.php`
- `Gallery/index.php`

**升级说明：**
- 访问任意后台页面将自动触发 `db_migration.php` 补齐日志字段
- 建议升级后强制刷新浏览器缓存（`Ctrl + F5`）

---

## v1.4.0 - 2026-02-12 - 系统配置增强

**版本标签：** Stable | 配置管理

**新增功能：**

**1. 系统设置页面重构**（`/admin/settings.php`）
- 配置分类展示：8个配置组，使用标签页组织
- 图形化配置界面：Bootstrap 5样式，图标标识
- 配置验证：数值类型字段添加min/max限制
- 在线帮助：每个配置项显示说明文字
- 配置说明卡片：详细的最佳实践建议

**2. 新增配置项**（共30+配置）
- **基本设置**：站点标题、描述、URL、Logo
- **SEO配置**：Meta标题、关键词、描述
- **图片压缩**：
  - `compress_quality`：压缩质量（1-100，默认75）
  - `compress_max_size`：目标大小（100-5000KB，默认800KB）
  - `compress_threshold`：压缩阈值（默认800KB）
  - `allowed_extensions`：允许的文件类型
  - `max_upload_count`：单次上传数量限制
- **安全设置**：
  - `login_max_attempts`：最大失败次数（3-10，默认5）
  - `login_block_duration`：封禁时长（5-1440分钟，默认15）
  - `session_timeout`：会话超时（0-1440分钟，默认120）
  - `force_https`：强制HTTPS
- **Worker配置**：
  - `worker_enabled`：启用/禁用Worker
  - `worker_batch_limit`：批量处理限制（1-200，默认50）
  - `worker_max_attempts`：最大重试次数（1-5，默认3）
  - `worker_delete_original`：删除原图（节省50%空间）
- **前台显示**：
  - `gallery_per_page`：每页数量（6-100，默认12）
  - `gallery_sort_order`：排序方式（创建时间/标题）
  - `gallery_layout`：布局方式（网格/瀑布流/列表）
  - `show_image_count`：显示图片数量
- **备案信息**：ICP备案号、公安备案号及链接
- **其他**：备份路径、注册开关、邮件验证、评论功能

**3. 配置项应用**
- `login_security.php`：动态读取`login_max_attempts`和`login_block_duration`
- `process_upload_tasks.php`（Worker）：读取`worker_enabled`、`worker_batch_limit`、`worker_max_attempts`、`compress_quality`、`compress_max_size`
- `update_image.php`：更新图片时使用配置的压缩参数
- `bootstrap.php`：新增`get_setting()`辅助函数

**数据库变更：**
- `db_migration.php`：自动初始化14个新配置项默认值
- 使用`INSERT IGNORE`确保重复执行安全

**文件修改：**
- `ctrol/settings.php`：完全重写，8组配置标签页
- `ctrol/bootstrap.php`：添加`get_setting()`函数
- `ctrol/login_security.php`：添加`get_security_setting()`函数  
- `ctrol/workers/process_upload_tasks.php`：添加`get_worker_setting()`函数，动态读取配置
- `ctrol/update_image.php`：使用`get_setting()`读取压缩参数
- `Gallery/db_migration.php`：初始化14个新配置项

**技术亮点：**
- 可扩展的配置框架：易于添加新配置组
- 类型验证：number类型自动限制范围
- select_custom类型：支持自定义选项（如排序、布局）
- 缓存机制：配置读取使用静态缓存，减少数据库查询
- 零硬编码：所有可调参数移至数据库配置

**用户体验：**
- 标签页导航：配置清晰分组，易于查找
- 图标图形化：每组配置使用FontAwesome图标
- 即时帮助：配置项下方显示使用建议
- 配置说明卡片：包含4类配置的最佳实践

**升级方法：**
访问后台任意页面会自动执行`db_migration.php`初始化新配置，无需手动操作。

---

## v1.3.0 - 2026-02-12 - 登录安全增强

**版本标签：** Stable | 安全加固

**新增功能：**

**1. 登录安全系统**
- 登录失败次数限制：30分钟内失败5次自动封禁IP 15分钟
- IP黑名单管理：支持临时封禁（自动解除）和永久封禁（手动解除）
- 登录失败记录追踪：记录所有失败尝试（用户名、IP、时间、User-Agent）
- 自动封禁机制：达到阈值自动触发临时封禁并记录日志
- 失败次数提示：失败3次后显示"还剩X次机会"友好提示
- 真实IP获取：支持Cloudflare、Nginx Proxy等反向代理环境

**2. IP黑名单管理页面**（`/admin/security.php` - 仅超级管理员）
- 黑名单列表：显示所有封禁IP（活跃/已过期状态）
- 手动添加IP：支持临时/永久封禁，填写封禁原因
- 移除黑名单：一键解除封禁
- 失败记录查看：最近24小时登录失败统计
- 详情模态框：查看封禁详细信息

**3. 增强的仪表盘**（`/admin/index.php`）
- 存储空间统计：实时计算uploads目录大小和文件数量
- 今日上传统计：当天新增图片数量
- 7天上传趋势图：使用Chart.js可视化展示上传量变化
- 安全警告提示：显示登录失败次数和黑名单IP数量（超级管理员）
- 处理状态进度条：图片压缩完成百分比可视化
- 系统信息面板：PHP版本、内存限制、上传限制、时区
- 操作日志图标：不同操作类型显示对应颜色图标

**数据库变更：**
- 新增表 `login_attempts`（登录失败记录）
  - 字段：id, username, ip_address, attempt_time, user_agent, is_blocked
  - 索引：username, ip_address, attempt_time
- 新增表 `ip_blacklist`（IP黑名单）
  - 字段：id, ip_address, reason, block_type, created_at, expires_at, created_by
  - 索引：ip_address, expires_at
- `user_action_logs` 表新增字段 `session_id`（会话追踪）

**核心文件：**
- `ctrol/login_security.php`：登录安全函数库（15个核心函数）
- `ctrol/login.php`：集成安全检查的登录逻辑
- `ctrol/logger.php`：增强真实IP获取和session_id记录
- `ctrol/security.php`：IP黑名单管理页面
- `ctrol/index.php`：增强的仪表盘
- `docs/upgrade_v1.3.0.sql`：v1.3.0升级脚本

**升级方法：**
```bash
# 方法1：命令行
mysql -u用户名 -p密码 数据库名 < docs/upgrade_v1.3.0.sql

# 方法2：宝塔面板/phpMyAdmin
复制 docs/upgrade_v1.3.0.sql 内容 → SQL标签页 → 执行
```

**安全建议：**
- 定期清理30天前的登录失败记录
- 监控黑名单异常增长（可能遭受攻击）
- 修改默认管理员密码为复杂密码

---

## v1.2.0 - 2026-02-12 - 异步处理支持

**版本标签：** Stable | 性能优化

**新增功能：**
- 异步上传处理流水线：图片上传后进入队列，Worker后台压缩
- Worker定时任务处理脚本（`ctrol/workers/process_upload_tasks.php`）
- 图片状态管理：ready（已就绪）、pending（待处理）、failed（失败）
- 系统诊断页面（`/admin/diagnostics.php` - 仅超级管理员）
- 登录页面UI优化：隐藏导航栏，居中布局

**数据库变更：**
- 新增 `upload_tasks` 表（任务队列）
- `images` 表新增字段：status, original_path, processed_at

**部署配置：**
```bash
# Cron定时任务（每5分钟）
*/5 * * * * php /path/to/ctrol/workers/process_upload_tasks.php >> Gallery/logs/worker.log 2>&1
```

**文档：**
- [Worker使用指南](Worker使用指南.md)
- [部署测试指南](TESTING_DEPLOYMENT.md)
- [部署检查清单](DEPLOYMENT_CHECKLIST.md)

---

## v1.1.0 - 2026-02-11 - 后端认证系统完整实现

**版本标签：** Stable | 核心功能完整

**关键修复：**
- `Gallery/index.php`：添加 `ob_start()` 缓冲区，修复"headers already sent"错误
- `ctrol/login.php`：从空壳重定向实现为完整登录处理器
  - bcrypt 密码验证（使用 `password_verify()` 和 `password_hash(PASSWORD_DEFAULT)`）
  - 会话初始化（`$_SESSION['loggedin']`、`username`、`user_id`、`role`）
  - CSRF token 生成（`admin_on_login()`）
  - 登录成功/失败日志记录
  - 使用公共 [Gallery/header.php](../Gallery/header.php) 和 [Gallery/footer.php](../Gallery/footer.php)
- `ctrol/change_password.php`：从空壳重定向实现为完整密码修改处理器
  - 当前密码验证
  - 新密码强度检查（最小 8 字符）
  - 安全的哈希存储与日志记录
- `ctrol/logout.php`：改进会话清理逻辑与日志记录
- `ctrol/config/config.php`：修正 `upload_storage_path()` 路径指向 `Gallery/uploads`

**页面设计：**
- login 页面设置 `$showNavbar = false`（访客页面）
- change_password 页面保持导航栏（已登录用户）
- 共同关闭不需要的脚本变量：`$includeGalleryScripts = false`、`$includeClockScript = false`

## 以下为本次对后台的增量改造（按顺序执行）

1. 添加后台轻量框架（`ctrol/bootstrap.php` / `auth.php` / `csrf.php` / `header.php` / `footer.php`）
2. 完善登录流程（`login.php`：设置 `$_SESSION['username']`，调用 `admin_on_login()` 并跳转到 `/admin/index.php`）
3. 新增管理页面：
  - `ctrol/index.php`：简易仪表盘（用户数、图片数）
  - `ctrol/users.php`：用户管理（分页、搜索、单/批量删除、日志）
  - `ctrol/images.php`：图片管理（分页、搜索、软删除、批量操作、下载按钮）
  - `ctrol/recycle.php`：回收站（恢复、永久删除、批量操作、下载按钮）
  - `ctrol/logs.php`：管理员操作日志查看与 CSV 导出
4. 数据库补充：在 `config.php` 中添加 `images` 表的软删除字段：`is_deleted`、`deleted_at`、`deleted_by`（通过 `addColumnIfNotExists` 自动创建）
5. 安全改进：
   - 为 `uploads/` 添加 `.htaccess` 与 `index.html`（阻止目录访问和脚本执行）。
   - 使用 PDO 预处理、`password_hash`/`password_verify`、CSRF Token、`htmlspecialchars()` 输出转义。 
   - 新增 `download.php`：通过图片 ID 安全提供下载或重定向远程图片，且限制文件必须位于 `uploads/` 目录内。
6. 操作日志：新增 `ctrol/logger.php`，将管理员关键动作写入 `user_action_logs` 表，便于审计。

## 验收与测试步骤

1. 登录：访问 `/admin/login.php`，使用管理员账号登录，应跳转到 `/admin/index.php` 并显示统计。
2. 用户管理：进入 `/admin/users.php`，尝试搜索、分页，单个删除与多选批量删除，确认 `user_action_logs` 有对应记录。
3. 图片管理：进入 `/admin/images.php`，搜索分页，尝试单个删除（应移入回收站）、多选批量删除（应移入回收站），并查看 `/admin/recycle.php`。
4. 回收站：在 `/admin/recycle.php` 恢复或永久删除图片，确认永久删除会从磁盘移除文件，并记录日志。
5. 日志导出：在 `/admin/logs.php` 点击导出 CSV，下载文件能打开且包含当前页日志。
6. 下载：在图片项点击“下载”应触发 `download.php?id=...`，若是远程图片会重定向，若是本地图片会安全返回文件。 

## 注意事项与后续建议

- 若运行在 nginx，请把 .htaccess 对应规则转换为 nginx 配置（`uploads` 禁止解析 PHP、禁止访问敏感文件）。
- 建议把 `config.php` 或备份移出 webroot，或至少通过 webserver 配置阻止访问。
- 可考虑添加更细粒度的权限（角色/权限表）和操作确认二次验证用于高风险操作（永久删除）。
- 若流量大，`download.php` 可改为内部 X-Accel-Redirect / X-Sendfile 支持以提高性能。
