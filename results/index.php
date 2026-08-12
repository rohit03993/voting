<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$election = current_election($pdo);
$base = base_url($config);
$title = $election['title'] ?? 'HCS Voting';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Live Results — <?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css">
</head>
<body class="vote-body">
  <header class="vote-header">
    <div class="container">
      <div class="brand-lockup">Horizon Competition School</div>
      <h1>Live Results</h1>
      <p class="muted" id="statusLine">Loading…</p>
    </div>
  </header>
  <main class="container">
    <section class="panel">
      <h2>Ballot totals</h2>
      <div id="totals" class="stats"></div>
    </section>
    <section class="panel">
      <h2>Winners</h2>
      <div id="winners" class="winners-grid"></div>
    </section>
    <section class="panel">
      <h2>Full Results</h2>
      <div id="results"></div>
    </section>
  </main>
  <script>
    window.HCS_RESULTS = { api: <?= json_encode($base . '/api/') ?> };
  </script>
  <script src="<?= e($base) ?>/assets/js/results.js"></script>
</body>
</html>
