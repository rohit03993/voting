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

$posStmt = $pdo->prepare('SELECT id, name FROM positions WHERE election_id = ? ORDER BY sort_order');
$posStmt->execute([$eid]);
$allPositions = $posStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $class = trim((string) ($_POST['class_name'] ?? ''));
            $positionId = (int) ($_POST['position_id'] ?? 0);
            if ($name === '' || $positionId <= 0) {
                throw new RuntimeException('Name and position are required.');
            }

            $photoName = '';
            if (!empty($_FILES['photo']['name'])) {
                $photoName = upload_photo($_FILES['photo']);
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare(
                    'INSERT INTO candidates (election_id, position_id, name, class_name, photo) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$eid, $positionId, $name, $class, $photoName]);
                flash('ok', 'Candidate added.');
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                if ($photoName !== '') {
                    $stmt = $pdo->prepare(
                        'UPDATE candidates SET position_id=?, name=?, class_name=?, photo=? WHERE id=? AND election_id=?'
                    );
                    $stmt->execute([$positionId, $name, $class, $photoName, $id, $eid]);
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE candidates SET position_id=?, name=?, class_name=? WHERE id=? AND election_id=?'
                    );
                    $stmt->execute([$positionId, $name, $class, $id, $eid]);
                }
                flash('ok', 'Candidate updated.');
            }
        }
        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE candidates SET is_active = 1 - is_active WHERE id = ? AND election_id = ?');
            $stmt->execute([$id, $eid]);
            flash('ok', 'Candidate visibility updated.');
        }
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM candidates WHERE id = ? AND election_id = ?');
            $stmt->execute([$id, $eid]);
            flash('ok', 'Candidate deleted.');
        }
    } catch (Throwable $e) {
        flash('err', $e->getMessage());
    }
    redirect('candidates.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM candidates WHERE id = ? AND election_id = ?');
    $s->execute([$editId, $eid]);
    $edit = $s->fetch() ?: null;
}

$list = $pdo->prepare(
    'SELECT c.*, p.name AS position_name
     FROM candidates c
     INNER JOIN positions p ON p.id = c.position_id
     WHERE c.election_id = ?
     ORDER BY p.sort_order, c.name'
);
$list->execute([$eid]);
$candidates = $list->fetchAll();
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Candidates — HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css?v=2">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container">
  <h1>Candidates</h1>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

  <section class="panel">
    <h2><?= $edit ? 'Edit candidate' : 'Add candidate' ?></h2>
    <form method="post" enctype="multipart/form-data" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
      <label>Full name <input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
      <label>Class <input name="class_name" value="<?= e($edit['class_name'] ?? '') ?>" placeholder="Class 12"></label>
      <label>Position
        <select name="position_id" required>
          <option value="">— Select —</option>
          <?php foreach ($allPositions as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= ((int) ($edit['position_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Photo <?= $edit ? '(leave empty to keep current)' : '' ?>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" <?= $edit ? '' : 'required' ?>>
      </label>
      <?php if ($edit && $edit['photo']): ?>
        <img class="thumb" src="<?= e(photo_url($config, $edit['photo'])) ?>" alt="">
      <?php endif; ?>
      <div class="row gap">
        <button class="btn" type="submit"><?= $edit ? 'Update' : 'Add candidate' ?></button>
        <?php if ($edit): ?><a class="btn secondary" href="candidates.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </section>

  <section class="panel">
    <table class="table">
      <thead><tr><th>Photo</th><th>Name</th><th>Class</th><th>Position</th><th>Active</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($candidates as $c): ?>
        <tr>
          <td><img class="thumb" src="<?= e(photo_url($config, $c['photo'])) ?>" alt=""></td>
          <td><?= e($c['name']) ?></td>
          <td><?= e($c['class_name']) ?></td>
          <td><?= e($c['position_name']) ?></td>
          <td><?= $c['is_active'] ? 'Yes' : 'No' ?></td>
          <td class="row gap wrap">
            <a class="btn secondary" href="?edit=<?= (int) $c['id'] ?>">Edit</a>
            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn secondary" type="submit">Toggle</button></form>
            <form method="post" onsubmit="return confirm('Delete this candidate?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn danger" type="submit">Delete</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>
