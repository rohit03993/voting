<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
ensure_voter_cookie();

$election = current_election($pdo);
$base = base_url($config);
$token = (string) ($_GET['t'] ?? '');
$ok = $election
    && $election['status'] === 'live'
    && hash_equals((string) $election['principal_token'], $token);
$title = $election['title'] ?? 'HCS Voting';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Principal Vote — <?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css?v=4">
</head>
<body class="vote-body">
  <?php
    $pageTitle = $title;
    $title = 'Principal Voting';
    $subtitle = $pageTitle;
    include dirname(__DIR__) . '/includes/site-header.php';
  ?>
  <main class="container vote-main">
    <?php if (!$ok): ?>
      <div class="panel center">
        <h2>Link not valid</h2>
        <p class="muted">This Principal voting link is invalid or voting is not live.</p>
      </div>
    <?php else: ?>
      <form id="votingForm" class="wizard-shell">
        <div class="wizard-progress-wrap">
          <div class="wizard-progress-meta">
            <span id="progressLabel">Enter passcode to start</span>
            <span id="progressCount">0 / 0</span>
          </div>
          <div class="wizard-progress-bar" aria-hidden="true">
            <div id="progressFill" class="wizard-progress-fill"></div>
          </div>
        </div>

        <div id="instructionMsg" class="alert warn">Enter the principal passcode, then vote one position at a time.</div>

        <section id="stepStart" class="panel wizard-step">
          <label class="stack">Passcode
            <input type="password" id="passcode" maxlength="12" required placeholder="Enter principal passcode">
          </label>
          <div id="passcodeError" class="alert err" hidden>Incorrect passcode. Please try again.</div>
          <button type="button" class="btn" id="startBtn">Start voting</button>
        </section>

        <section id="stepVote" class="panel wizard-step" hidden>
          <h2 id="stepTitle" class="wizard-step-title">Position</h2>
          <div id="candidateGrid" class="candidate-section candidate-section-compact"></div>
          <div class="wizard-nav">
            <button type="button" class="btn secondary" id="prevBtn">Back</button>
            <button type="button" class="btn" id="nextBtn" disabled>Next</button>
          </div>
        </section>

        <section id="stepReview" class="panel wizard-step" hidden>
          <h2 class="wizard-step-title">Review your choices</h2>
          <div id="reviewList" class="review-list"></div>
          <div class="wizard-nav">
            <button type="button" class="btn secondary" id="reviewBackBtn">Back</button>
            <button type="submit" class="btn" id="submitBtn">Submit Principal Vote</button>
          </div>
        </section>

        <div id="spinner" class="spinner" hidden>Submitting…</div>
        <div id="confirmation" class="alert ok" hidden>Your vote has been submitted successfully!</div>
      </form>
    <?php endif; ?>
  </main>
  <?php if ($ok): ?>
  <script>
    window.HCS_VOTE = {
      api: <?= json_encode($base . '/api/') ?>,
      voterType: 'principal',
      accessToken: <?= json_encode($token) ?>,
      requirePasscode: true
    };
  </script>
  <script src="<?= e($base) ?>/assets/js/vote.js?v=5"></script>
  <?php endif; ?>
</body>
</html>
