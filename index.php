<?php
require_once __DIR__ . '/config.php';

$exams = $pdo->query(
    "SELECT e.*, (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS qcount
     FROM exams e WHERE e.is_active = 1 ORDER BY e.created_at DESC LIMIT 6"
)->fetchAll();

$stats = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM users WHERE role='student') AS students,
            (SELECT COUNT(*) FROM exams WHERE is_active=1) AS exams,
            (SELECT COUNT(*) FROM attempts WHERE status='submitted') AS attempts"
)->fetch();

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="hero-text">
    <span class="eyebrow">Online Exam Platform</span>
    <h1>Take timed quizzes.<br><span class="grad">Climb the leaderboard.</span></h1>
    <p>Practice with timed multiple-choice exams, get instant auto-graded results with explanations, and see how you rank against everyone else.</p>
    <div class="actions">
      <?php if (user()): ?>
        <a class="btn" href="<?= url('dashboard.php') ?>">Browse exams</a>
      <?php else: ?>
        <a class="btn" href="<?= url('register.php') ?>">Get started free</a>
        <a class="btn ghost" href="<?= url('login.php') ?>">I have an account</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-stats">
    <div class="stat-card"><b data-count="<?= (int) $stats['exams'] ?>">0</b><span>Active exams</span></div>
    <div class="stat-card"><b data-count="<?= (int) $stats['students'] ?>">0</b><span>Students</span></div>
    <div class="stat-card"><b data-count="<?= (int) $stats['attempts'] ?>">0</b><span>Attempts graded</span></div>
  </div>
</section>

<section class="wrap">
  <h2 class="section-title">How it works</h2>
  <div class="features">
    <div class="card feature"><em>&#9201;</em><h3>Live timer</h3><p>Server-verified countdown. When time is up, your answers are submitted automatically.</p></div>
    <div class="card feature"><em>&#9989;</em><h3>Instant grading</h3><p>Get your score, pass/fail status and a full review with explanations right after submitting.</p></div>
    <div class="card feature"><em>&#127942;</em><h3>Leaderboard</h3><p>Ranked by score, then by speed. Every exam has its own top-20 list.</p></div>
    <div class="card feature"><em>&#128274;</em><h3>Secure</h3><p>Hashed passwords, CSRF protection and answers that are never sent to your browser.</p></div>
  </div>
</section>

<section class="wrap">
  <h2 class="section-title">Available exams</h2>
  <?php if (!$exams): ?>
    <p class="muted">No exams yet. Check back soon.</p>
  <?php else: ?>
  <div class="grid">
    <?php foreach ($exams as $ex): ?>
      <article class="card exam-card">
        <h3><?= e($ex['title']) ?></h3>
        <p class="muted"><?= e($ex['description']) ?></p>
        <div class="chips">
          <span class="chip"><?= (int) $ex['qcount'] ?> questions</span>
          <span class="chip"><?= (int) $ex['duration_minutes'] ?> min</span>
          <span class="chip">Pass <?= (int) $ex['pass_percent'] ?>%</span>
        </div>
        <a class="btn small" href="<?= user() ? url('exam.php?id=' . (int) $ex['id']) : url('login.php') ?>">
          <?= user() ? 'View exam' : 'Log in to start' ?>
        </a>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
