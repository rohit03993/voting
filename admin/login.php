<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (admin_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } else {
        $user = trim((string) ($_POST['username'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        if (attempt_admin_login($pdo, $user, $pass)) {
            redirect('index.php');
        }
        $error = 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css?v=2">
</head>
<body class="auth-body">
  <div class="auth-card">
    <h1>Staff Admin</h1>
    <p class="muted">Manage candidates, publish voting, and view links.</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>Username <input name="username" required autofocus></label>
      <label>Password <input name="password" type="password" required></label>
      <button class="btn" type="submit">Login</button>
    </form>
  </div>
</body>
</html>
