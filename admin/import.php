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
$flash = null;
$report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf'] ?? null)) {
    try {
        if (empty($_FILES['csv']['tmp_name'])) {
            throw new RuntimeException('Please upload a CSV file.');
        }
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$fh) {
            throw new RuntimeException('Could not read CSV.');
        }

        $header = fgetcsv($fh);
        if (!$header) {
            throw new RuntimeException('CSV is empty.');
        }
        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);
        $map = [
            'name' => array_search('name', $header, true),
            'class' => array_search('class', $header, true),
            'position' => array_search('position', $header, true),
            'image' => array_search('imageurl', $header, true),
        ];
        if ($map['name'] === false || $map['position'] === false) {
            throw new RuntimeException('CSV must have Name and Position columns.');
        }
        if ($map['class'] === false) {
            $map['class'] = array_search('class_name', $header, true);
        }
        if ($map['image'] === false) {
            $map['image'] = array_search('image', $header, true);
            if ($map['image'] === false) {
                $map['image'] = array_search('photo', $header, true);
            }
        }

        $posCache = [];
        $posFind = $pdo->prepare('SELECT id FROM positions WHERE election_id = ? AND name = ? LIMIT 1');
        $posAdd = $pdo->prepare('INSERT INTO positions (election_id, name, sort_order) VALUES (?, ?, ?)');
        $maxOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM positions WHERE election_id = {$eid}")->fetchColumn();
        $candAdd = $pdo->prepare(
            'INSERT INTO candidates (election_id, position_id, name, class_name, photo) VALUES (?, ?, ?, ?, ?)'
        );

        $added = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $name = trim((string) ($row[$map['name']] ?? ''));
            $position = trim((string) ($row[$map['position']] ?? ''));
            if ($name === '' || $position === '') {
                continue;
            }
            $class = $map['class'] !== false ? trim((string) ($row[$map['class']] ?? '')) : '';
            $image = $map['image'] !== false ? trim((string) ($row[$map['image']] ?? '')) : '';

            if (!isset($posCache[$position])) {
                $posFind->execute([$eid, $position]);
                $found = $posFind->fetch();
                if ($found) {
                    $posCache[$position] = (int) $found['id'];
                } else {
                    $maxOrder++;
                    $posAdd->execute([$eid, $position, $maxOrder]);
                    $posCache[$position] = (int) $pdo->lastInsertId();
                    $report[] = "Created position: {$position}";
                }
            }

            $candAdd->execute([$eid, $posCache[$position], $name, $class, $image]);
            $added++;
        }
        fclose($fh);
        flash('ok', "Imported {$added} candidates.");
        redirect('candidates.php');
    } catch (Throwable $e) {
        $flash = ['type' => 'err', 'message' => $e->getMessage()];
    }
}

if (!$flash) {
    $flash = take_flash();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Import CSV — HCS Voting</title>
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container">
  <h1>Import candidates (CSV)</h1>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

  <section class="panel">
    <p>Export your old Google Sheet as CSV with columns: <strong>Name, Class, Position, ImageURL</strong>.</p>
    <p class="muted">ImageURL can be a full https link (e.g. existing WordPress photo URLs) or leave blank and upload photos later.</p>
    <form method="post" enctype="multipart/form-data" class="stack">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label>CSV file <input type="file" name="csv" accept=".csv,text/csv" required></label>
      <button class="btn" type="submit">Import</button>
    </form>
  </section>
</main>
</body>
</html>
