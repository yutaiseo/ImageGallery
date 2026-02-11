# 📸 演示截图快速指南

## 🎯 需要准备的截图

### 前台展示（2张）
1. **gallery.png** - 瀑布流主页
   - 访问：`http://localhost/` 或你的测试域名
   - 展示：至少12张演示图片的瀑布流布局
   - 分辨率：1920x1080
   - 要点：整洁美观、功能清晰

2. **viewer.png** - 全屏图片查看器
   - 点击任意图片进入全屏
   - 展示：图片预览、切换按钮、图片信息
   - 分辨率：1920x1080
   - 要点：展示动画效果、操作按钮

### 后台管理（4张）
3. **dashboard.png** - 管理后台仪表盘
   - 访问：`/admin/index.php`
   - 展示：统计信息、快速操作
   - 隐藏：真实用户名、域名

4. **manage.png** - 图片管理页面
   - 访问：`/admin/images.php`
   - 展示：图片列表、编辑/删除按钮
   - 隐藏：真实上传的图片（使用演示图片）

5. **backup.png** - 备份恢复界面
   - 访问：`/admin/backup.php`
   - 展示：备份列表、恢复按钮、云存储选项
   - 要点：突出核心功能

6. **cloud.png** - 云存储配置页面
   - 访问：`/admin/cloud.php`
   - 展示：OSS/S3/COS 配置表单
   - 隐藏：真实的 AccessKey（留空或用占位符）

---

## 🛠️ 快速准备步骤

### Step 1: 准备演示环境（5分钟）
```bash
# 1. 本地测试环境确保正常运行
# 访问 http://localhost/

# 2. 上传几张演示图片（无版权图片）
# 建议使用：Unsplash / Pexels 的免费图片
```

### Step 2: 截图工具
**Windows 推荐：**
- **Snipping Tool**（系统自带）
- **Win + Shift + S**（快捷键）
- **Snagit**（专业工具）

**浏览器全页截图：**
- Chrome: F12 → Ctrl+Shift+P → "Capture screenshot"

### Step 3: 截图规范
```
✅ 分辨率：1920x1080 或 1366x768
✅ 格式：PNG（保真）
✅ 文件名：
   - gallery.png
   - viewer.png
   - dashboard.png
   - manage.png
   - backup.png
   - cloud.png

❌ 避免包含：
   - 真实用户名
   - 真实域名
   - 真实 AccessKey/SecretKey
   - 个人隐私信息
```

### Step 4: 图片优化
```bash
# 使用在线工具压缩（保持质量）
# https://tinypng.com/
# 目标：单张 < 500KB
```

### Step 5: 保存位置
```bash
# 将截图保存到
docs/screenshots/

# 目录结构：
docs/
  screenshots/
    ├── gallery.png       ← 前台瀑布流
    ├── viewer.png        ← 全屏查看器
    ├── dashboard.png     ← 仪表盘
    ├── manage.png        ← 图片管理
    ├── backup.png        ← 备份恢复
    └── cloud.png         ← 云存储配置
```

---

## 📝 演示数据准备

### 上传演示图片
访问无版权图片网站：
- **Unsplash**: https://unsplash.com/
- **Pexels**: https://www.pexels.com/
- **Pixabay**: https://pixabay.com/

**下载建议：**
- 风景类：5-8张
- 科技类：3-5张
- 总数：12-20张
- 大小：每张 2-5MB

### 配置演示信息
在后台填写演示数据：
- **标题**: "Beautiful Landscape"
- **描述**: "Demo image for showcase"
- **用户名**: "admin" 或 "demo"

---

## ✅ 完成检查

截图完成后：
- [ ] 6张截图已保存到 `docs/screenshots/`
- [ ] 所有截图无真实敏感信息
- [ ] 文件大小合理（< 500KB）
- [ ] 图片清晰美观
- [ ] 文件名正确

然后推送到 GitHub：
```bash
git add docs/screenshots/*.png
git commit -m "docs: add demo screenshots for README"
git push origin main
```

---

## 🎨 美化技巧

1. **统一浏览器窗口大小**
   - F11 全屏或固定窗口大小

2. **隐藏敏感信息**
   - F12 开发者工具 → 编辑 DOM
   - 临时将用户名改为 "admin"
   - 将域名改为 "localhost"

3. **添加演示数据**
   - 确保页面有足够内容
   - 避免空白页面
   - 展示功能丰富性

4. **光标处理**
   - 截图前移开鼠标
   - 或使用定时截图功能

---

**准备好了吗？开始截图吧！📸**
