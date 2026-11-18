<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/grader.php';
require_login();
$u = user();

$attemptId = (int) ($_GET['attempt'] ?? 0);

/* ===================== TAKING AN EXAM ===================== */
if ($attemptId) {
    $st = $pdo->prepare(
        'SELECT a.*, e.title, e.duration_minutes FROM attempts a
         JOIN exams e ON e.id = a.exam_id WHERE a.id = ? AND a.user_id = ?'
    );
    $st->execute([$attemptId, $u['id']]);
    $attempt = $st->fetch();

    if (!$attempt) {
        http_response_code(404);
        exit('Attempt not found.');
    }
    if ($attempt['status'] === 'submitted') {
        redirect('result.php?attempt=' . $attemptId);
    }

    $endTs     = strtotime($attempt['started_at']) + ((int) $attempt['duration_minutes']) * 60;
    $remaining = $endTs - time();

    if ($remaining <= 0) {
        finalize_attempt($pdo, $attemptId, [], false);
        flash('Time was up, so your exam was submitted automatically.', 'error');
        redirect('result.php?attempt=' . $attemptId);
    }

    // Same shuffled order for the whole attempt (seeded by attempt id)
    $qs = $pdo->query(
        'SELECT id, question, option_a, option_b, option_c, option_d, marks FROM questions
         WHERE exam_id = ' . (int) $attempt['exam_id'] . ' ORDER BY RAND(' . $attemptId . ')'
    )->fetchAll();

    $sv = $pdo->prepare('SELECT question_id, selected FROM answers WHERE attempt_id = ?');
    $sv->execute([$attemptId]);
    $saved = array_column($sv->fetchAll(), 'selected', 'question_id');

    $pageTitle    = $attempt['title'];
    $hideNav      = true;
    $bodyClass    = 'exam-page';
    $extraScripts = ['assets/exam.js'];
    require __DIR__ . '/includes/header.php';
    $total = count($qs);
    ?>
    <div class="exam-bar">
      <div class="exam-title"><?= e($attempt['title']) ?></div>
      <div class="exam-progress"><div class="bar"><i id="progressFill"></i></div><span id="progressText">0 / <?= $total ?></span></div>
      <div class="timer" id="timer" aria-live="off">--:--</div>
    </div>

    <form id="examForm" method="post" action="<?= url('submit.php') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="attempt" value="<?= $attemptId ?>">
      <div class="exam-layout">
        <main class="exam-main">
          <?php foreach ($qs as $i => $q): ?>
          <section class="q card<?= $i === 0 ? ' active' : '' ?>" data-qid="<?= (int) $q['id'] ?>">
            <div class="q-head">
              <span class="q-num">Question <?= $i + 1 ?> of <?= $total ?></span>
              <span class="chip"><?= (int) $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
            </div>
            <h2 class="q-text"><?= nl2br(e($q['question'])) ?></h2>
            <div class="opts">
              <?php foreach (['A', 'B', 'C', 'D'] as $L): ?>
              <label class="opt">
                <input type="radio" name="ans[<?= (int) $q['id'] ?>]" value="<?= $L ?>"<?= ($saved[$q['id']] ?? '') === $L ? ' checked' : '' ?>>
                <span class="key"><?= $L ?></span>
                <span class="txt"><?= e($q['option_' . strtolower($L)]) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="q-actions">
              <button type="button" class="btn ghost small" data-act="prev">&larr; Previous</button>
              <button type="button" class="btn ghost small" data-act="clear">Clear</button>
              <button type="button" class="btn ghost small" data-act="mark">&#9873; Mark for review</button>
              <button type="button" class="btn small" data-act="next">Next &rarr;</button>
            </div>
          </section>
          <?php endforeach; ?>
        </main>

        <aside class="exam-side card">
          <h3>Questions</h3>
          <div class="qnav" id="qnav">
            <?php foreach ($qs as $i => $q): ?>
              <button type="button" class="nav-q<?= isset($saved[$q['id']]) ? ' done' : '' ?><?= $i === 0 ? ' current' : '' ?>" data-i="<?= $i ?>"><?= $i + 1 ?></button>
            <?php endforeach; ?>
          </div>
          <ul class="legend">
            <li><i class="lg done"></i> Answered</li>
            <li><i class="lg"></i> Not answered</li>
            <li><i class="lg marked"></i> Marked for review</li>
          </ul>
          <div class="save-state" id="saveState">All changes saved</div>
          <button type="button" class="btn block" id="finishBtn">Finish &amp; submit</button>
        </aside>
      </div>
    </form>

    <div class="modal" id="confirmModal" hidden>
      <div class="modal-box card">
        <h2>Submit exam?</h2>
        <p id="confirmText"></p>
        <div class="modal-actions">
          <button type="button" class="btn ghost" id="cancelSubmit">Keep working</button>
          <button type="button" class="btn" id="confirmSubmit">Yes, submit</button>
        </div>
      </div>
    </div>
    <div class="toast" id="toast" hidden></div>
    <script>
      window.EXAM_CFG = {
        remaining: <?= (int) $remaining ?>,
        attemptId: <?= $attemptId ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        saveUrl: <?= json_encode(url('save.php')) ?>
      };
    </script>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ===================== EXAM INSTRUCTIONS / START ===================== */
$examId = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT e.*, (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS qcount,
            (SELECT COALESCE(SUM(marks),0) FROM questions q WHERE q.exam_id = e.id) AS total_marks
     FROM exams e WHERE e.id = ? AND e.is_active = 1'
);
$st->execute([$examId]);
$exam = $st->fetch();
if (!$exam) {
    http_response_code(404);
    exit('Exam not found or not active.');
}

// Close any expired attempt for this exam first
$st = $pdo->prepare("SELECT id, started_at FROM attempts WHERE user_id = ? AND exam_id = ? AND status = 'in_progress'");
$st->execute([$u['id'], $examId]);
$open = $st->fetch();
if ($open && strtotime($open['started_at']) + $exam['duration_minutes'] * 60 <= time()) {
    finalize_attempt($pdo, (int) $open['id'], [], false);
    $open = false;
}

$st = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE user_id = ? AND exam_id = ? AND status = 'submitted'");
$st->execute([$u['id'], $examId]);
$used = (int) $st->fetchColumn();
$canStart = $exam['qcount'] > 0 && ($exam['max_attempts'] == 0 || $used < $exam['max_attempts']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if ($open) {
        redirect('exam.php?attempt=' . (int) $open['id']);
    }
    if (!$canStart) {
        flash('You cannot start this exam.', 'error');
        redirect('exam.php?id=' . $examId);
    }
    $pdo->prepare("INSERT INTO attempts (user_id, exam_id, started_at, status) VALUES (?, ?, ?, 'in_progress')")
        ->execute([$u['id'], $examId, date('Y-m-d H:i:s')]);
    redirect('exam.php?attempt=' . (int) $pdo->lastInsertId());
}

$pageTitle = $exam['title'];
require __DIR__ . '/includes/header.php';
?>
<main class="wrap narrow">
  <div class="card">
    <h1><?= e($exam['title']) ?></h1>
    <p class="muted"><?= e($exam['description']) ?></p>
    <div class="stat-row small">
      <div class="stat-card"><b><?= (int) $exam['qcount'] ?></b><span>Questions</span></div>
      <div class="stat-card"><b><?= (int) $exam['duration_minutes'] ?>m</b><span>Duration</span></div>
      <div class="stat-card"><b><?= (int) $exam['total_marks'] ?></b><span>Total marks</span></div>
      <div class="stat-card"><b><?= (int) $exam['pass_percent'] ?>%</b><span>To pass</span></div>
    </div>
    <h3>Instructions</h3>
    <ul class="rules">
      <li>The timer starts as soon as you press Start and cannot be paused.</li>
      <li>Your answers are saved automatically as you select them.</li>
      <li>When the time ends, the exam is submitted automatically.</li>
      <li>You can move between questions and mark any for review.</li>
      <li><?= $exam['max_attempts'] == 0 ? 'You can retake this exam as many times as you like.' : 'Attempts used: ' . $used . ' of ' . (int) $exam['max_attempts'] . '.' ?></li>
    </ul>
    <form method="post">
      <?= csrf_field() ?>
      <?php if ($open): ?>
        <button class="btn warn" type="submit">Resume your exam</button>
      <?php elseif ($canStart): ?>
        <button class="btn" type="submit">Start exam</button>
      <?php elseif ($exam['qcount'] == 0): ?>
        <p class="alert">This exam has no questions yet.</p>
      <?php else: ?>
        <p class="alert">You have used all your attempts for this exam.</p>
      <?php endif; ?>
      <a class="btn ghost" href="<?= url('dashboard.php') ?>">Back</a>
    </form>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
