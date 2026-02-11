# 🖼️ ImageGallery - 专业图片管理系统

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4+-blue?logo=php" alt="PHP Version">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="License">
  <img src="https://img.shields.io/badge/PRs-Welcome-brightgreen" alt="PRs Welcome">
</p>

**ImageGallery** 是一个功能完整的企业级图片管理系统，支持本地存储和多云对象存储，内置智能备份恢复、权限管理、自动压缩等功能。适合个人、团队和企业使用。

## ✨ 核心特性

### 🎯 前台展示
- **瀑布流布局** - 响应式设计，完美适配PC/平板/手机
- **全屏查看器** - 流畅的图片浏览体验，支持键盘/触摸操作
- **智能预加载** - 预加载队列系统，瞬间切换图片
- **平滑动画** - 原生应用级的切换动画效果
- **增量加载** - 滚动自动加载更多，无限浏览

### 🔧 后台管理
- **图片管理** 
  - 拖放上传 + 批量处理
  - 自动压缩优化（可配置）
  - 标题/描述编辑
  - 软删除 + 回收站
  - 批量下载
  
- **云存储集成** ☁️
  - 阿里云 OSS
  - AWS S3
  - 腾讯云 COS
  - OpenStack Swift
  - 可视化配置界面
  
- **备份恢复** 💾
  - 一键备份（数据库+程序+文件）
  - 可选择性恢复
  - 云端同步备份
  - 定时自动备份
  - 备份保留策略
  
- **用户管理**
  - 多用户支持
  - 角色权限控制
  - 操作日志审计
  - 登录日志
  
- **系统监控**
  - 实时统计信息
  - 存储使用分析
  - 操作日志查看
  - 性能监控

## 🚀 快速开始

### 环境要求
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache / Nginx
- PHP 扩展：PDO, GD, ZipArchive, JSON

### 安装步骤

#### 1. 下载代码
```bash
git clone https://github.com/yourusername/ImageGallery.git
cd ImageGallery
```

#### 2. 配置服务器

**Apache (.htaccess 已包含)**
确保启用 `mod_rewrite`

**Nginx 配置**
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location /admin/ {
    try_files $uri $uri/ /index.php?$query_string;
}

location /install/ {
    try_files $uri $uri/ /index.php?$query_string;
}
```

#### 3. 访问安装向导
```
http://your-domain.com/install/
```

按照向导完成：
1. 环境检测
2. 数据库配置
3. 管理员账号创建
4. 完成安装

### 配置说明

#### 云存储配置
访问 `/admin/cloud.php` 配置云服务商：

```json
{
  "enabled": true,
  "endpoint": "oss-cn-hangzhou.aliyuncs.com",
  "key_id": "YOUR_ACCESS_KEY_ID",
  "key_secret": "YOUR_ACCESS_KEY_SECRET",
  "bucket": "your-bucket-name",
  "prefix": "images/"
}
```

#### 定时备份（可选）
添加系统计划任务（Cron）：
```bash
# 每天凌晨2点自动备份
0 2 * * * curl -s "https://your-domain.com/admin/backup.php?cron=1&token=YOUR_TOKEN"
```

Token 在 `/admin/backup.php` 页面获取。

## 📸 功能展示

### 前台展示
![前台瀑布流](.github/screenshots/gallery.png)
![全屏查看器](.github/screenshots/viewer.png)

### 后台管理
![仪表盘](.github/screenshots/dashboard.png)
![图片管理](.github/screenshots/manage.png)
![备份恢复](.github/screenshots/backup.png)
![云存储配置](.github/screenshots/cloud.png)

## 📚 文档

- [部署指南](docs/部署说明.md)
- [功能清单](docs/后台功能清单.md)
- [开发记录](docs/开发记录.md)
- [版本管理](docs/版本管理说明.md)
- [更新日志](docs/CHANGELOG.md)

## 🛣️ 路线图

### ✅ v1.1 已完成
- [x] 云存储配置GUI
- [x] 备份恢复功能
- [x] 瀑布流布局优化
- [x] 全屏查看器动画

### 🚧 v1.2 计划中
- [ ] 用户权限细粒度控制
- [ ] IP 白名单
- [ ] 两步验证（TOTP）
- [ ] 七牛云支持
- [ ] 又拍云支持

### 💡 v2.0 未来愿景
- [ ] RESTful API
- [ ] 插件系统
- [ ] 主题系统
- [ ] Docker 支持
- [ ] 多语言支持

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 📄 开源协议

本项目采用 [MIT License](LICENSE) 开源协议。

**商业使用注意事项：**
- ✅ 免费用于个人和商业项目
- ✅ 可修改、分发、再授权
- ❌ 作者不承担任何担保责任
- 📌 保留原始版权声明

## 💰 赞助支持

如果这个项目对你有帮助，可以考虑赞助支持：

- ⭐ 给项目点个 Star
- 🐛 提交 Bug 报告
- 💡 提出功能建议
- ☕ [请作者喝咖啡](#) （支付宝/微信二维码）

## 📞 联系方式

- 作者：[Your Name]
- 邮箱：your.email@example.com
- 项目主页：https://github.com/yourusername/ImageGallery
- 问题反馈：https://github.com/yourusername/ImageGallery/issues

## 🙏 致谢

- [Bootstrap 5](https://getbootstrap.com/) - UI 框架
- [Font Awesome](https://fontawesome.com/) - 图标库
- [lightGallery](https://www.lightgalleryjs.com/) - 灵感来源

---

<p align="center">Made with ❤️ by Your Name</p>
