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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css?v=4">
</head>
<body class="vote-body">
  <?php
    $subtitle = 'One position at a time — quick and easy.';
    include __DIR__ . '/includes/site-header.php';
  ?>

  <main class="container vote-main">
    <?php if ($status !== 'live'): ?>
      <div class="panel center">
        <h2>Voting is not open</h2>
        <p class="muted">Current status: <strong><?= e(strtoupper((string) $status)) ?></strong>. Please wait for staff to publish.</p>
        <p><a class="btn secondary" href="<?= e($base) ?>/results/">View results page</a></p>
      </div>
    <?php else: ?>
      <form id="votingForm" class="wizard-shell">
        <div class="wizard-progress-wrap">
          <div class="wizard-progress-meta">
            <span id="progressLabel">Getting ready…</span>
            <span id="progressCount">0 / 0</span>
          </div>
          <div class="wizard-progress-bar" aria-hidden="true">
            <div id="progressFill" class="wizard-progress-fill"></div>
          </div>
        </div>

        <div id="instructionMsg" class="alert warn">Select Student or Staff to start.</div>

        <section id="stepStart" class="panel wizard-step">
          <label class="stack">
            <span>I am a</span>
            <select id="voterType" required>
              <option value="">— Select Voter Type —</option>
              <option value="student">Student</option>
              <option value="staff">Staff / Teacher</option>
            </select>
          </label>
          <button type="button" class="btn" id="startBtn" disabled>Start voting</button>
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
            <button type="submit" class="btn" id="submitBtn">Submit Vote</button>
          </div>
        </section>

        <div id="spinner" class="spinner" hidden>Submitting…</div>
        <div id="confirmation" class="alert ok" hidden>Your vote has been submitted successfully!</div>
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
  <script src="<?= e($base) ?>/assets/js/vote.js?v=5"></script>
  <?php endif; ?>
</body>
</html>
