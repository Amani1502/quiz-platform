<?php
require_once __DIR__ . '/config.php';
if (user()) {
    redirect('dashboard.php');
}

$error = '';
$email = '';
$locked = ($_SESSION['lock_until'] ?? 0) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if ($locked) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();

        if ($row && password_verify($pass, $row['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['fails'], $_SESSION['lock_until']);
            $_SESSION['user'] = [
                'id'    => (int) $row['id'],
                'name'  => $row['name'],
                'email' => $row['email'],
                'role'  => $row['role'],
            ];
            redirect($row['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
        }

        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['lock_until'] = time() + 60;
            $_SESSION['fails'] = 0;
        }
        $error = 'Incorrect email or password.';
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
  <form class="card auth-card" method="post" novalidate>
    <h1>Welcome back</h1>
    <p class="muted">Log in to take exams and see your results.</p>
    <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn block" type="submit">Log in</button>
    <p class="muted center">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
    <p class="hint">Demo: student@quiz.com / Student@123</p>
  </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
