# 贡献指南

感谢你考虑为 ImageGallery 做出贡献！

## 行为准则

参与本项目即表示你同意遵守我们的行为准则。请保持友好和尊重。

## 如何贡献

### 报告 Bug

如果你发现 Bug，请创建一个 Issue，包含：

1. **清晰的标题**
2. **详细描述**：
   - 预期行为
   - 实际行为
   - 复现步骤
3. **环境信息**：
   - PHP 版本
   - 数据库版本
   - 服务器类型（Apache/Nginx）
4. **截图**（如果适用）
5. **错误日志**

### 提出功能建议

创建一个 Feature Request Issue：

1. **问题描述**：这个功能解决什么问题？
2. **建议方案**：你期望的实现方式
3. **替代方案**：其他可能的实现方式
4. **附加信息**：相关截图、参考资料

### 提交代码

#### 开发流程

1. **Fork 仓库**
   ```bash
   # 点击 GitHub 页面右上角的 Fork 按钮
   ```

2. **克隆到本地**
   ```bash
   git clone https://github.com/your-username/ImageGallery.git
   cd ImageGallery
   ```

3. **创建特性分支**
   ```bash
   git checkout -b feature/amazing-feature
   # 或
   git checkout -b fix/bug-description
   ```

4. **进行更改**
   - 遵循代码风格指南
   - 添加必要的注释
   - 更新相关文档

5. **测试**
   - 确保所有功能正常工作
   - 测试不同环境和浏览器

6. **提交更改**
   ```bash
   git add .
   git commit -m "feat: add amazing feature"
   ```

7. **推送到 Fork**
   ```bash
   git push origin feature/amazing-feature
   ```

8. **创建 Pull Request**
   - 访问你的 Fork 仓库
   - 点击 "New Pull Request"
   - 填写详细的 PR 描述

#### Commit 规范

使用 [Conventional Commits](https://www.conventionalcommits.org/) 格式：

```
<type>(<scope>): <subject>

<body>

<footer>
```

**类型（Type）：**
- `feat`: 新功能
- `fix`: Bug 修复
- `docs`: 文档更新
- `style`: 代码格式（不影响功能）
- `refactor`: 重构
- `perf`: 性能优化
- `test`: 测试相关
- `chore`: 构建/工具链相关

**示例：**
```bash
feat(backup): add backup restore functionality

- Implement restore_backup() function
- Add restore modal UI
- Add delete backup feature

Closes #42
```

## 代码风格

### PHP 代码规范

遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 编码规范：

```php
<?php

namespace App\Services;

class ImageService
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function process(string $imagePath): bool
    {
        // 方法实现
        return true;
    }
}
```

**关键点：**
- 缩进使用 4 个空格
- 左花括号 `{` 在同一行
- 类、方法、变量使用驼峰命名
- 常量全大写，用下划线分隔

### JavaScript 代码规范

```javascript
// 使用驼峰命名
function handleImageClick(imageId) {
    const imageElement = document.getElementById(imageId);
    
    if (!imageElement) {
        return;
    }
    
    // 处理逻辑
}
```

### CSS 代码规范

```css
/* 使用短横线命名 */
.image-viewer {
    display: flex;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-viewer.show {
    opacity: 1;
}
```

## 文档贡献

改进文档同样重要：

- 修正拼写/语法错误
- 补充缺失的文档
- 改进示例代码
- 翻译成其他语言

## 测试指南

提交 PR 前请确保：

- [ ] 基础功能正常工作
- [ ] 在不同浏览器测试（Chrome/Firefox/Safari）
- [ ] 移动端响应式正常
- [ ] 无 PHP 错误/警告
- [ ] 无 JavaScript 控制台错误

## Pull Request 检查清单

- [ ] 代码遵循项目风格指南
- [ ] 自查代码变更，确保无不必要的修改
- [ ] 添加必要的注释
- [ ] 更新相关文档
- [ ] 测试通过
- [ ] Commit 信息清晰规范
- [ ] PR 描述详细完整

## 获取帮助

有问题？可以：

- 📖 查看 [Wiki 文档](https://github.com/yutaiseo/ImageGallery/wiki)
- 💬 在 [Discussions](https://github.com/yutaiseo/ImageGallery/discussions) 提问
- 🐛 在 [Issues](https://github.com/yutaiseo/ImageGallery/issues) 搜索类似问题

## 贡献者名单

感谢所有贡献者的付出！

<!-- 贡献者列表将自动生成 -->

---

再次感谢你的贡献！ 🎉
