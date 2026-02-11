document.addEventListener('DOMContentLoaded', function () {
  var clockEl = document.getElementById('realTimeClock');
  if (!clockEl) return;

  function updateClock() {
    var now = new Date();
    var options = {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    };
    clockEl.textContent = now.toLocaleString('zh-CN', options);
  }

  updateClock();
  setInterval(updateClock, 1000);
});
