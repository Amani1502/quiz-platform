<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/grader.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_verify();

$attemptId = (int) ($_POST['attempt'] ?? 0);

$st = $pdo->prepare('SELECT id, status FROM attempts WHERE id = ? AND user_id = ?');
$st->execute([$attemptId, user()['id']]);
$a = $st->fetch();
if (!$a) {
    http_response_code(404);
    exit('Attempt not found.');
}

$posted = (isset($_POST['ans']) && is_array($_POST['ans'])) ? $_POST['ans'] : [];
finalize_attempt($pdo, $attemptId, $posted, true);

redirect('result.php?attempt=' . $attemptId);
