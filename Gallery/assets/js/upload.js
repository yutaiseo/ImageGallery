document.addEventListener('DOMContentLoaded', function () {
  var uploadType = document.getElementById('uploadType');
  var remoteField = document.getElementById('remoteField');
  var localField = document.getElementById('localField');
  var fileDropArea = document.querySelector('.file-drop-area');
  var fileInput = document.getElementById('imageInput');
  var fileNameDisplay = document.getElementById('fileName');
  var submitBtn = document.getElementById('submitBtn');
  var form = document.getElementById('addImageForm');

  if (!uploadType || !remoteField || !localField || !fileDropArea || !fileInput || !fileNameDisplay || !submitBtn || !form) {
    return;
  }

  updateUploadFields();

  uploadType.addEventListener('change', updateUploadFields);
  fileInput.addEventListener('change', handleFileSelect);

  fileDropArea.addEventListener('dragover', function (e) {
    e.preventDefault();
    fileDropArea.classList.add('is-dragover');
  });

  fileDropArea.addEventListener('dragleave', function () {
    fileDropArea.classList.remove('is-dragover');
  });

  fileDropArea.addEventListener('drop', function (e) {
    e.preventDefault();
    fileDropArea.classList.remove('is-dragover');

    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      handleFileSelect();
    }
  });

  fileDropArea.querySelector('span').addEventListener('click', function (e) {
    e.preventDefault();
    fileInput.click();
  });

  form.addEventListener('submit', function (e) {
    form.classList.remove('was-validated');

    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
      form.classList.add('was-validated');
      return;
    }

    if (uploadType.value === 'remote') {
      var remoteUrl = document.querySelector('input[name="remote_url"]').value;
      if (!validateRemoteImage(remoteUrl)) {
        e.preventDefault();
        document.querySelector('input[name="remote_url"]').classList.add('is-invalid');
        document.querySelector('input[name="remote_url"]').nextElementSibling.textContent =
          '请输入有效的图片URL (支持jpg, png, gif格式)';
        return;
      }
    }

    if (uploadType.value === 'local') {
      if (!fileInput.files.length) {
        e.preventDefault();
        fileInput.classList.add('is-invalid');
        fileInput.nextElementSibling.textContent = '请选择图片文件';
        return;
      }

      var file = fileInput.files[0];
      if (!validateLocalFile(file)) {
        e.preventDefault();
        fileInput.classList.add('is-invalid');
        return;
      }
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>上传中...';
  });

  function updateUploadFields() {
    var remoteInput = document.querySelector('input[name="remote_url"]');
    if (uploadType.value === 'remote') {
      remoteField.classList.remove('d-none');
      localField.classList.add('d-none');
      fileInput.required = false;
      fileInput.value = '';
      fileNameDisplay.textContent = '';
      remoteInput.required = true;
    } else if (uploadType.value === 'local') {
      remoteField.classList.add('d-none');
      localField.classList.remove('d-none');
      remoteInput.required = false;
      remoteInput.value = '';
      fileInput.required = true;
    } else {
      remoteField.classList.add('d-none');
      localField.classList.add('d-none');
      remoteInput.required = false;
      fileInput.required = false;
    }

    form.classList.remove('was-validated');
  }

  function handleFileSelect() {
    var files = fileInput.files;
    if (!files.length) return;

    var file = files[0];
    if (!validateLocalFile(file)) {
      return;
    }

    fileNameDisplay.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
    fileInput.classList.remove('is-invalid');
  }

  function validateRemoteImage(url) {
    var imagePattern = /\.(jpeg|jpg|gif|png)$/i;
    return imagePattern.test(url) && url.startsWith('http');
  }

  function validateLocalFile(file) {
    if (!file) return false;

    var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    var maxSize = 6 * 1024 * 1024;

    if (validTypes.indexOf(file.type) === -1) {
      fileInput.nextElementSibling.textContent = '不支持的文件类型，请选择图片文件 (JPG, PNG, GIF, WebP)';
      fileInput.classList.add('is-invalid');
      return false;
    }

    if (file.size > maxSize) {
      fileInput.nextElementSibling.textContent = '文件太大 (' + formatFileSize(file.size) + '), 最大允许6MB';
      fileInput.classList.add('is-invalid');
      return false;
    }

    return true;
  }

  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' bytes';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }
});
