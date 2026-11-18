<?php
/**
 * Grades an attempt on the server. Correct answers never leave the server
 * until an attempt is submitted.
 *
 * @param array $posted        [question_id => 'A'|'B'|'C'|'D'] from the final submit
 * @param bool  $acceptPosted  false when auto-closing an expired attempt
 */
function finalize_attempt(PDO $pdo, int $attemptId, array $posted = [], bool $acceptPosted = true): bool
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'SELECT a.*, e.duration_minutes FROM attempts a
             JOIN exams e ON e.id = a.exam_id WHERE a.id = ? FOR UPDATE'
        );
        $st->execute([$attemptId]);
        $a = $st->fetch();

        if (!$a || $a['status'] !== 'in_progress') {
            $pdo->rollBack();
            return false;
        }

        $endTs = strtotime($a['started_at']) + ((int) $a['duration_minutes']) * 60;
        $now   = time();

        // Accept the final posted answers only within a 30 second grace window
        if ($acceptPosted && $posted && $now <= $endTs + 30) {
            $vq = $pdo->prepare('SELECT id FROM questions WHERE exam_id = ?');
            $vq->execute([$a['exam_id']]);
            $valid = array_flip(array_column($vq->fetchAll(), 'id'));

            $up = $pdo->prepare(
                'INSERT INTO answers (attempt_id, question_id, selected) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE selected = VALUES(selected)'
            );
            foreach ($posted as $qid => $sel) {
                $qid = (int) $qid;
                $sel = strtoupper((string) $sel);
                if (isset($valid[$qid]) && in_array($sel, ['A', 'B', 'C', 'D'], true)) {
                    $up->execute([$attemptId, $qid, $sel]);
                }
            }
        }

        // Grade everything
        $qs = $pdo->prepare(
            'SELECT q.id, q.correct_option, q.marks, ans.selected
             FROM questions q
             LEFT JOIN answers ans ON ans.question_id = q.id AND ans.attempt_id = ?
             WHERE q.exam_id = ?'
        );
        $qs->execute([$attemptId, $a['exam_id']]);

        $mark  = $pdo->prepare('UPDATE answers SET is_correct = ? WHERE attempt_id = ? AND question_id = ?');
        $score = 0;
        $total = 0;
        foreach ($qs->fetchAll() as $q) {
            $total += (int) $q['marks'];
            if ($q['selected'] !== null) {
                $ok = $q['selected'] === $q['correct_option'];
                if ($ok) {
                    $score += (int) $q['marks'];
                }
                $mark->execute([$ok ? 1 : 0, $attemptId, $q['id']]);
            }
        }

        $percent = $total > 0 ? round($score / $total * 100, 2) : 0;
        $subTs   = min($now, $endTs);

        $pdo->prepare(
            "UPDATE attempts SET status = 'submitted', submitted_at = ?, score = ?, total = ?, percent = ? WHERE id = ?"
        )->execute([date('Y-m-d H:i:s', $subTs), $score, $total, $percent, $attemptId]);

        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
