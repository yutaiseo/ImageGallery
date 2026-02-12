<?php
require_once __DIR__ . '/bootstrap.php';
require_admin_write();

$page_title = '图片上传';
include __DIR__ . '/header.php';

// 处理上传表单提交
$upload_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) {
    $upload_result = '<div class="alert alert-danger">CSRF 验证失败</div>';
  } else {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type'] ?? 'local';

    $filePath = '';
    $isRemote = 0;

    if ($type === 'remote') {
      $remoteUrl = trim($_POST['remote_url'] ?? '');
      if (empty($remoteUrl) || !preg_match('/\.(jpeg|jpg|gif|png|webp)$/i', $remoteUrl) || strpos($remoteUrl, 'http') !== 0) {
        $upload_result = '<div class="alert alert-danger">❌ 无效的远程图片URL</div>';
      } else {
        if ($title === '') {
          $path = parse_url($remoteUrl, PHP_URL_PATH);
          $title = $path ? pathinfo($path, PATHINFO_FILENAME) : 'remote-image';
        }
        $filePath = $remoteUrl;
        $isRemote = 1;
      }
    } else {
      if (empty($_FILES['images'])) {
        $upload_result = '<div class="alert alert-danger">❌ 本地上传失败</div>';
      } else {
        $files = $_FILES['images'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $validTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

        $count = is_array($files['name']) ? count($files['name']) : 0;
        $successCount = 0;
        $failCount = 0;
        $messages = [];

        for ($i = 0; $i < $count; $i++) {
          if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $failCount++;
            $messages[] = htmlspecialchars($files['name'][$i]) . '：上传失败';
            continue;
          }

          $tmpName = $files['tmp_name'][$i];
          $fileNameOriginal = $files['name'][$i];
          $fileSize = $files['size'][$i];

          $imageInfo = @getimagesize($tmpName);
          if ($imageInfo === false || !isset($validTypes[$imageInfo['mime']])) {
            $failCount++;
            $messages[] = htmlspecialchars($fileNameOriginal) . '：格式无效';
            continue;
          }
          if ($fileSize > $maxSize) {
            $failCount++;
            $messages[] = htmlspecialchars($fileNameOriginal) . '：超过 2MB 限制';
            continue;
          }

          $extension = $validTypes[$imageInfo['mime']];
          $storedName = bin2hex(random_bytes(8)) . '.' . $extension;
          $targetPath = upload_storage_path($storedName);

          if (!move_uploaded_file($tmpName, $targetPath)) {
            $failCount++;
            $messages[] = htmlspecialchars($fileNameOriginal) . '：保存失败';
            continue;
          }

          $imageTitle = $title;
          if ($imageTitle === '') {
            $imageTitle = pathinfo($fileNameOriginal, PATHINFO_FILENAME);
          } elseif ($count > 1) {
            $imageTitle = $title . '-' . ($i + 1);
          }

          $filePath = 'uploads/' . $storedName;
          try {
            $stmt = $pdo->prepare(
              'INSERT INTO images (title, description, file_path, is_remote, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$imageTitle, $description, $filePath, 0]);
            log_action($pdo, $_SESSION['username'] ?? 'admin', 'upload_image', 'uploaded image: ' . $imageTitle);
            $successCount++;
          } catch (PDOException $e) {
            @unlink($targetPath);
            $failCount++;
            $messages[] = htmlspecialchars($fileNameOriginal) . '：保存到数据库失败';
            error_log('Upload DB error: ' . $e->getMessage());
          }
        }

        if ($successCount > 0) {
          $upload_result = '<div class="alert alert-success">✅ 成功上传 ' . $successCount . ' 张图片。</div>';
        }
        if ($failCount > 0) {
          $upload_result .= '<div class="alert alert-warning">部分文件失败：<ul><li>' . implode('</li><li>', $messages) . '</li></ul></div>';
        }
      }
    }

    if ($type === 'remote' && $filePath !== '' && $upload_result === '') {
      try {
        $stmt = $pdo->prepare(
          'INSERT INTO images (title, description, file_path, is_remote, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$title, $description, $filePath, $isRemote]);
        log_action($pdo, $_SESSION['username'] ?? 'admin', 'upload_image', 'uploaded image: ' . $title);
        $upload_result = '<div class="alert alert-success">✅ 上传成功！图片已保存为：<strong>' . htmlspecialchars($title) . '</strong></div>';
      } catch (PDOException $e) {
        $upload_result = '<div class="alert alert-danger">❌ 保存到数据库失败</div>';
        error_log('Upload DB error: ' . $e->getMessage());
      }
    }
  }
}
?>

<div class="mt-3 admin-card">
  <h3 class="admin-card-title"><i class="fas fa-cloud-upload-alt"></i>上传图片</h3>

  <?php echo $upload_result; ?>

  <form method="post" enctype="multipart/form-data" id="adminAddImageForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

    <div class="row">
      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">图片标题</label>
          <input type="text" name="title" class="form-control" placeholder="单图：标题；多图：作为前缀" id="imageTitle">
        </div>

        <div class="mb-3">
          <label class="form-label">图片描述</label>
          <textarea name="description" class="form-control" rows="4" placeholder="输入图片描述（可选）" id="imageDescription"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">上传类型 <span class="text-danger">*</span></label>
          <select class="form-select" id="adminUploadType" name="type" required>
            <option value="">请选择类型</option>
            <option value="local" selected>本地上传</option>
            <option value="remote">远程链接</option>
          </select>
        </div>

        <div class="mb-3 d-none" id="adminRemoteField">
          <label class="form-label">远程图片链接 <span class="text-danger">*</span></label>
          <input type="url" name="remote_url" class="form-control" placeholder="https://example.com/image.jpg">
          <small class="text-muted">支持 JPG/PNG/GIF/WebP</small>
        </div>

        <div class="mb-3">
          <button type="submit" class="btn btn-primary" id="adminSubmitBtn">
            <i class="fas fa-upload"></i> 上传图片
          </button>
        </div>
      </div>

      <div class="col-md-6">
        <div class="mb-3" id="adminLocalField">
          <label class="form-label">选择图片文件 <span class="text-danger">*</span></label>
          <div class="file-drop-area border border-2 border-dashed rounded p-4 text-center" id="dropArea">
            <div>
              <i class="fas fa-image" style="font-size: 2.5rem; color: #aaa;"></i>
              <p class="mt-2 text-muted">
                <strong>点击选择</strong> 或拖放图片文件到此处（支持多选）
              </p>
              <small class="text-muted d-block">支持格式：JPEG、PNG、GIF、WebP</small>
              <small class="text-muted d-block">最大文件大小：2MB</small>
            </div>
            <input type="file" name="images[]" id="adminImageInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display: none;" required>
          </div>
        </div>

        <div class="mb-3" id="previewContainer" style="display: none;">
          <label class="form-label">预览</label>
          <img id="imagePreview" src="" alt="预览" class="img-thumbnail" style="max-width: 100%; max-height: 300px; object-fit: contain;">
          <p id="adminFileName" class="text-muted small mt-2"></p>
          <ul id="adminFileList" class="small text-muted mt-2 mb-0"></ul>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const uploadType = document.getElementById('adminUploadType');
  const remoteField = document.getElementById('adminRemoteField');
  const localField = document.getElementById('adminLocalField');
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('adminImageInput');
  const imagePreview = document.getElementById('imagePreview');
  const imageTitle = document.getElementById('imageTitle');
  const fileName = document.getElementById('adminFileName');
  const previewContainer = document.getElementById('previewContainer');
  const fileList = document.getElementById('adminFileList');
  const form = document.getElementById('adminAddImageForm');

  function updateUploadFields() {
    const remoteInput = document.querySelector('input[name="remote_url"]');
    if (uploadType.value === 'remote') {
      remoteField.classList.remove('d-none');
      localField.classList.add('d-none');
      fileInput.required = false;
      fileInput.value = '';
      if (remoteInput) {
        remoteInput.required = true;
        remoteInput.classList.remove('is-invalid');
      }
    } else if (uploadType.value === 'local') {
      remoteField.classList.add('d-none');
      localField.classList.remove('d-none');
      fileInput.required = true;
      if (remoteInput) {
        remoteInput.required = false;
        remoteInput.value = '';
        remoteInput.classList.remove('is-invalid');
      }
    } else {
      remoteField.classList.add('d-none');
      localField.classList.add('d-none');
      fileInput.required = false;
      if (remoteInput) {
        remoteInput.required = false;
        remoteInput.classList.remove('is-invalid');
      }
    }
  }

  if (uploadType) {
    updateUploadFields();
    uploadType.addEventListener('change', updateUploadFields);
  }

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
    const files = Array.from(fileInput.files || []);
    if (!files.length) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const maxSize = 2 * 1024 * 1024;

    for (const file of files) {
      if (!validTypes.includes(file.type)) {
        alert('仅支持 JPEG、PNG、GIF、WebP 格式');
        fileInput.value = '';
        return;
      }
      if (file.size > maxSize) {
        alert('文件大小不能超过 2MB');
        fileInput.value = '';
        return;
      }
    }

    const firstFile = files[0];
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.src = e.target.result;
      fileName.textContent = '已选择 ' + files.length + ' 个文件，首个：' + firstFile.name + ' (' + (firstFile.size / 1024).toFixed(2) + ' KB)';
      previewContainer.style.display = 'block';
      if (fileList) {
        fileList.innerHTML = '';
        files.forEach((f) => {
          const li = document.createElement('li');
          li.textContent = f.name + ' (' + (f.size / 1024).toFixed(2) + ' KB)';
          fileList.appendChild(li);
        });
      }

      if (!imageTitle.value && files.length === 1) {
        imageTitle.value = firstFile.name.split('.')[0];
      }
    };
    reader.readAsDataURL(firstFile);
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      if (uploadType && uploadType.value === 'remote') {
        const remoteInput = document.querySelector('input[name="remote_url"]');
        if (!remoteInput || !remoteInput.value || !/^https?:\/\/.+/i.test(remoteInput.value)) {
          e.preventDefault();
          remoteInput.classList.add('is-invalid');
        }
      }
    });
  }
});
</script>

<?php include __DIR__ . '/footer.php';
