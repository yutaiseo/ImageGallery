<?php
// footer.php - 公共页脚组件
require_once __DIR__ . '/cdn_assets.php';

$includeClockScript = $includeClockScript ?? true;
$includeToastScript = $includeToastScript ?? true;
$includeUploadScripts = $includeUploadScripts ?? true;
$includeGalleryScripts = $includeGalleryScripts ?? true;
$extraScripts = $extraScripts ?? [];
$footerContentHtml = $footerContentHtml ?? '';
$showFooterMeta = $showFooterMeta ?? true;

$siteSettings = $siteSettings ?? [];
if ($showFooterMeta && empty($siteSettings) && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    try {
        $stmt = $GLOBALS['pdo']->query("SELECT setting_key, setting_value FROM site_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $siteSettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        $siteSettings = [];
    }
}

$icpNumber = trim((string)($siteSettings['icp_number'] ?? ''));
$icpLink = trim((string)($siteSettings['icp_link'] ?? ''));
$securityNumber = trim((string)($siteSettings['security_number'] ?? ''));
$securityLink = trim((string)($siteSettings['security_link'] ?? ''));
?>
    <footer class="footer mt-auto py-3 bg-dark">
        <?php if (!empty($footerContentHtml)): ?>
            <?php echo $footerContentHtml; ?>
        <?php else: ?>
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span class="text-white">
                            <?php 
                            // 从APP_VERSION常量获取（如果已加载config），否则从VERSION文件读取
                            if (defined('APP_VERSION')) {
                                $version = APP_VERSION;
                            } else {
                                $versionFile = __DIR__ . '/../VERSION';
                                if (file_exists($versionFile)) {
                                    $lines = array_map('trim', file($versionFile));
                                    $version = $lines[0] ?? 'unknown';
                                } else {
                                    $version = 'unknown';
                                }
                            }
                            $year = date("Y");
                            echo "图片管理系统 {$version} &copy; {$year}";
                            ?>
                        </span>
                        <?php if ($showFooterMeta && ($icpNumber !== '' || $securityNumber !== '')): ?>
                            <span class="text-white ms-2">
                                <?php if ($icpNumber !== ''): ?>
                                    <?php if ($icpLink !== ''): ?>
                                        <a class="text-white" href="<?php echo htmlspecialchars($icpLink); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($icpNumber); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($icpNumber); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($icpNumber !== '' && $securityNumber !== ''): ?>
                                    <span class="mx-1">|</span>
                                <?php endif; ?>
                                <?php if ($securityNumber !== ''): ?>
                                    <?php if ($securityLink !== ''): ?>
                                        <a class="text-white" href="<?php echo htmlspecialchars($securityLink); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($securityNumber); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($securityNumber); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-white" id="realTimeClock"></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </footer>
    
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
</div>
<?php ob_end_flush(); ?>  <!-- 立即返回框架给客户端 -->
<!-- CDN 智能选择：客户端异步测试 -->
<script>
(function() {
  // 后台异步检测各 CDN 的响应速度
  var cdnUrls = {
    'bootcdn': 'https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css',
    'staticfile': 'https://cdn.staticfile.net/bootstrap/5.3.3/css/bootstrap.min.css',
    'cdnjs': 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css',
    'jsdelivr': 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
  };
  
  function testCdnSpeed(name, url) {
    return new Promise(function(resolve) {
      var start = performance.now();
      var img = new Image();
      img.onload = function() {
        var latency = performance.now() - start;
        resolve({ name: name, latency: latency });
      };
      img.onerror = function() {
        resolve({ name: name, latency: 9999 });
      };
      img.src = url + '?t=' + Date.now();  // 添加随机串防缓存
      setTimeout(function() {
        img.src = '';  // 超时则取消
      }, 2000);
    });
  }
  
  // 延迟 500ms 后台测试（不阻塞首屏）
  setTimeout(function() {
    Promise.all(Object.entries(cdnUrls).map(function(entry) {
      return testCdnSpeed(entry[0], entry[1]);
    })).then(function(results) {
      var fastest = results.reduce(function(a, b) {
        return a.latency < b.latency ? a : b;
      });
      
      // 保存最快的 CDN 到服务器
      if (fastest.latency < 5000) {
        fetch('_set_cdn_preference.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cdn: fastest.name })
        }).catch(function() {});  // 失败无所谓
      }
    });
  }, 500);
})();
</script>
    <?php render_cdn_js(['bootstrap_js', 'hammer_js']); ?>
    <?php if ($includeClockScript): ?>
        <script defer src="assets/js/clock.js?v=<?php echo filemtime(__DIR__ . '/assets/js/clock.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($includeToastScript): ?>
        <script defer src="assets/js/toast.js?v=<?php echo filemtime(__DIR__ . '/assets/js/toast.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($includeUploadScripts): ?>
        <script defer src="assets/js/upload.js?v=<?php echo filemtime(__DIR__ . '/assets/js/upload.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($includeGalleryScripts): ?>
        <script defer src="assets/js/gallery.js?v=<?php echo filemtime(__DIR__ . '/assets/js/gallery.js'); ?>"></script>
    <?php endif; ?>
    <?php foreach ($extraScripts as $scriptSrc): ?>
        <script defer src="<?php echo htmlspecialchars($scriptSrc); ?>"></script>
    <?php endforeach; ?>

</body>
</html>
<?php 
// 什么都不做
?>