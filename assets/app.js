// Global behaviour: theme toggle, mobile menu, animated counters
(function () {
  var root = document.documentElement;
  var themeBtn = document.getElementById('themeBtn');
  if (themeBtn) {
    themeBtn.addEventListener('click', function () {
      var light = root.classList.toggle('light');
      try { localStorage.setItem('quiz-theme', light ? 'light' : 'dark'); } catch (e) {}
    });
  }

  var burger = document.getElementById('burger');
  var links = document.getElementById('navlinks');
  if (burger && links) {
    burger.addEventListener('click', function () { links.classList.toggle('open'); });
  }

  document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseInt(el.dataset.count, 10) || 0;
    if (target === 0) { el.textContent = '0'; return; }
    var cur = 0;
    var step = Math.max(1, Math.ceil(target / 40));
    var t = setInterval(function () {
      cur = Math.min(target, cur + step);
      el.textContent = cur;
      if (cur >= target) clearInterval(t);
    }, 30);
  });

  // Animate horizontal bars on the admin dashboard
  document.querySelectorAll('.hbar .bar i').forEach(function (b) {
    var w = b.style.width; b.style.width = '0';
    setTimeout(function () { b.style.width = w; }, 100);
  });
})();
