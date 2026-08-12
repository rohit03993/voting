<?php
declare(strict_types=1);
$adminPage = basename($_SERVER['PHP_SELF'] ?? '');
$logoUrl = '../assets/img/logo-horizon.png';
?>
<header class="site-top">
  <div class="container site-top-inner">
    <p class="site-tagline">Nurturing excellence in academics &amp; character</p>
    <p class="site-top-links">HCS Student Council Voting</p>
  </div>
</header>

<header class="admin-nav">
  <div class="container admin-bar">
    <a class="brand-logo" href="index.php" aria-label="Horizon Competition School — Voting Admin">
      <img src="<?= e($logoUrl) ?>" alt="Horizon Competition School">
    </a>

    <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="adminMenu">
      <span></span><span></span><span></span>
    </button>

    <nav class="admin-menu" id="adminMenu">
      <a class="<?= $adminPage === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
      <a class="<?= $adminPage === 'positions.php' ? 'active' : '' ?>" href="positions.php">Positions</a>
      <a class="<?= $adminPage === 'candidates.php' ? 'active' : '' ?>" href="candidates.php">Candidates</a>
      <a class="<?= $adminPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">Settings</a>
      <a class="<?= $adminPage === 'import.php' ? 'active' : '' ?>" href="import.php">Import CSV</a>
      <a class="menu-logout" href="index.php?logout=1">Logout</a>
    </nav>
  </div>
</header>
<script>
(function () {
  var btn = document.getElementById('navToggle');
  var menu = document.getElementById('adminMenu');
  if (!btn || !menu) return;
  btn.addEventListener('click', function () {
    var open = menu.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.classList.toggle('is-open', open);
  });
})();
</script>
