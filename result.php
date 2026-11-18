<?php
require_once __DIR__ . '/config.php';
require_login();
$u = user();

$attemptId = (int) ($_GET['attempt'] ?? 0);

$st = $pdo->prepare(
    "SELECT a.*, e.title, e.pass_percent, us.name AS student,
            TIMESTAMPDIFF(SECOND, a.started_at, a.submitted_at) AS secs
     FROM attempts a
     JOIN exams e ON e.id = a.exam_id
     JOIN users us ON us.id = a.user_id
     WHERE a.id = ?"
);
$st->execute([$attemptId]);
$a = $st->fetch();

if (!$a || ($a['user_id'] != $u['id'] && $u['role'] !== 'admin')) {
    http_response_code(404);
    exit('Result not found.');
}
if ($a['status'] !== 'submitted') {
    redirect('exam.php?attempt=' . $attemptId);
}

$st = $pdo->prepare(
    'SELECT q.*, ans.selected, ans.is_correct
     FROM questions q
     LEFT JOIN answers ans ON ans.question_id = q.id AND ans.attempt_id = ?
     WHERE q.exam_id = ? ORDER BY q.id'
);
$st->execute([$attemptId, $a['exam_id']]);
$review = $st->fetchAll();

$correct = $wrong = $skipped = 0;
foreach ($review as $r) {
    if ($r['selected'] === null) {
        $skipped++;
    } elseif ($r['is_correct']) {
        $correct++;
    } else {
        $wrong++;
    }
}

// Rank = position among every student's best score for this exam
$st = $pdo->prepare("SELECT MAX(score) FROM attempts WHERE user_id = ? AND exam_id = ? AND status = 'submitted'");
$st->execute([$a['user_id'], $a['exam_id']]);
$myBest = (int) $st->fetchColumn();
$st = $pdo->prepare(
    "SELECT COUNT(*) + 1 FROM (SELECT user_id, MAX(score) AS s FROM attempts
     WHERE exam_id = ? AND status = 'submitted' GROUP BY user_id) t WHERE t.s > ?"
);
$st->execute([$a['exam_id'], $myBest]);
$rank = (int) $st->fetchColumn();

$passed = $a['percent'] >= $a['pass_percent'];

$pageTitle = 'Result';
require __DIR__ . '/includes/header.php';
?>
<main class="wrap">
  <div class="card result-hero">
    <div class="ring" style="--p:<?= (float) $a['percent'] ?>">
      <div><b><?= number_format((float) $a['percent'], 0) ?>%</b><small><?= (int) $a['score'] ?> / <?= (int) $a['total'] ?></small></div>
    </div>
    <div>
      <span class="badge big <?= $passed ? 'good' : 'bad' ?>"><?= $passed ? 'Passed' : 'Not passed' ?></span>
      <h1><?= e($a['title']) ?></h1>
      <p class="muted"><?= e($a['student']) ?> &middot; <?= e(date('d M Y, h:i A', strtotime($a['submitted_at']))) ?> &middot; Pass mark <?= (int) $a['pass_percent'] ?>%</p>
      <div class="chips">
        <span class="chip good">&#10003; <?= $correct ?> correct</span>
        <span class="chip bad">&#10007; <?= $wrong ?> wrong</span>
        <span class="chip">&minus; <?= $skipped ?> skipped</span>
        <span class="chip">&#9201; <?= e(fmt_duration((int) $a['secs'])) ?></span>
        <span class="chip">&#127942; Rank #<?= $rank ?></span>
      </div>
      <div class="actions">
        <a class="btn small" href="<?= url('exam.php?id=' . (int) $a['exam_id']) ?>">Retake</a>
        <a class="btn small ghost" href="<?= url('leaderboard.php?exam=' . (int) $a['exam_id']) ?>">Leaderboard</a>
        <a class="btn small ghost" href="<?= url('dashboard.php') ?>">Dashboard</a>
      </div>
    </div>
  </div>

  <h2 class="section-title">Answer review</h2>
  <?php foreach ($review as $i => $r):
      $state = $r['selected'] === null ? 'skip' : ($r['is_correct'] ? 'ok' : 'no'); ?>
    <article class="card review <?= $state ?>">
      <div class="q-head">
        <span class="q-num">Question <?= $i + 1 ?></span>
        <span class="badge <?= $state === 'ok' ? 'good' : ($state === 'no' ? 'bad' : '') ?>">
          <?= $state === 'ok' ? 'Correct' : ($state === 'no' ? 'Wrong' : 'Skipped') ?>
        </span>
      </div>
      <h3><?= nl2br(e($r['question'])) ?></h3>
      <ul class="rev-opts">
        <?php foreach (['A', 'B', 'C', 'D'] as $L):
            $cls = '';
            if ($L === $r['correct_option']) { $cls = 'is-correct'; }
            elseif ($L === $r['selected']) { $cls = 'is-wrong'; } ?>
          <li class="<?= $cls ?>">
            <span class="key"><?= $L ?></span> <?= e($r['option_' . strtolower($L)]) ?>
            <?php if ($L === $r['selected']): ?><em>your answer</em><?php endif; ?>
            <?php if ($L === $r['correct_option']): ?><em>correct</em><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!empty($r['explanation'])): ?>
        <p class="explain"><b>Explanation:</b> <?= e($r['explanation']) ?></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
