document.addEventListener('DOMContentLoaded', function () {
  var selectAllImages = document.getElementById('selectAll');
  if (selectAllImages) {
    selectAllImages.addEventListener('change', function (e) {
      var checked = e.target.checked;
      document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
        cb.checked = checked;
      });
    });
  }

  var editModal = document.getElementById('editModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;
      var imageId = button.getAttribute('data-image-id');
      var imageTitle = button.getAttribute('data-image-title');
      var imageDescription = button.getAttribute('data-image-description');
      var imageUrl = button.getAttribute('data-image-url');

      var idInput = document.getElementById('editImageId');
      var titleInput = document.getElementById('editImageTitle');
      var descInput = document.getElementById('editImageDescription');
      var preview = document.getElementById('editImagePreview');

      if (idInput) idInput.value = imageId || '';
      if (titleInput) titleInput.value = imageTitle || '';
      if (descInput) descInput.value = imageDescription || '';
      if (preview && imageUrl) {
        preview.src = imageUrl + '?t=' + new Date().getTime();
      }
    });
  }

  var selectAllUsers = document.getElementById('selectAllUsers');
  if (selectAllUsers) {
    selectAllUsers.addEventListener('change', function (e) {
      var checked = e.target.checked;
      document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
        cb.checked = checked;
      });
    });
  }

  var recycleSelectAll = document.getElementById('recycleSelectAll');
  if (recycleSelectAll) {
    recycleSelectAll.addEventListener('change', function (e) {
      var checked = e.target.checked;
      document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
        cb.checked = checked;
      });
    });
  }

  var recycleForm = document.getElementById('recycleBatchForm');
  if (recycleForm) {
    recycleForm.addEventListener('submit', function (e) {
      var actionInput = recycleForm.querySelector('input[name="action"]');
      var act = actionInput ? actionInput.value : '';
      if (!act) {
        e.preventDefault();
        if (window.showToast) window.showToast('请选择批量操作', 'warning');
      }
    });
  }

  document.querySelectorAll('.js-confirm').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var message = btn.getAttribute('data-confirm') || '确认执行该操作？';
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  document.querySelectorAll('.js-form-action').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var formId = btn.getAttribute('data-form');
      var action = btn.getAttribute('data-action') || '';
      var id = btn.getAttribute('data-id') || '';
      var message = btn.getAttribute('data-confirm') || '确认执行该操作？';

      if (!formId || !action) return;
      if (message && !confirm(message)) {
        e.preventDefault();
        return;
      }

      var form = document.getElementById(formId);
      if (!form) return;
      var actionInput = form.querySelector('input[name="action"]');
      if (actionInput) {
        actionInput.value = action;
      }
      var idInput = form.querySelector('input[name="id"]');
      if (idInput) {
        idInput.value = id;
      }
      form.submit();
    });
  });

  document.querySelectorAll('.js-recycle-action').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var action = btn.getAttribute('data-action');
      var message = btn.getAttribute('data-confirm') || '确认执行该操作？';
      if (!action) return;
      var actionInput = recycleForm ? recycleForm.querySelector('input[name="action"]') : null;
      if (actionInput) {
        actionInput.value = action;
      }
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });
});
