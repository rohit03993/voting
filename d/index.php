<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
ensure_voter_cookie();

$election = current_election($pdo);
$base = base_url($config);
$token = (string) ($_GET['t'] ?? '');
$ok = $election
    && $election['status'] === 'live'
    && hash_equals((string) $election['director_token'], $token);
$title = $election['title'] ?? 'HCS Voting';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Director Vote — <?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css?v=2">
</head>
<body class="vote-body">
  <?php
    $pageTitle = $title;
    $title = 'Director Voting';
    $subtitle = $pageTitle;
    include dirname(__DIR__) . '/includes/site-header.php';
  ?>
  <main class="container">
    <?php if (!$ok): ?>
      <div class="panel center">
        <h2>Link not valid</h2>
        <p class="muted">This Director voting link is invalid or voting is not live.</p>
      </div>
    <?php else: ?>
      <div id="instructionMsg" class="alert warn">Enter passcode, then select one candidate for each position.</div>
      <form id="votingForm" class="panel">
        <label class="stack">Passcode
          <input type="password" id="passcode" maxlength="12" required placeholder="Enter director passcode">
        </label>
        <div id="passcodeError" class="alert err" hidden>Incorrect passcode. Please try again.</div>
        <div id="positions" class="positions"></div>
        <div id="spinner" class="spinner" hidden>Submitting…</div>
        <div id="confirmation" class="alert ok" hidden>Your vote has been submitted successfully!</div>
        <button type="submit" id="submitBtn" class="btn" hidden>Submit Director Vote</button>
      </form>
    <?php endif; ?>
  </main>
  <?php if ($ok): ?>
  <script>
    window.HCS_VOTE = {
      api: <?= json_encode($base . '/api/') ?>,
      voterType: 'director',
      accessToken: <?= json_encode($token) ?>,
      requirePasscode: true
    };
  </script>
  <script src="<?= e($base) ?>/assets/js/vote.js"></script>
  <?php endif; ?>
</body>
</html>
