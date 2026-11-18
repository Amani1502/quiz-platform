<?php
$u         = user();
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = $pageTitle ?? 'QuizArena';
$script    = basename($_SERVER['SCRIPT_NAME']);
$inAdmin   = strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/admin/') !== false;
function nav_active(bool $cond): string { return $cond ? ' class="active"' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | QuizArena</title>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
<script>try{if(localStorage.getItem('quiz-theme')==='light')document.documentElement.classList.add('light')}catch(e){}</script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (empty($hideNav)): ?>
<header class="nav">
  <a class="brand" href="<?= url('index.php') ?>">Quiz<span>Arena</span></a>
  <button class="burger" id="burger" aria-label="Menu">&#9776;</button>
  <nav id="navlinks">
    <a href="<?= url('index.php') ?>"<?= nav_active($script === 'index.php' && !$inAdmin) ?>>Home</a>
    <?php if ($u): ?>
      <a href="<?= url('dashboard.php') ?>"<?= nav_active($script === 'dashboard.php') ?>>Exams</a>
    <?php endif; ?>
    <a href="<?= url('leaderboard.php') ?>"<?= nav_active($script === 'leaderboard.php') ?>>Leaderboard</a>
    <?php if ($u && $u['role'] === 'admin'): ?>
      <a href="<?= url('admin/index.php') ?>"<?= nav_active($inAdmin) ?>>Admin</a>
    <?php endif; ?>
    <?php if ($u): ?>
      <span class="who">Hi, <?= e(explode(' ', $u['name'])[0]) ?></span>
      <a class="btn small ghost" href="<?= url('logout.php') ?>">Logout</a>
    <?php else: ?>
      <a href="<?= url('login.php') ?>"<?= nav_active($script === 'login.php') ?>>Login</a>
      <a class="btn small" href="<?= url('register.php') ?>">Sign up</a>
    <?php endif; ?>
    <button class="theme" id="themeBtn" title="Toggle theme" aria-label="Toggle theme">&#9788;</button>
  </nav>
</header>
<?php endif; ?>
<?php if ($flash): ?>
  <div class="flash <?= e($flash[1]) ?>"><?= e($flash[0]) ?></div>
<?php endif; ?>
