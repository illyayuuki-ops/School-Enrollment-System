    </main>
</div>
<script>
(function () {
  var IDLE_LIMIT_MS = 10 * 60 * 1000;
  var PING_THROTTLE_MS = 60 * 1000;
  var lastActivity = Date.now();
  var lastPing = 0;

  function markActivity() {
    lastActivity = Date.now();
    var now = Date.now();
    if (now - lastPing >= PING_THROTTLE_MS) {
      lastPing = now;
      fetch('/session_ping.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) { if (data && data.ok === false && data.redirect) { window.location.href = data.redirect; } })
        .catch(function () {});
    }
  }

  ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
    document.addEventListener(evt, markActivity, { passive: true });
  });

  setInterval(function () {
    if (Date.now() - lastActivity >= IDLE_LIMIT_MS) {
      window.location.href = '/logout.php';
    }
  }, 15 * 1000);
})();
</script>
</body>
</html>