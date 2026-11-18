<?php
require_once __DIR__ . '/config.php';

$exams = $pdo->query('SELECT id, title FROM exams WHERE is_active = 1 ORDER BY title')->fetchAll();
$examId = (int) ($_GET['exam'] ?? ($exams[0]['id'] ?? 0));

$rows = [];
if ($examId) {
    // Each student's best attempt: highest score first, then fastest time
    $st = $pdo->prepare(
        "SELECT u.id AS uid, u.name, a.score, a.total, a.percent,
                TIMESTAMPDIFF(SECOND, a.started_at, a.submitted_at) AS secs, a.submitted_at
         FROM attempts a
         JOIN users u ON u.id = a.user_id
         WHERE a.exam_id = ? AND a.status = 'submitted'
           AND a.id = (
                SELECT a2.id FROM attempts a2
                WHERE a2.user_id = a.user_id AND a2.exam_id = a.exam_id AND a2.status = 'submitted'
                ORDER BY a2.score DESC, TIMESTAMPDIFF(SECOND, a2.started_at, a2.submitted_at) ASC, a2.id ASC
                LIMIT 1)
         ORDER BY a.score DESC, secs ASC
         LIMIT 20"
    );
    $st->execute([$examId]);
    $rows = $st->fetchAll();
}

$me = user();
$pageTitle = 'Leaderboard';
require __DIR__ . '/includes/header.php';
$medals = ['&#129351;', '&#129352;', '&#129353;'];
?>
<main class="wrap narrow">
  <h1>Leaderboard</h1>
  <p class="muted">Top 20 students. Ranked by score, then by fastest time.</p>

  <form method="get" class="inline-form">
    <select name="exam" onchange="this.form.submit()">
      <?php foreach ($exams as $ex): ?>
        <option value="<?= (int) $ex['id'] ?>"<?= $ex['id'] == $examId ? ' selected' : '' ?>><?= e($ex['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn small">Show</button></noscript>
  </form>

  <?php if (!$rows): ?>
    <p class="card muted">No results for this exam yet. Be the first!</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="leader">
      <thead><tr><th>#</th><th>Student</th><th>Score</th><th>Percent</th><th>Time</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <tr class="<?= ($me && (int) $me['id'] === (int) $r['uid']) ? 'me' : '' ?>">
          <td><?= $i < 3 ? $medals[$i] : $i + 1 ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= (int) $r['score'] ?> / <?= (int) $r['total'] ?></td>
          <td><?= number_format((float) $r['percent'], 1) ?>%</td>
          <td><?= e(fmt_duration((int) $r['secs'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
