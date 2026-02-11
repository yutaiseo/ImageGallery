(function () {
  function ensureContainer() {
    var container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      document.body.appendChild(container);
    }
    return container;
  }

  function fallbackNotice(message, type) {
    var container = ensureContainer();
    var alertEl = document.createElement('div');
    var map = {
      success: 'success',
      danger: 'danger',
      warning: 'warning',
      info: 'info'
    };
    var tone = map[type] || 'secondary';

    alertEl.className = 'alert alert-' + tone + ' alert-dismissible fade show mb-2';
    alertEl.setAttribute('role', 'alert');
    alertEl.textContent = message;

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.addEventListener('click', function () {
      alertEl.remove();
    });

    alertEl.appendChild(closeBtn);
    container.appendChild(alertEl);

    setTimeout(function () {
      alertEl.remove();
    }, 4000);
  }

  window.showToast = function (message, type) {
    var container = ensureContainer();
    var text = String(message || '');
    var tone = type || 'info';

    if (!window.bootstrap || !window.bootstrap.Toast) {
      fallbackNotice(text, tone);
      return;
    }

    var toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-bg-' + tone + ' border-0';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    var body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = text;

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');
    closeBtn.setAttribute('aria-label', 'Close');

    var inner = document.createElement('div');
    inner.className = 'd-flex';
    inner.appendChild(body);
    inner.appendChild(closeBtn);

    toastEl.appendChild(inner);
    container.appendChild(toastEl);

    var toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', function () {
      toastEl.remove();
    });
  };

  window.alert = function (message) {
    window.showToast(String(message || ''), 'warning');
  };
})();
