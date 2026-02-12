# 管理后台变更记录

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
