<?php
declare(strict_types=1);
/** Shared public header. Expects $base, $title, optional $subtitle, $subtitleId */
$subtitle = $subtitle ?? '';
$subtitleId = $subtitleId ?? '';
$logoUrl = rtrim((string) $base, '/') . '/assets/img/logo-horizon.png';
?>
<header class="vote-header">
  <div class="container vote-header-inner">
    <a class="brand-logo" href="<?= e(rtrim((string) $base, '/') . '/') ?>" aria-label="Horizon Competition School">
      <img src="<?= e($logoUrl) ?>" alt="Horizon Competition School">
    </a>
    <div class="vote-heading">
      <h1><?= e($title) ?></h1>
      <?php if ($subtitle !== ''): ?>
        <p class="muted"<?= $subtitleId !== '' ? ' id="' . e($subtitleId) . '"' : '' ?>><?= e($subtitle) ?></p>
      <?php endif; ?>
    </div>
  </div>
</header>
