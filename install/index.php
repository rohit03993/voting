<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config/config.php';
$sampleFile = dirname(__DIR__) . '/config/config.sample.php';
$schemaFile = __DIR__ . '/schema.sql';
$installed = is_file($configFile);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $principalCode = trim($_POST['principal_passcode'] ?? '654321');
    $directorCode = trim($_POST['director_passcode'] ?? '987654');
    $title = trim($_POST['election_title'] ?? 'HCS Student Council Elections 2026');
    $baseUrl = rtrim(trim($_POST['base_url'] ?? ''), '/');

    try {
        if ($dbName === '' || $dbUser === '' || $adminPass === '') {
            throw new RuntimeException('Database name, user, and admin password are required.');
        }

        $dsn = "mysql:host={$dbHost};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException('Could not read schema.sql');
        }
        $pdo->exec($sql);

        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$adminUser, $hash]);

        $principalToken = bin2hex(random_bytes(16));
        $directorToken = bin2hex(random_bytes(16));
        $election = $pdo->prepare(
            'INSERT INTO elections (title, status, principal_token, director_token) VALUES (?, "draft", ?, ?)'
        );
        $election->execute([$title, $principalToken, $directorToken]);
        $electionId = (int) $pdo->lastInsertId();

        $positions = [
            'Head Boy', 'Head Girl', 'Deputy Head Boy', 'Deputy Head Girl',
            'Sports Captain (Senior Wing)', 'Sports Captain (Junior Wing)',
            'Cultural Captain (Senior Wing)', 'Cultural Captain (Junior Wing)',
            'Assembly Incharge (Senior Wing)', 'Assembly Incharge (Junior Wing)',
            'Discipline Incharge (Senior Wing)', 'Discipline Incharge (Junior Wing)',
            'Class Captain 10 (L1)', 'Class Captain 10 (L2)',
            'Class Captain 9 (CHD)', 'Class Captain 9 (CHN)',
            'Class Captain (Class 8)', 'Class Captain (Class 7)',
            'Class Captain (Class 6)', 'Class Captain (Class 5)',
        ];
        $posStmt = $pdo->prepare(
            'INSERT INTO positions (election_id, name, sort_order) VALUES (?, ?, ?)'
        );
        foreach ($positions as $i => $name) {
            $posStmt->execute([$electionId, $name, $i + 1]);
        }

        $set = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $set->execute(['principal_passcode', $principalCode]);
        $set->execute(['director_passcode', $directorCode]);

        $uploads = dirname(__DIR__) . '/assets/uploads';
        if (!is_dir($uploads)) {
            mkdir($uploads, 0755, true);
        }
        file_put_contents($uploads . '/.htaccess', "Options -Indexes\n");

        $configPhp = "<?php\nreturn " . var_export([
            'db' => [
                'host' => $dbHost,
                'name' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass,
                'charset' => 'utf8mb4',
            ],
            'app' => [
                'name' => 'HCS Student Council Elections',
                'base_url' => $baseUrl,
                'timezone' => 'Asia/Kolkata',
                'session_name' => 'hcs_vote_sess',
            ],
            'security' => [
                'admin_user' => $adminUser,
                'admin_pass' => '(stored hashed in database)',
                'principal_passcode' => $principalCode,
                'director_passcode' => $directorCode,
            ],
        ], true) . ";\n";

        if (file_put_contents($configFile, $configPhp) === false) {
            throw new RuntimeException('Could not write config/config.php — check folder permissions.');
        }

        $installed = true;
        $success = 'Installation complete. Delete or lock the /install folder after login.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css?v=2">
</head>
<body class="auth-body">
  <div class="auth-card">
    <h1>HCS Voting Installer</h1>
    <?php if ($installed && $success): ?>
      <div class="alert ok"><?= htmlspecialchars($success) ?></div>
      <p><a class="btn" href="../admin/login.php">Go to Admin Login</a></p>
    <?php elseif ($installed): ?>
      <div class="alert ok">Already installed. <a href="../admin/login.php">Open Admin</a></div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" class="stack">
        <label>DB Host <input name="db_host" value="localhost" required></label>
        <label>DB Name <input name="db_name" placeholder="hcs_voting" required></label>
        <label>DB User <input name="db_user" required></label>
        <label>DB Password <input name="db_pass" type="password"></label>
        <label>Admin Username <input name="admin_user" value="admin" required></label>
        <label>Admin Password <input name="admin_pass" type="password" required></label>
        <label>Principal Passcode <input name="principal_passcode" value="654321" required></label>
        <label>Director Passcode <input name="director_passcode" value="987654" required></label>
        <label>Election Title <input name="election_title" value="HCS Student Council Elections 2026" required></label>
        <label>Base URL (optional) <input name="base_url" placeholder="https://horizonclasses.com/vote"></label>
        <button class="btn" type="submit">Install</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
