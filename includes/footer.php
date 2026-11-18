<?php if (empty($hideNav)): ?>
<footer class="footer">&copy; <?= date('Y') ?> QuizArena &middot; Online Exam Platform</footer>
<?php endif; ?>
<script src="<?= url('assets/app.js') ?>"></script>
<?php foreach (($extraScripts ?? []) as $src): ?>
<script src="<?= url($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
