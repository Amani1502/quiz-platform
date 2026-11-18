<?php
require_once __DIR__ . '/config.php';
if (user()) {
    redirect('dashboard.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name'] ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your name (2 to 100 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $errors[] = 'Password must be at least 8 characters and include a letter and a number.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $pdo->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, "student", ?)')
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            flash('Account created! You can log in now.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Sign up';
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
  <form class="card auth-card" method="post" novalidate>
    <h1>Create account</h1>
    <p class="muted">Join to take exams and appear on the leaderboard.</p>
    <?php foreach ($errors as $er): ?><div class="alert"><?= e($er) ?></div><?php endforeach; ?>
    <?= csrf_field() ?>
    <label>Full name<input type="text" name="name" value="<?= e($name) ?>" required maxlength="100"></label>
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required maxlength="150"></label>
    <label>Password<input type="password" name="password" required minlength="8"></label>
    <label>Confirm password<input type="password" name="confirm" required minlength="8"></label>
    <button class="btn block" type="submit">Sign up</button>
    <p class="muted center">Already registered? <a href="<?= url('login.php') ?>">Log in</a></p>
  </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
