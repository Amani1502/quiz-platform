<?php
// AJAX autosave: stores one answer while the exam is running.
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

function fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST only');
}
if (!user()) {
    fail(401, 'Not logged in');
}
$sent = $_POST['csrf'] ?? '';
if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    fail(419, 'Bad token');
}

$attemptId  = (int) ($_POST['attempt'] ?? 0);
$questionId = (int) ($_POST['question'] ?? 0);
$selected   = strtoupper(trim((string) ($_POST['selected'] ?? '')));

$st = $pdo->prepare(
    "SELECT a.*, e.duration_minutes FROM attempts a JOIN exams e ON e.id = a.exam_id
     WHERE a.id = ? AND a.user_id = ? AND a.status = 'in_progress'"
);
$st->execute([$attemptId, user()['id']]);
$a = $st->fetch();
if (!$a) {
    fail(404, 'Attempt not active');
}
if (time() > strtotime($a['started_at']) + $a['duration_minutes'] * 60 + 5) {
    fail(410, 'Time is over');
}

$st = $pdo->prepare('SELECT id FROM questions WHERE id = ? AND exam_id = ?');
$st->execute([$questionId, $a['exam_id']]);
if (!$st->fetch()) {
    fail(422, 'Invalid question');
}

if ($selected === '') {
    $pdo->prepare('DELETE FROM answers WHERE attempt_id = ? AND question_id = ?')->execute([$attemptId, $questionId]);
} elseif (in_array($selected, ['A', 'B', 'C', 'D'], true)) {
    $pdo->prepare(
        'INSERT INTO answers (attempt_id, question_id, selected) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE selected = VALUES(selected), is_correct = NULL'
    )->execute([$attemptId, $questionId, $selected]);
} else {
    fail(422, 'Invalid option');
}

echo json_encode(['ok' => true]);
