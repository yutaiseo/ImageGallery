document.addEventListener('DOMContentLoaded', function () {
  var chartEl = document.getElementById('trafficChart');
  if (chartEl && window.Chart) {
    var ctx = chartEl.getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
        datasets: [{
          label: '访问量',
          data: [1280, 1520, 1453, 1720, 1850, 1680, 1920],
          backgroundColor: 'rgba(59, 125, 221, 0.2)',
          borderColor: '#3b7ddd',
          tension: 0.3,
          borderWidth: 2,
          pointBackgroundColor: '#fff',
          pointBorderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true, grid: { drawBorder: false } },
          x: { grid: { display: false } }
        }
      }
    });
  }

  document.querySelectorAll('.admin-delete-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-image-id');
      if (!id) return;
      if (confirm('确定要删除这张图片吗？')) {
        window.location = 'gadmin/delete_image.php?id=' + id;
      }
    });
  });

  var editModalEl = document.getElementById('editModal');
  var editModalInstance = null;
  document.querySelectorAll('.admin-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!editModalEl) return;
      var imageId = btn.getAttribute('data-image-id');
      var imageTitle = btn.getAttribute('data-image-title');
      var imageDescription = btn.getAttribute('data-image-description');
      var imageUrl = btn.getAttribute('data-image-url');

      var idInput = document.getElementById('editImageId');
      var titleInput = document.getElementById('editImageTitle');
      var descInput = document.getElementById('editImageDescription');
      var preview = document.getElementById('editImagePreview');

      if (idInput) idInput.value = imageId || '';
      if (titleInput) titleInput.value = imageTitle || '';
      if (descInput) descInput.value = imageDescription || '';
      if (preview && imageUrl) preview.src = imageUrl + '?t=' + new Date().getTime();

      if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(editModalEl);
      }
      editModalInstance.show();
    });
  });

  var uploadType = document.getElementById('adminUploadType');
  if (!uploadType) return;

  var remoteField = document.getElementById('adminRemoteField');
  var localField = document.getElementById('adminLocalField');
  var fileDropArea = document.querySelector('#adminLocalField .file-drop-area');
  var fileInput = document.getElementById('adminImageInput');
  var fileNameDisplay = document.getElementById('adminFileName');
  var submitBtn = document.getElementById('adminSubmitBtn');
  var form = document.getElementById('adminAddImageForm');

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
      var remoteUrl = document.querySelector('#adminRemoteField input[name="remote_url"]').value;
      if (!validateRemoteImage(remoteUrl)) {
        e.preventDefault();
        var field = document.querySelector('#adminRemoteField input[name="remote_url"]');
        field.classList.add('is-invalid');
        return;
      }
    }

    if (uploadType.value === 'local') {
      if (!fileInput.files.length) {
        e.preventDefault();
        fileInput.classList.add('is-invalid');
        return;
      }
      if (!validateLocalFile(fileInput.files[0])) {
        e.preventDefault();
        return;
      }
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>上传中...';
  });

  function updateUploadFields() {
    if (uploadType.value === 'remote') {
      remoteField.classList.remove('d-none');
      localField.classList.add('d-none');
      fileInput.required = false;
      fileInput.value = '';
      fileNameDisplay.textContent = '';
      document.querySelector('#adminRemoteField input[name="remote_url"]').required = true;
    } else if (uploadType.value === 'local') {
      remoteField.classList.add('d-none');
      localField.classList.remove('d-none');
      document.querySelector('#adminRemoteField input[name="remote_url"]').required = false;
      document.querySelector('#adminRemoteField input[name="remote_url"]').value = '';
      fileInput.required = true;
    } else {
      remoteField.classList.add('d-none');
      localField.classList.add('d-none');
      document.querySelector('#adminRemoteField input[name="remote_url"]').required = false;
      fileInput.required = false;
    }
    form.classList.remove('was-validated');
  }

  function handleFileSelect() {
    var files = fileInput.files;
    if (!files.length) return;
    var file = files[0];
    if (!validateLocalFile(file)) return;
    fileNameDisplay.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
    fileInput.classList.remove('is-invalid');
  }

  function validateRemoteImage(url) {
    return /\.(jpeg|jpg|gif|png|webp)$/i.test(url) && url.startsWith('http');
  }

  function validateLocalFile(file) {
    if (!file) return false;
    var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    var maxSize = 6 * 1024 * 1024;
    if (validTypes.indexOf(file.type) === -1) {
      fileInput.classList.add('is-invalid');
      return false;
    }
    if (file.size > maxSize) {
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
