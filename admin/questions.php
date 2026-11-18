<?php
require_once __DIR__ . '/../config.php';
require_admin();

$examId = (int) ($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$st->execute([$examId]);
$exam = $st->fetch();
if (!$exam) {
    http_response_code(404);
    exit('Exam not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $qid  = (int) ($_POST['id'] ?? 0);
        $q    = trim($_POST['question'] ?? '');
        $opts = [trim($_POST['option_a'] ?? ''), trim($_POST['option_b'] ?? ''), trim($_POST['option_c'] ?? ''), trim($_POST['option_d'] ?? '')];
        $cor  = strtoupper($_POST['correct_option'] ?? '');
        $mk   = max(1, min(100, (int) ($_POST['marks'] ?? 1)));
        $exp  = trim($_POST['explanation'] ?? '');

        if ($q === '' || in_array('', $opts, true) || !in_array($cor, ['A', 'B', 'C', 'D'], true)) {
            flash('Fill the question, all four options and choose the correct answer.', 'error');
            redirect('admin/questions.php?exam_id=' . $examId . ($qid ? '&edit=' . $qid : ''));
        }
        if ($qid) {
            $pdo->prepare('UPDATE questions SET question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, marks=?, explanation=? WHERE id=? AND exam_id=?')
                ->execute([$q, $opts[0], $opts[1], $opts[2], $opts[3], $cor, $mk, $exp ?: null, $qid, $examId]);
            flash('Question updated.');
        } else {
            $pdo->prepare('INSERT INTO questions (exam_id, question, option_a, option_b, option_c, option_d, correct_option, marks, explanation) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$examId, $q, $opts[0], $opts[1], $opts[2], $opts[3], $cor, $mk, $exp ?: null]);
            flash('Question added.');
        }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM questions WHERE id = ? AND exam_id = ?')->execute([(int) ($_POST['id'] ?? 0), $examId]);
        flash('Question deleted.');
    } elseif ($action === 'import') {
        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 1048576) {
            flash('Please upload a CSV file smaller than 1 MB.', 'error');
        } else {
            $h = fopen($file['tmp_name'], 'r');
            $added = 0;
            $skipped = 0;
            $ins = $pdo->prepare('INSERT INTO questions (exam_id, question, option_a, option_b, option_c, option_d, correct_option, marks, explanation) VALUES (?,?,?,?,?,?,?,?,?)');
            $pdo->beginTransaction();
            while (($row = fgetcsv($h)) !== false) {
                if (count($row) < 6) { $skipped++; continue; }
                if (strtolower(trim($row[0])) === 'question') { continue; } // header row
                [$q, $a, $b, $c, $d, $cor] = array_map('trim', array_slice($row, 0, 6));
                $cor = strtoupper($cor);
                $mk  = (isset($row[6]) && (int) $row[6] > 0) ? min(100, (int) $row[6]) : 1;
                $exp = trim($row[7] ?? '');
                if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '' || !in_array($cor, ['A', 'B', 'C', 'D'], true)) {
                    $skipped++;
                    continue;
                }
                $ins->execute([$examId, $q, $a, $b, $c, $d, $cor, $mk, $exp ?: null]);
                $added++;
            }
            $pdo->commit();
            fclose($h);
            flash("Imported $added question(s)." . ($skipped ? " Skipped $skipped invalid row(s)." : ''), $added ? 'success' : 'error');
        }
    }
    redirect('admin/questions.php?exam_id=' . $examId);
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM questions WHERE id = ? AND exam_id = ?');
    $st->execute([(int) $_GET['edit'], $examId]);
    $edit = $st->fetch() ?: null;
}

$st = $pdo->prepare('SELECT * FROM questions WHERE exam_id = ? ORDER BY id');
$st->execute([$examId]);
$questions = $st->fetchAll();

$pageTitle = 'Questions';
require __DIR__ . '/../includes/header.php';
?>
<main class="wrap">
  <div class="page-head">
    <div>
      <h1>Question bank</h1>
      <p class="muted"><?= e($exam['title']) ?> &middot; <?= count($questions) ?> questions</p>
    </div>
    <a class="btn small ghost" href="<?= url('admin/exams.php') ?>">&larr; All exams</a>
  </div>

  <form method="post" class="card form-grid">
    <h3><?= $edit ? 'Edit question' : 'Add a question' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label class="span2">Question<textarea name="question" rows="2" required><?= e($edit['question'] ?? '') ?></textarea></label>
    <label>Option A<input type="text" name="option_a" required maxlength="255" value="<?= e($edit['option_a'] ?? '') ?>"></label>
    <label>Option B<input type="text" name="option_b" required maxlength="255" value="<?= e($edit['option_b'] ?? '') ?>"></label>
    <label>Option C<input type="text" name="option_c" required maxlength="255" value="<?= e($edit['option_c'] ?? '') ?>"></label>
    <label>Option D<input type="text" name="option_d" required maxlength="255" value="<?= e($edit['option_d'] ?? '') ?>"></label>
    <label>Correct answer
      <select name="correct_option" required>
        <?php foreach (['A', 'B', 'C', 'D'] as $L): ?>
          <option value="<?= $L ?>"<?= ($edit['correct_option'] ?? 'A') === $L ? ' selected' : '' ?>><?= $L ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Marks<input type="number" name="marks" min="1" max="100" value="<?= (int) ($edit['marks'] ?? 1) ?>"></label>
    <label class="span2">Explanation (shown after submitting)<textarea name="explanation" rows="2"><?= e($edit['explanation'] ?? '') ?></textarea></label>
    <div class="span2 actions">
      <button class="btn" type="submit"><?= $edit ? 'Save question' : 'Add question' ?></button>
      <?php if ($edit): ?><a class="btn ghost" href="<?= url('admin/questions.php?exam_id=' . $examId) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>

  <form method="post" enctype="multipart/form-data" class="card inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="import">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <div>
      <b>Bulk import (CSV)</b>
      <p class="muted small-text">Columns: question, option_a, option_b, option_c, option_d, correct (A-D), marks, explanation.
        <a href="<?= url('sample_questions.csv') ?>" download>Download sample</a></p>
    </div>
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <button class="btn small" type="submit">Import</button>
  </form>

  <h2 class="section-title">Questions</h2>
  <?php foreach ($questions as $i => $q): ?>
    <article class="card q-admin">
      <div class="q-head">
        <span class="q-num">#<?= $i + 1 ?></span>
        <span class="chip"><?= (int) $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
      </div>
      <h3><?= nl2br(e($q['question'])) ?></h3>
      <ul class="rev-opts">
        <?php foreach (['A', 'B', 'C', 'D'] as $L): ?>
          <li class="<?= $L === $q['correct_option'] ? 'is-correct' : '' ?>"><span class="key"><?= $L ?></span> <?= e($q['option_' . strtolower($L)]) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="row-actions">
        <a href="<?= url('admin/questions.php?exam_id=' . $examId . '&edit=' . (int) $q['id']) ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete this question?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="exam_id" value="<?= $examId ?>">
          <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
          <button class="link danger" type="submit">Delete</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$questions): ?><p class="muted">No questions yet. Add one above or import a CSV.</p><?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
