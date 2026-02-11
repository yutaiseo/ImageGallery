<?php
require_once __DIR__ . '/bootstrap.php';
require_admin();

$page_title = '图片上传';
include __DIR__ . '/header.php';

// 处理上传表单提交
$upload_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $upload_result = '<div class="alert alert-danger">CSRF 验证失败</div>';
    } else {
        $file = $_FILES['image'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        $imageInfo = @getimagesize($file['tmp_name']);
        $validTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        if ($imageInfo === false) {
            $upload_result = '<div class="alert alert-danger">❌ 不是有效的图片文件</div>';
        } elseif (!isset($validTypes[$imageInfo['mime']])) {
            $upload_result = '<div class="alert alert-danger">❌ 仅支持 JPEG、PNG、GIF、WebP 格式</div>';
        } elseif ($file['size'] > $maxSize) {
            $upload_result = '<div class="alert alert-danger">❌ 文件大小超过 2MB 限制</div>';
        } else {
            // 验证通过，保存文件
            $extension = $validTypes[$imageInfo['mime']];
            $fileName = bin2hex(random_bytes(8)) . '.' . $extension;
            $targetPath = upload_storage_path($fileName);

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // 获取图片标题
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($title)) {
                    $title = pathinfo($file['name'], PATHINFO_FILENAME);
                }

                try {
                    // 插入数据库记录
                    $stmt = $pdo->prepare(
                        'INSERT INTO images (title, description, file_path, uploaded_by, created_at) 
                         VALUES (?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([
                        $title,
                        $description,
                        'uploads/' . $fileName,
                        $_SESSION['username'] ?? 'admin'
                    ]);

                    log_action($pdo, $_SESSION['username'] ?? 'admin', 'upload_image', 'uploaded image: ' . $title);
                    $upload_result = '<div class="alert alert-success">✅ 上传成功！图片已保存为：<strong>' . htmlspecialchars($title) . '</strong></div>';
                } catch (PDOException $e) {
                    @unlink($targetPath);
                    $upload_result = '<div class="alert alert-danger">❌ 保存到数据库失败，已删除上传文件</div>';
                    error_log('Upload DB error: ' . $e->getMessage());
                }
            } else {
                $upload_result = '<div class="alert alert-danger">❌ 文件保存失败，请检查目录权限</div>';
            }
        }
    }
}
?>

<div class="mt-3 admin-card">
  <h3 class="admin-card-title"><i class="fas fa-cloud-upload-alt"></i>上传图片</h3>

  <?php echo $upload_result; ?>

  <form method="post" enctype="multipart/form-data" id="uploadForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">图片标题 <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="输入图片标题（缺省时使用文件名）" id="imageTitle">
        </div>

        <div class="mb-3">
          <label class="form-label">图片描述</label>
          <textarea name="description" class="form-control" rows="4" placeholder="输入图片描述（可选）" id="imageDescription"></textarea>
        </div>

        <div class="mb-3">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload"></i> 上传图片
          </button>
        </div>
      </div>

      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">选择图片文件 <span class="text-danger">*</span></label>
          <div class="file-drop-area border border-2 border-dashed rounded p-4 text-center" id="dropArea">
            <div>
              <i class="fas fa-image" style="font-size: 2.5rem; color: #aaa;"></i>
              <p class="mt-2 text-muted">
                <strong>点击选择</strong> 或拖放图片文件到此处
              </p>
              <small class="text-muted d-block">支持格式：JPEG、PNG、GIF、WebP</small>
              <small class="text-muted d-block">最大文件大小：2MB</small>
            </div>
            <input type="file" name="image" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;" required>
          </div>
        </div>

        <div class="mb-3" id="previewContainer" style="display: none;">
          <label class="form-label">预览</label>
          <img id="imagePreview" src="" alt="预览" class="img-thumbnail" style="max-width: 100%; max-height: 300px; object-fit: contain;">
          <p id="fileName" class="text-muted small mt-2"></p>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('imageInput');
  const imagePreview = document.getElementById('imagePreview');
  const imageTitle = document.getElementById('imageTitle');
  const fileName = document.getElementById('fileName');
  const previewContainer = document.getElementById('previewContainer');

  // 点击触发文件选择
  dropArea.addEventListener('click', () => fileInput.click());

  // 拖放处理
  dropArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropArea.style.borderColor = '#0d6efd';
    dropArea.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
  });

  dropArea.addEventListener('dragleave', () => {
    dropArea.style.borderColor = '#dc3545';
    dropArea.style.backgroundColor = 'transparent';
  });

  dropArea.addEventListener('drop', (e) => {
    e.preventDefault();
    e.stopPropagation();
    dropArea.style.borderColor = '#dc3545';
    dropArea.style.backgroundColor = 'transparent';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      fileInput.files = files;
      handleFileSelect();
    }
  });

  // 文件选择处理
  fileInput.addEventListener('change', handleFileSelect);

  function handleFileSelect() {
    const file = fileInput.files[0];
    if (!file) return;

    // 验证文件类型
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
      alert('仅支持 JPEG、PNG、GIF、WebP 格式');
      fileInput.value = '';
      return;
    }

    // 验证文件大小
    if (file.size > 2 * 1024 * 1024) {
      alert('文件大小不能超过 2MB');
      fileInput.value = '';
      return;
    }

    // 显示预览
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.src = e.target.result;
      fileName.textContent = '文件名：' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
      previewContainer.style.display = 'block';
      
      // 如果标题为空，自动填入文件名
      if (!imageTitle.value) {
        imageTitle.value = file.name.split('.')[0];
      }
    };
    reader.readAsDataURL(file);
  }
});
</script>

<?php include __DIR__ . '/footer.php';
