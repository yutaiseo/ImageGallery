document.addEventListener('DOMContentLoaded', function () {
  var editModal = document.getElementById('editModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      if (!button) return;
      document.getElementById('editImageId').value = button.getAttribute('data-image-id') || '';
      document.getElementById('editImageTitle').value = button.getAttribute('data-image-title') || '';
      document.getElementById('editImageDescription').value = button.getAttribute('data-image-description') || '';
      var preview = document.getElementById('editImagePreview');
      if (preview) preview.src = button.getAttribute('data-image-url') || '';
    });
  }

  document.querySelectorAll('.delete-image-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-image-id');
      if (!id) return;
      if (confirm('确定要删除这张图片吗？')) {
        window.location = 'delete_image.php?id=' + id;
      }
    });
  });

  document.querySelectorAll('.image-container[data-bg]').forEach(function (el) {
    var url = el.getAttribute('data-bg');
    if (url) {
      el.style.backgroundImage = "url('" + url + "')";
    }
  });
});
