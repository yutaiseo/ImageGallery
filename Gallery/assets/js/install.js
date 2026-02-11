document.addEventListener('DOMContentLoaded', function () {
  var box = document.getElementById('box');
  if (!box) return;

  var installUrl = box.getAttribute('data-install-url') || 'install.php';
  var doneUrl = box.getAttribute('data-done-url') || (installUrl + '?done=1');
  var step = parseInt(box.getAttribute('data-step') || '0', 10);

  document.querySelectorAll('[data-reset="1"]').forEach(function (el) {
    el.addEventListener('click', function () {
      if (confirm('确定重置安装进度？')) {
        window.location.href = installUrl + '?reset=1';
      }
    });
  });

  var copyBtn = document.querySelector('[data-action="copy-pw"]');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var pwShow = document.getElementById('pwShow');
      if (!pwShow) return;
      navigator.clipboard.writeText(pwShow.innerText).then(function () {
        copyBtn.innerText = '已复制';
        setTimeout(function () { copyBtn.innerText = '复制'; }, 1000);
      });
    });
  }

  var refreshBtn = document.querySelector('[data-action="refresh-pw"]');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      fetch(installUrl + '?genpw=1')
        .then(function (resp) { return resp.text(); })
        .then(function (pw) {
          var pwShow = document.getElementById('pwShow');
          var pwInput = document.getElementById('admin_pass');
          var pwConfirm = document.getElementById('admin_pass_confirm');
          if (pwShow) pwShow.innerText = pw;
          if (pwInput) pwInput.value = pw;
          if (pwConfirm) pwConfirm.value = pw;
        });
    });
  }

  var pwInput = document.getElementById('admin_pass');
  if (pwInput) {
    pwInput.addEventListener('input', function () {
      var pwShow = document.getElementById('pwShow');
      if (pwShow) pwShow.innerText = pwInput.value || '';
    });
  }

  document.querySelectorAll('[data-action="copy-final"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy-text') || '';
      if (!text) return;
      navigator.clipboard.writeText(text);
    });
  });

  if (step === 5) {
    var bar = document.getElementById('bar');
    var installList = document.getElementById('install-list');
    if (!bar || !installList) return;

    fetch(installUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'do_install=1'
    })
      .then(function (res) { return res.json(); })
      .then(function (res) {
        var total = res.length;
        var allOk = res.every(function (r) { return r.ok; });
        res.forEach(function (r, i) {
          var li = document.createElement('li');
          li.innerHTML = r.msg + '：' + (r.ok ? '<span class="ok">✔ 成功</span>' : '<span class="fail">✘ 失败</span>' + (r.err ? (' (' + r.err + ')') : ''));
          installList.appendChild(li);
          bar.style.width = Math.round((i + 1) * 100 / total) + '%';
        });
        if (allOk) {
          setTimeout(function () {
            window.location.href = doneUrl;
          }, 1300);
        }
      })
      .catch(function (e) {
        installList.innerHTML = '<li class="fail">❌ 安装失败：' + e + '</li>';
      });
  }
});
