<?php
require_once __DIR__ . '/functions.php';
ensure_legacy_assets();
$pageTitle = $pageTitle ?? SITE_NAME . ' – ' . SITE_TAGLINE;
$pageDesc  = $pageDesc ?? 'Explore the candidates, compare your options and participate in Imo State’s public opinion polls.';
$ogImg = media_url('og.jpg') ? abs_url('media.php?f=og.jpg') : null;
$icon  = media_url('icon.jpg');
$logo  = logo_url();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="<?= BRAND ?>">
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:type" content="website">
<?php if ($ogImg): ?><meta property="og:image" content="<?= e($ogImg) ?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="<?= e($ogImg) ?>"><?php endif; ?>
<?php if ($icon): ?><link rel="icon" href="<?= e($icon) ?>"><link rel="apple-touch-icon" href="<?= e($icon) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=3">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="<?= url('') ?>">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= SITE_NAME ?>"><?php else: ?><span class="brand-mark">ID</span><?php endif; ?>
      <span class="brand-text">Imo <b>Decides</b></span>
    </a>
    <nav class="nav" id="nav">
      <a href="<?= url('') ?>">Home</a>
      <a href="<?= url('governor/') ?>">Governor</a>
      <a href="<?= url('senate/') ?>">Senate</a>
      <a href="<?= url('house-of-representative/') ?>">Reps</a>
      <a href="<?= url('house-of-assembly/') ?>">Assembly</a>
    </nav>
    <form class="search" action="<?= url('search/') ?>" method="get">
      <input type="search" name="q" placeholder="Search candidates…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search">
    </form>
    <button class="burger" aria-label="Menu" onclick="document.getElementById('nav').classList.toggle('open')">☰</button>
  </div>
</header>
<?php if ($f = flash()): ?><div class="wrap"><div class="flash <?= e($f[1]) ?>"><?= e($f[0]) ?></div></div><?php endif; ?>
<main>
