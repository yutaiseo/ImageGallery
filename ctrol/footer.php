<?php
// gadmin/footer.php — 后台页脚
require_once __DIR__ . '/../Gallery/cdn_assets.php';
?>
    </main>
  </div>
</div>

<footer class="footer">
  <div class="container-fluid">
    <div class="footer-content">
      <div class="version-info">
        后台管理系统 v1.1.0 Stable
      </div>
      <div class="real-time-clock" id="currentTime"></div>
    </div>
  </div>
</footer>

</div><!-- 关闭 gadmin-body -->

<?php render_cdn_js(['bootstrap_js']); ?>
<!-- CDN 智能选择：客户端异步测试 -->
<script>
(function() {
  setTimeout(function() {
    var cdnUrls = {
      'bootcdn': 'https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css',
      'staticfile': 'https://cdn.staticfile.net/bootstrap/5.3.3/css/bootstrap.min.css'
    };
    
    Promise.all(Object.entries(cdnUrls).map(function(entry) {
      return new Promise(function(resolve) {
        var start = performance.now(), img = new Image();
        img.onload = function() { resolve({ name: entry[0], latency: performance.now() - start }); };
        img.onerror = function() { resolve({ name: entry[0], latency: 9999 }); };
        img.src = entry[1] + '?t=' + Date.now();
        setTimeout(function() { img.src = ''; }, 2000);
      });
    })).then(function(results) {
      var fastest = results.reduce(function(a, b) { return a.latency < b.latency ? a : b; });
      if (fastest.latency < 5000) {
        fetch('/Gallery/_set_cdn_preference.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cdn: fastest.name })
        }).catch(function() {});
      }
    });
  }, 500);
})();
</script>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
<script src="../assets/js/clock.js?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/js/clock.js'); ?>"></script>
<script src="../assets/js/toast.js?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/js/toast.js'); ?>"></script>
<script src="../assets/js/gadmin.js?v=<?php echo filemtime(__DIR__ . '/../Gallery/assets/js/gadmin.js'); ?>"></script>
</body>
</html>