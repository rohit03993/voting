<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

if (isset($_GET['logout'])) {
    admin_logout();
    redirect('login.php');
}

$election = current_election($pdo);
$base = base_url($config);
$flash = take_flash();

$counts = ['positions' => 0, 'candidates' => 0, 'ballots' => 0];
if ($election) {
    $eid = (int) $election['id'];
    $counts['positions'] = (int) $pdo->query("SELECT COUNT(*) FROM positions WHERE election_id = {$eid}")->fetchColumn();
    $counts['candidates'] = (int) $pdo->query("SELECT COUNT(*) FROM candidates WHERE election_id = {$eid} AND is_active = 1")->fetchColumn();
    $counts['ballots'] = (int) $pdo->query("SELECT COUNT(*) FROM ballots WHERE election_id = {$eid}")->fetchColumn();
}

// Status actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null) && $election) {
    $action = $_POST['action'] ?? '';
    if ($action === 'publish') {
        $stmt = $pdo->prepare('UPDATE elections SET status = "live", published_at = NOW() WHERE id = ?');
        $stmt->execute([(int) $election['id']]);
        flash('ok', 'Election published. Voting is now LIVE.');
        redirect('index.php');
    }
    if ($action === 'close') {
        $stmt = $pdo->prepare('UPDATE elections SET status = "closed", closed_at = NOW() WHERE id = ?');
        $stmt->execute([(int) $election['id']]);
        flash('ok', 'Election closed. No more votes will be accepted.');
        redirect('index.php');
    }
    if ($action === 'reopen_draft') {
        $stmt = $pdo->prepare('UPDATE elections SET status = "draft", published_at = NULL, closed_at = NULL WHERE id = ?');
        $stmt->execute([(int) $election['id']]);
        flash('ok', 'Moved back to draft.');
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container">
  <h1>Dashboard</h1>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

  <?php if (!$election): ?>
    <div class="alert err">No election found. Re-run installer or create one.</div>
  <?php else: ?>
    <section class="panel">
      <div class="row between">
        <div>
          <h2><?= e($election['title']) ?></h2>
          <p>Status: <span class="badge <?= e($election['status']) ?>"><?= e(strtoupper($election['status'])) ?></span></p>
        </div>
        <form method="post" class="row gap">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <?php if ($election['status'] === 'draft'): ?>
            <button class="btn" name="action" value="publish" type="submit">Publish (Go Live)</button>
          <?php elseif ($election['status'] === 'live'): ?>
            <button class="btn danger" name="action" value="close" type="submit">Close Voting</button>
          <?php else: ?>
            <button class="btn secondary" name="action" value="reopen_draft" type="submit">Back to Draft</button>
          <?php endif; ?>
        </form>
      </div>
      <div class="stats">
        <div class="stat"><strong><?= $counts['positions'] ?></strong><span>Positions</span></div>
        <div class="stat"><strong><?= $counts['candidates'] ?></strong><span>Candidates</span></div>
        <div class="stat"><strong><?= $counts['ballots'] ?></strong><span>Ballots cast</span></div>
      </div>
    </section>

    <section class="panel">
      <h2>Voting Links</h2>
      <div class="stack">
        <label>Students &amp; Staff
          <input readonly value="<?= e($base . '/') ?>" onclick="this.select()">
        </label>
        <label>Principal (private)
          <input readonly value="<?= e($base . '/p/?t=' . $election['principal_token']) ?>" onclick="this.select()">
        </label>
        <label>Director (private)
          <input readonly value="<?= e($base . '/d/?t=' . $election['director_token']) ?>" onclick="this.select()">
        </label>
        <label>Live Results
          <input readonly value="<?= e($base . '/results/') ?>" onclick="this.select()">
        </label>
      </div>
      <p class="muted">Share Principal / Director links only with those people. Passcodes are set in Settings.</p>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
