// Exam engine: timer, question navigation, autosave, review marks, submit
(function () {
  var cfg = window.EXAM_CFG;
  if (!cfg) return;

  var form = document.getElementById('examForm');
  var questions = Array.prototype.slice.call(document.querySelectorAll('.q'));
  var navBtns = Array.prototype.slice.call(document.querySelectorAll('.nav-q'));
  var timerEl = document.getElementById('timer');
  var progressFill = document.getElementById('progressFill');
  var progressText = document.getElementById('progressText');
  var saveState = document.getElementById('saveState');
  var modal = document.getElementById('confirmModal');
  var confirmText = document.getElementById('confirmText');
  var toast = document.getElementById('toast');

  var current = 0;
  var submitting = false;
  var switches = 0;
  var pendingSaves = 0;
  var endAt = Date.now() + cfg.remaining * 1000;

  /* ---------- helpers ---------- */
  function isAnswered(i) { return !!questions[i].querySelector('input:checked'); }
  function answeredCount() { return questions.filter(function (_, i) { return isAnswered(i); }).length; }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  function showToast(msg) {
    toast.textContent = msg;
    toast.hidden = false;
    clearTimeout(showToast.t);
    showToast.t = setTimeout(function () { toast.hidden = true; }, 3500);
  }

  function updateUI() {
    navBtns.forEach(function (b, i) {
      b.classList.toggle('done', isAnswered(i));
      b.classList.toggle('current', i === current);
    });
    var n = answeredCount();
    progressText.textContent = n + ' / ' + questions.length;
    progressFill.style.width = (n / questions.length * 100) + '%';
  }

  function go(i) {
    if (i < 0 || i >= questions.length) return;
    questions[current].classList.remove('active');
    current = i;
    questions[current].classList.add('active');
    updateUI();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /* ---------- autosave ---------- */
  function save(qid, value) {
    pendingSaves++;
    saveState.textContent = 'Saving...';
    saveState.classList.remove('err');
    var body = new URLSearchParams();
    body.set('csrf', cfg.csrf);
    body.set('attempt', cfg.attemptId);
    body.set('question', qid);
    body.set('selected', value);
    fetch(cfg.saveUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true })
      .then(function (r) { if (!r.ok) throw new Error('save failed'); return r.json(); })
      .then(function () {
        pendingSaves--;
        if (pendingSaves === 0) saveState.textContent = 'All changes saved';
      })
      .catch(function () {
        pendingSaves--;
        saveState.textContent = 'Could not save. Answers will still be sent on submit.';
        saveState.classList.add('err');
      });
  }

  questions.forEach(function (q) {
    q.addEventListener('change', function (e) {
      if (e.target.type === 'radio') {
        save(q.dataset.qid, e.target.value);
        updateUI();
      }
    });
    q.addEventListener('click', function (e) {
      var act = e.target.getAttribute && e.target.getAttribute('data-act');
      if (!act) return;
      if (act === 'next') go(current + 1);
      if (act === 'prev') go(current - 1);
      if (act === 'clear') {
        var checked = q.querySelector('input:checked');
        if (checked) { checked.checked = false; save(q.dataset.qid, ''); updateUI(); }
      }
      if (act === 'mark') {
        var on = navBtns[current].classList.toggle('marked');
        showToast(on ? 'Marked for review' : 'Mark removed');
      }
    });
  });

  navBtns.forEach(function (b) {
    b.addEventListener('click', function () { go(parseInt(b.dataset.i, 10)); });
  });

  /* ---------- keyboard shortcuts ---------- */
  document.addEventListener('keydown', function (e) {
    if (modal && !modal.hidden) return;
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'textarea' || tag === 'select') return;
    if (e.key === 'ArrowRight') go(current + 1);
    else if (e.key === 'ArrowLeft') go(current - 1);
    else if (/^[a-dA-D]$/.test(e.key) || /^[1-4]$/.test(e.key)) {
      var idx = /^[1-4]$/.test(e.key) ? parseInt(e.key, 10) - 1 : e.key.toUpperCase().charCodeAt(0) - 65;
      var radio = questions[current].querySelectorAll('input[type=radio]')[idx];
      if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
    }
  });

  /* ---------- timer ---------- */
  function tick() {
    var left = Math.max(0, Math.ceil((endAt - Date.now()) / 1000));
    var h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
    timerEl.textContent = (h > 0 ? h + ':' : '') + pad(m) + ':' + pad(s);
    timerEl.classList.toggle('low', left <= 300 && left > 60);
    timerEl.classList.toggle('critical', left <= 60);
    if (left <= 0) {
      clearInterval(timerId);
      showToast("Time is up! Submitting your exam...");
      doSubmit();
    }
  }
  var timerId = setInterval(tick, 250);
  tick();

  /* ---------- submit ---------- */
  function doSubmit() {
    if (submitting) return;
    submitting = true;
    form.submit();
  }

  document.getElementById('finishBtn').addEventListener('click', function () {
    var unanswered = questions.length - answeredCount();
    var marked = document.querySelectorAll('.nav-q.marked').length;
    var msg = unanswered > 0
      ? 'You have ' + unanswered + ' unanswered question' + (unanswered > 1 ? 's' : '') + '.'
      : 'You answered every question.';
    if (marked) msg += ' ' + marked + ' marked for review.';
    confirmText.textContent = msg + ' You cannot change answers after submitting.';
    modal.hidden = false;
  });
  document.getElementById('cancelSubmit').addEventListener('click', function () { modal.hidden = true; });
  document.getElementById('confirmSubmit').addEventListener('click', doSubmit);

  /* ---------- safety nets ---------- */
  window.addEventListener('beforeunload', function (e) {
    if (!submitting) { e.preventDefault(); e.returnValue = ''; }
  });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && !submitting) {
      switches++;
      showToast('Tab switch detected (' + switches + '). Please stay on the exam page.');
    }
  });
  document.addEventListener('contextmenu', function (e) { e.preventDefault(); });

  updateUI();
})();
