<?php
require_once __DIR__ . '/../config.php';
require_admin();

$examId = (int) ($_GET['exam'] ?? 0);
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 25;

$where  = "a.status = 'submitted'";
$params = [];
if ($examId) {
    $where .= ' AND a.exam_id = ?';
    $params[] = $examId;
}
if ($search !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$base = "FROM attempts a JOIN users u ON u.id = a.user_id JOIN exams e ON e.id = a.exam_id WHERE $where";

// ---- CSV export ----
if (isset($_GET['export'])) {
    $st = $pdo->prepare("SELECT u.name, u.email, e.title, a.score, a.total, a.percent, e.pass_percent, a.started_at, a.submitted_at,
                         TIMESTAMPDIFF(SECOND, a.started_at, a.submitted_at) AS secs $base ORDER BY a.submitted_at DESC");
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quiz-results-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'Email', 'Exam', 'Score', 'Total', 'Percent', 'Result', 'Time (sec)', 'Started', 'Submitted']);
    foreach ($st->fetchAll() as $r) {
        fputcsv($out, [csv_safe($r['name']), csv_safe($r['email']), csv_safe($r['title']), $r['score'], $r['total'], $r['percent'],
            $r['percent'] >= $r['pass_percent'] ? 'Pass' : 'Fail', $r['secs'], $r['started_at'], $r['submitted_at']]);
    }
    fclose($out);
    exit;
}

$st = $pdo->prepare("SELECT COUNT(*) $base");
$st->execute($params);
$totalRows = (int) $st->fetchColumn();
$pages     = max(1, (int) ceil($totalRows / $per));
$page      = min($page, $pages);
$offset    = ($page - 1) * $per;

$st = $pdo->prepare("SELECT a.id, u.name, u.email, e.title, e.pass_percent, a.score, a.total, a.percent, a.submitted_at,
                     TIMESTAMPDIFF(SECOND, a.started_at, a.submitted_at) AS secs
                     $base ORDER BY a.submitted_at DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$exams = $pdo->query('SELECT id, title FROM exams ORDER BY title')->fetchAll();
$qs = http_build_query(array_filter(['exam' => $examId ?: null, 'q' => $search ?: null]));

$pageTitle = 'All Results';
require __DIR__ . '/../includes/header.php';
?>
<main class="wrap">
  <div class="page-head">
    <h1>All results</h1>
    <a class="btn small ghost" href="<?= url('admin/index.php') ?>">&larr; Dashboard</a>
  </div>

  <form method="get" class="card inline-form">
    <select name="exam">
      <option value="0">All exams</option>
      <?php foreach ($exams as $ex): ?>
        <option value="<?= (int) $ex['id'] ?>"<?= $ex['id'] == $examId ? ' selected' : '' ?>><?= e($ex['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="search" name="q" placeholder="Search name or email" value="<?= e($search) ?>">
    <button class="btn small" type="submit">Filter</button>
    <a class="btn small ghost" href="?<?= e($qs . ($qs ? '&' : '')) ?>export=1">Export CSV</a>
  </form>

  <p class="muted"><?= $totalRows ?> result<?= $totalRows == 1 ? '' : 's' ?></p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>Exam</th><th>Score</th><th>Percent</th><th>Result</th><th>Time</th><th>Submitted</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $pass = $r['percent'] >= $r['pass_percent']; ?>
        <tr>
          <td><?= e($r['name']) ?><br><small class="muted"><?= e($r['email']) ?></small></td>
          <td><?= e($r['title']) ?></td>
          <td><?= (int) $r['score'] ?> / <?= (int) $r['total'] ?></td>
          <td><?= number_format((float) $r['percent'], 1) ?>%</td>
          <td><span class="badge <?= $pass ? 'good' : 'bad' ?>"><?= $pass ? 'Pass' : 'Fail' ?></span></td>
          <td><?= e(fmt_duration((int) $r['secs'])) ?></td>
          <td><?= e(date('d M Y, h:i A', strtotime($r['submitted_at']))) ?></td>
          <td><a href="<?= url('result.php?attempt=' . (int) $r['id']) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="muted">No results found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?><a class="btn small ghost" href="?<?= e($qs . ($qs ? '&' : '') . 'page=' . ($page - 1)) ?>">&larr; Prev</a><?php endif; ?>
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn small ghost" href="?<?= e($qs . ($qs ? '&' : '') . 'page=' . ($page + 1)) ?>">Next &rarr;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
