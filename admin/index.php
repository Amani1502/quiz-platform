<?php
require_once __DIR__ . '/../config.php';
require_admin();

$s = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM users WHERE role = 'student') AS students,
            (SELECT COUNT(*) FROM exams) AS exams,
            (SELECT COUNT(*) FROM questions) AS questions,
            (SELECT COUNT(*) FROM attempts WHERE status = 'submitted') AS attempts,
            (SELECT COALESCE(AVG(percent), 0) FROM attempts WHERE status = 'submitted') AS avg_pct,
            (SELECT COALESCE(AVG(a.percent >= e.pass_percent) * 100, 0)
               FROM attempts a JOIN exams e ON e.id = a.exam_id WHERE a.status = 'submitted') AS pass_rate"
)->fetch();

$recent = $pdo->query(
    "SELECT a.id, u.name, e.title, a.score, a.total, a.percent, a.submitted_at, e.pass_percent
     FROM attempts a JOIN users u ON u.id = a.user_id JOIN exams e ON e.id = a.exam_id
     WHERE a.status = 'submitted' ORDER BY a.submitted_at DESC LIMIT 8"
)->fetchAll();

$perExam = $pdo->query(
    "SELECT e.title, COUNT(a.id) AS n, COALESCE(AVG(a.percent), 0) AS avg_pct
     FROM exams e LEFT JOIN attempts a ON a.exam_id = e.id AND a.status = 'submitted'
     GROUP BY e.id, e.title ORDER BY n DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="wrap">
  <div class="page-head">
    <h1>Admin dashboard</h1>
    <div class="actions">
      <a class="btn small" href="<?= url('admin/exams.php') ?>">Manage exams</a>
      <a class="btn small ghost" href="<?= url('admin/results.php') ?>">All results</a>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat-card"><b><?= (int) $s['students'] ?></b><span>Students</span></div>
    <div class="stat-card"><b><?= (int) $s['exams'] ?></b><span>Exams</span></div>
    <div class="stat-card"><b><?= (int) $s['questions'] ?></b><span>Questions</span></div>
    <div class="stat-card"><b><?= (int) $s['attempts'] ?></b><span>Attempts</span></div>
    <div class="stat-card"><b><?= number_format((float) $s['avg_pct'], 1) ?>%</b><span>Average score</span></div>
    <div class="stat-card"><b><?= number_format((float) $s['pass_rate'], 0) ?>%</b><span>Pass rate</span></div>
  </div>

  <div class="two-col">
    <section>
      <h2 class="section-title">Average score by exam</h2>
      <div class="card">
        <?php foreach ($perExam as $p): ?>
          <div class="hbar">
            <div class="hbar-label"><?= e($p['title']) ?> <small>(<?= (int) $p['n'] ?> attempts)</small></div>
            <div class="bar"><i style="width:<?= (float) $p['avg_pct'] ?>%"></i></div>
            <span><?= number_format((float) $p['avg_pct'], 0) ?>%</span>
          </div>
        <?php endforeach; ?>
        <?php if (!$perExam): ?><p class="muted">No data yet.</p><?php endif; ?>
      </div>
    </section>

    <section>
      <h2 class="section-title">Recent submissions</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Exam</th><th>Score</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['title']) ?></td>
              <td><?= number_format((float) $r['percent'], 0) ?>%</td>
              <td><a href="<?= url('result.php?attempt=' . (int) $r['id']) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="4" class="muted">No submissions yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
