<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
ensure_voter_cookie();
$election = current_election($pdo);
$appName = $config['app']['name'] ?? 'HCS Voting';
$status = $election['status'] ?? 'none';
$title = $election['title'] ?? $appName;
$base = base_url($config);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css">
</head>
<body class="vote-body">
  <header class="vote-header">
    <div class="container">
      <div class="brand-lockup">Horizon Competition School</div>
      <h1><?= e($title) ?></h1>
      <p class="muted">Select your voter type, then choose one candidate for each position.</p>
    </div>
  </header>

  <main class="container">
    <?php if ($status !== 'live'): ?>
      <div class="panel center">
        <h2>Voting is not open</h2>
        <p class="muted">Current status: <strong><?= e(strtoupper((string) $status)) ?></strong>. Please wait for staff to publish.</p>
        <p><a class="btn secondary" href="<?= e($base) ?>/results/">View results page</a></p>
      </div>
    <?php else: ?>
      <div id="instructionMsg" class="alert warn">Please select one candidate from each category to enable Submit.</div>

      <form id="votingForm" class="panel">
        <label class="stack">
          <span>I am a</span>
          <select id="voterType" required>
            <option value="">— Select Voter Type —</option>
            <option value="student">Student</option>
            <option value="staff">Staff / Teacher</option>
          </select>
        </label>

        <div id="positions" class="positions"></div>

        <div id="spinner" class="spinner" hidden>Submitting…</div>
        <div id="confirmation" class="alert ok" hidden>Your vote has been submitted successfully!</div>
        <button type="submit" id="submitBtn" class="btn" hidden>Submit Vote</button>
      </form>
    <?php endif; ?>
  </main>

  <script>
    window.HCS_VOTE = {
      api: <?= json_encode($base . '/api/') ?>,
      voterType: null,
      accessToken: null,
      requirePasscode: false
    };
  </script>
  <?php if ($status === 'live'): ?>
  <script src="<?= e($base) ?>/assets/js/vote.js"></script>
  <?php endif; ?>
</body>
</html>
