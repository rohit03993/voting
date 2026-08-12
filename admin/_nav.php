<?php
declare(strict_types=1);
$adminPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<header class="admin-nav">
  <div class="container row between">
    <a class="brand" href="index.php">HCS Voting Admin</a>
    <nav class="row gap wrap">
      <a class="<?= $adminPage === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
      <a class="<?= $adminPage === 'positions.php' ? 'active' : '' ?>" href="positions.php">Positions</a>
      <a class="<?= $adminPage === 'candidates.php' ? 'active' : '' ?>" href="candidates.php">Candidates</a>
      <a class="<?= $adminPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">Settings</a>
      <a class="<?= $adminPage === 'import.php' ? 'active' : '' ?>" href="import.php">Import CSV</a>
      <a href="index.php?logout=1">Logout</a>
    </nav>
  </div>
</header>
