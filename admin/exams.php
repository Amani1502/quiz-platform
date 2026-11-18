<?php
require_once __DIR__ . '/../config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $duration = max(1, min(300, (int) ($_POST['duration_minutes'] ?? 10)));
        $pass     = max(0, min(100, (int) ($_POST['pass_percent'] ?? 40)));
        $maxAtt   = max(0, min(20, (int) ($_POST['max_attempts'] ?? 0)));
        $active   = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || mb_strlen($title) > 150) {
            flash('Title is required (max 150 characters).', 'error');
        } elseif ($id) {
            $pdo->prepare('UPDATE exams SET title=?, description=?, duration_minutes=?, pass_percent=?, max_attempts=?, is_active=? WHERE id=?')
                ->execute([$title, $desc, $duration, $pass, $maxAtt, $active, $id]);
            flash('Exam updated.');
        } else {
            $pdo->prepare('INSERT INTO exams (title, description, duration_minutes, pass_percent, max_attempts, is_active, created_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$title, $desc, $duration, $pass, $maxAtt, $active, date('Y-m-d H:i:s')]);
            flash('Exam created. Now add some questions.');
            redirect('admin/questions.php?exam_id=' . (int) $pdo->lastInsertId());
        }
    } elseif ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE exams SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('Exam visibility changed.');
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM exams WHERE id = ?')->execute([$id]);
        flash('Exam and all its questions and attempts were deleted.');
    }
    redirect('admin/exams.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$exams = $pdo->query(
    "SELECT e.*, (SELECT COUNT(*) FROM questions q WHERE q.exam_id = e.id) AS qcount,
            (SELECT COUNT(*) FROM attempts a WHERE a.exam_id = e.id AND a.status = 'submitted') AS acount
     FROM exams e ORDER BY e.created_at DESC"
)->fetchAll();

$pageTitle = 'Manage Exams';
require __DIR__ . '/../includes/header.php';
?>
<main class="wrap">
  <div class="page-head">
    <h1>Manage exams</h1>
    <a class="btn small ghost" href="<?= url('admin/index.php') ?>">&larr; Dashboard</a>
  </div>

  <form method="post" class="card form-grid">
    <h3><?= $edit ? 'Edit exam' : 'Create new exam' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <label class="span2">Title<input type="text" name="title" required maxlength="150" value="<?= e($edit['title'] ?? '') ?>"></label>
    <label class="span2">Description<textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></label>
    <label>Duration (minutes)<input type="number" name="duration_minutes" min="1" max="300" value="<?= (int) ($edit['duration_minutes'] ?? 10) ?>"></label>
    <label>Pass mark (%)<input type="number" name="pass_percent" min="0" max="100" value="<?= (int) ($edit['pass_percent'] ?? 40) ?>"></label>
    <label>Max attempts (0 = unlimited)<input type="number" name="max_attempts" min="0" max="20" value="<?= (int) ($edit['max_attempts'] ?? 0) ?>"></label>
    <label class="check"><input type="checkbox" name="is_active" <?= (!$edit || $edit['is_active']) ? 'checked' : '' ?>> Visible to students</label>
    <div class="span2 actions">
      <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Create exam' ?></button>
      <?php if ($edit): ?><a class="btn ghost" href="<?= url('admin/exams.php') ?>">Cancel</a><?php endif; ?>
    </div>
  </form>

  <h2 class="section-title">All exams</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Questions</th><th>Time</th><th>Pass</th><th>Attempts</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($exams as $ex): ?>
        <tr>
          <td><b><?= e($ex['title']) ?></b></td>
          <td><?= (int) $ex['qcount'] ?></td>
          <td><?= (int) $ex['duration_minutes'] ?> min</td>
          <td><?= (int) $ex['pass_percent'] ?>%</td>
          <td><?= (int) $ex['acount'] ?></td>
          <td><span class="badge <?= $ex['is_active'] ? 'good' : '' ?>"><?= $ex['is_active'] ? 'Active' : 'Hidden' ?></span></td>
          <td class="row-actions">
            <a href="<?= url('admin/questions.php?exam_id=' . (int) $ex['id']) ?>">Questions</a>
            <a href="<?= url('admin/exams.php?edit=' . (int) $ex['id']) ?>">Edit</a>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>">
              <button class="link" type="submit"><?= $ex['is_active'] ? 'Hide' : 'Show' ?></button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('Delete this exam with all its questions and results?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>">
              <button class="link danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$exams): ?><tr><td colspan="7" class="muted">No exams yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
