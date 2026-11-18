<?php
require_once __DIR__ . '/config.php';
require_login();
$u = user();

$st = $pdo->prepare(
    "SELECT e.*,
        (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS qcount,
        (SELECT MAX(a.percent) FROM attempts a WHERE a.exam_id = e.id AND a.user_id = ? AND a.status = 'submitted') AS best,
        (SELECT a.id FROM attempts a WHERE a.exam_id = e.id AND a.user_id = ? AND a.status = 'in_progress' LIMIT 1) AS open_attempt
     FROM exams e WHERE e.is_active = 1 ORDER BY e.created_at DESC"
);
$st->execute([$u['id'], $u['id']]);
$exams = $st->fetchAll();

$st = $pdo->prepare(
    "SELECT COUNT(*) AS n, COALESCE(AVG(percent),0) AS avg_pct, COALESCE(MAX(percent),0) AS best_pct
     FROM attempts WHERE user_id = ? AND status = 'submitted'"
);
$st->execute([$u['id']]);
$mine = $st->fetch();

$st = $pdo->prepare(
    "SELECT a.*, e.title, e.pass_percent,
            TIMESTAMPDIFF(SECOND, a.started_at, a.submitted_at) AS secs
     FROM attempts a JOIN exams e ON e.id = a.exam_id
     WHERE a.user_id = ? AND a.status = 'submitted'
     ORDER BY a.submitted_at DESC LIMIT 10"
);
$st->execute([$u['id']]);
$history = $st->fetchAll();

$pageTitle = 'My Exams';
require __DIR__ . '/includes/header.php';
?>
<main class="wrap">
  <h1>Your dashboard</h1>
  <div class="stat-row">
    <div class="stat-card"><b><?= (int) $mine['n'] ?></b><span>Exams taken</span></div>
    <div class="stat-card"><b><?= number_format((float) $mine['avg_pct'], 1) ?>%</b><span>Average score</span></div>
    <div class="stat-card"><b><?= number_format((float) $mine['best_pct'], 1) ?>%</b><span>Best score</span></div>
  </div>

  <h2 class="section-title">Available exams</h2>
  <?php if (!$exams): ?>
    <p class="muted">No exams are available right now.</p>
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
          <?php if ($ex['best'] !== null): ?><span class="chip good">Best <?= number_format((float) $ex['best'], 0) ?>%</span><?php endif; ?>
        </div>
        <?php if ($ex['open_attempt']): ?>
          <a class="btn small warn" href="<?= url('exam.php?attempt=' . (int) $ex['open_attempt']) ?>">Resume exam</a>
        <?php else: ?>
          <a class="btn small" href="<?= url('exam.php?id=' . (int) $ex['id']) ?>"><?= $ex['best'] !== null ? 'Retake' : 'Start' ?></a>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 class="section-title">Recent results</h2>
  <?php if (!$history): ?>
    <p class="muted">You have not taken any exams yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Exam</th><th>Score</th><th>Percent</th><th>Result</th><th>Time</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): $pass = $h['percent'] >= $h['pass_percent']; ?>
        <tr>
          <td><?= e($h['title']) ?></td>
          <td><?= (int) $h['score'] ?> / <?= (int) $h['total'] ?></td>
          <td><?= number_format((float) $h['percent'], 1) ?>%</td>
          <td><span class="badge <?= $pass ? 'good' : 'bad' ?>"><?= $pass ? 'Passed' : 'Failed' ?></span></td>
          <td><?= e(fmt_duration((int) $h['secs'])) ?></td>
          <td><?= e(date('d M Y, h:i A', strtotime($h['submitted_at']))) ?></td>
          <td><a href="<?= url('result.php?attempt=' . (int) $h['id']) ?>">Review</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
