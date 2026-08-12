<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$election = current_election($pdo);
if (!$election) {
    flash('err', 'No election found.');
    redirect('index.php');
}
$eid = (int) $election['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Position name is required.');
            }
            $max = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM positions WHERE election_id = {$eid}")->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO positions (election_id, name, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$eid, $name, $max + 1]);
            flash('ok', 'Position added.');
        }
        if ($action === 'rename') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $stmt = $pdo->prepare('UPDATE positions SET name = ? WHERE id = ? AND election_id = ?');
            $stmt->execute([$name, $id, $eid]);
            flash('ok', 'Position updated.');
        }
        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE positions SET is_active = 1 - is_active WHERE id = ? AND election_id = ?');
            $stmt->execute([$id, $eid]);
            flash('ok', 'Position visibility updated.');
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM positions WHERE id = ? AND election_id = ?');
            $stmt->execute([$id, $eid]);
            flash('ok', 'Position deleted.');
        }
        if ($action === 'move') {
            $id = (int) ($_POST['id'] ?? 0);
            $dir = $_POST['dir'] ?? 'up';
            $rows = $pdo->prepare('SELECT id, sort_order FROM positions WHERE election_id = ? ORDER BY sort_order');
            $rows->execute([$eid]);
            $list = $rows->fetchAll();
            $idx = array_search($id, array_map(fn($r) => (int) $r['id'], $list), true);
            if ($idx !== false) {
                $swapWith = $dir === 'up' ? $idx - 1 : $idx + 1;
                if (isset($list[$swapWith])) {
                    $a = $list[$idx];
                    $b = $list[$swapWith];
                    $u = $pdo->prepare('UPDATE positions SET sort_order = ? WHERE id = ?');
                    $u->execute([(int) $b['sort_order'], (int) $a['id']]);
                    $u->execute([(int) $a['sort_order'], (int) $b['id']]);
                }
            }
            flash('ok', 'Order updated.');
        }
    } catch (Throwable $e) {
        flash('err', $e->getMessage());
    }
    redirect('positions.php');
}

$positions = $pdo->prepare('SELECT * FROM positions WHERE election_id = ? ORDER BY sort_order');
$positions->execute([$eid]);
$positions = $positions->fetchAll();
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Positions — HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css?v=2">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container">
  <h1>Positions</h1>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

  <section class="panel">
    <h2>Add position</h2>
    <form method="post" class="row gap">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add">
      <input name="name" placeholder="e.g. Head Boy" required style="flex:1">
      <button class="btn" type="submit">Add</button>
    </form>
  </section>

  <section class="panel">
    <table class="table">
      <thead><tr><th>#</th><th>Name</th><th>Active</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($positions as $i => $p): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <form method="post" class="row gap">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input name="name" value="<?= e($p['name']) ?>" required style="flex:1">
              <button class="btn secondary" type="submit">Save</button>
            </form>
          </td>
          <td><?= $p['is_active'] ? 'Yes' : 'No' ?></td>
          <td class="row gap wrap">
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="dir" value="up"><button class="btn secondary" type="submit">↑</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="dir" value="down"><button class="btn secondary" type="submit">↓</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn secondary" type="submit">Toggle</button></form>
            <form method="post" onsubmit="return confirm('Delete this position and its candidates?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn danger" type="submit">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>
