<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$election = current_election($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $form = $_POST['form'] ?? 'settings';

    // -------- Danger zone: reset (admin only) --------
    if ($form === 'reset' && $election) {
        $mode = $_POST['reset_mode'] ?? '';
        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        $eid = (int) $election['id'];

        if ($confirm !== 'RESET') {
            flash('err', 'Type RESET in capital letters to confirm.');
            redirect('settings.php');
        }

        try {
            $pdo->beginTransaction();

            if ($mode === 'votes') {
                // Keep candidates & positions; wipe all ballots/votes
                $pdo->prepare('DELETE FROM votes WHERE election_id = ?')->execute([$eid]);
                $pdo->prepare('DELETE FROM ballots WHERE election_id = ?')->execute([$eid]);
                $pdo->prepare(
                    'UPDATE elections SET status = "draft", published_at = NULL, closed_at = NULL WHERE id = ?'
                )->execute([$eid]);
                $pdo->commit();
                flash('ok', 'All votes cleared. Election set back to Draft. Candidates kept.');
                redirect('settings.php');
            }

            if ($mode === 'full') {
                // Wipe votes, candidates, positions; recreate default positions; new tokens; draft
                $pdo->prepare('DELETE FROM votes WHERE election_id = ?')->execute([$eid]);
                $pdo->prepare('DELETE FROM ballots WHERE election_id = ?')->execute([$eid]);
                $pdo->prepare('DELETE FROM candidates WHERE election_id = ?')->execute([$eid]);
                $pdo->prepare('DELETE FROM positions WHERE election_id = ?')->execute([$eid]);

                $posStmt = $pdo->prepare(
                    'INSERT INTO positions (election_id, name, sort_order) VALUES (?, ?, ?)'
                );
                foreach (default_positions() as $i => $name) {
                    $posStmt->execute([$eid, $name, $i + 1]);
                }

                $pdo->prepare(
                    'UPDATE elections
                     SET status = "draft",
                         published_at = NULL,
                         closed_at = NULL,
                         principal_token = ?,
                         director_token = ?
                     WHERE id = ?'
                )->execute([random_token(16), random_token(16), $eid]);

                $pdo->commit();
                flash('ok', 'Full reset done. Votes + candidates cleared. Default positions restored. New Principal/Director links generated. Status: Draft.');
                redirect('settings.php');
            }

            $pdo->rollBack();
            flash('err', 'Unknown reset option.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('err', 'Reset failed: ' . $e->getMessage());
        }
        redirect('settings.php');
    }

    // -------- Demo seed (admin only) --------
    if ($form === 'seed' && $election) {
        $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($confirm !== 'SEED') {
            flash('err', 'Type SEED in capital letters to confirm.');
            redirect('settings.php');
        }
        try {
            $pdo->beginTransaction();
            $result = seed_demo_candidates($pdo, (int) $election['id'], true);
            $pdo->commit();
            flash(
                'ok',
                "Demo data loaded: {$result['candidates']} candidates across {$result['positions']} positions. Status set to Draft. Publish when ready."
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('err', 'Seed failed: ' . $e->getMessage());
        }
        redirect('settings.php');
    }

    // -------- Normal settings --------
    $title = trim((string) ($_POST['title'] ?? ''));
    $principal = trim((string) ($_POST['principal_passcode'] ?? ''));
    $director = trim((string) ($_POST['director_passcode'] ?? ''));

    if ($election && $title !== '') {
        $stmt = $pdo->prepare('UPDATE elections SET title = ? WHERE id = ?');
        $stmt->execute([$title, (int) $election['id']]);
    }
    if ($principal !== '') {
        setting_set($pdo, 'principal_passcode', $principal);
    }
    if ($director !== '') {
        setting_set($pdo, 'director_passcode', $director);
    }

    if (!empty($_POST['regen_tokens']) && $election) {
        $stmt = $pdo->prepare('UPDATE elections SET principal_token = ?, director_token = ? WHERE id = ?');
        $stmt->execute([random_token(16), random_token(16), (int) $election['id']]);
        flash('ok', 'Private links regenerated. Old Principal/Director links no longer work.');
    } else {
        flash('ok', 'Settings saved.');
    }
    redirect('settings.php');
}

$election = current_election($pdo);
$principal = setting_get($pdo, 'principal_passcode', $config['security']['principal_passcode'] ?? '');
$director = setting_get($pdo, 'director_passcode', $config['security']['director_passcode'] ?? '');
$flash = take_flash();

$ballotCount = 0;
$candidateCount = 0;
if ($election) {
    $eid = (int) $election['id'];
    $ballotCount = (int) $pdo->query("SELECT COUNT(*) FROM ballots WHERE election_id = {$eid}")->fetchColumn();
    $candidateCount = (int) $pdo->query("SELECT COUNT(*) FROM candidates WHERE election_id = {$eid}")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings — HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css?v=2">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container">
  <h1>Settings</h1>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

  <section class="panel">
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form" value="settings">
      <label>Election title
        <input name="title" value="<?= e($election['title'] ?? '') ?>" required>
      </label>
      <label>Principal passcode
        <input name="principal_passcode" value="<?= e($principal) ?>" required>
      </label>
      <label>Director passcode
        <input name="director_passcode" value="<?= e($director) ?>" required>
      </label>
      <label class="check">
        <input type="checkbox" name="regen_tokens" value="1">
        Regenerate Principal &amp; Director private link tokens
      </label>
      <button class="btn" type="submit">Save settings</button>
    </form>
  </section>

  <section class="panel">
    <h2>Demo data</h2>
    <p class="muted">Adds <strong>5–10 students per position</strong> with placeholder photos. Replaces current candidates and clears votes. Sets election to Draft.</p>
    <form method="post" class="stack" onsubmit="return confirm('Replace all candidates with demo data and clear votes?');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form" value="seed">
      <label>Type <code>SEED</code> to confirm
        <input name="confirm_text" placeholder="SEED" autocomplete="off" required>
      </label>
      <button class="btn" type="submit">Load demo candidates</button>
    </form>
  </section>

  <section class="panel danger-zone">
    <h2>Danger zone — Reset (Admin only)</h2>
    <p class="muted">Current: <strong><?= (int) $ballotCount ?></strong> ballots · <strong><?= (int) $candidateCount ?></strong> candidates</p>

    <form method="post" class="stack" onsubmit="return confirm('This cannot be undone. Continue?');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="form" value="reset">

      <label>What to reset
        <select name="reset_mode" required>
          <option value="">— Choose —</option>
          <option value="votes">Clear all votes only (keep candidates &amp; photos)</option>
          <option value="full">Full reset (delete votes + all candidates, restore default positions)</option>
        </select>
      </label>

      <label>Type <code>RESET</code> to confirm
        <input name="confirm_text" placeholder="RESET" autocomplete="off" required>
      </label>

      <button class="btn danger" type="submit">Reset now</button>
    </form>
    <p class="muted" style="margin-top:.8rem">
      After reset, election goes back to <strong>Draft</strong>. Publish again when ready.
      Full reset also creates new Principal/Director links.
    </p>
  </section>
</main>
</body>
</html>
