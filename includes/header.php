<?php
require_once __DIR__ . '/functions.php';
ensure_legacy_assets();
$pageTitle = $pageTitle ?? SITE_NAME . ' – ' . SITE_TAGLINE;
$pageDesc  = $pageDesc ?? SITE_TAGLINE;
$ogImg = media_url('og.jpg') ? abs_url('media.php?f=og.jpg') : null;
$icon  = media_url('icon.jpg');
$logo  = logo_url();
$navPositions = db()->query('SELECT slug, title FROM positions ORDER BY sort')->fetchAll();
$curPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?><!doctype html>
<html lang="en-US">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="<?= BRAND ?>">
<meta name="msapplication-navbutton-color" content="<?= BRAND ?>">
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta property="og:title" content="<?= e($ogTitle ?? $pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:type" content="website">
<?php if ($ogImg): ?><meta property="og:image" content="<?= e($ogImg) ?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="<?= e($ogImg) ?>"><?php endif; ?>
<?php if ($icon): ?><link rel="icon" href="<?= e($icon) ?>"><link rel="apple-touch-icon" href="<?= e($icon) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=10">
</head>
<body>
<header class="hdr">
  <div class="hdr-in">
    <div class="hdr-logo">
      <button class="burger" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
      <a class="logo" href="<?= url('') ?>" title="<?= SITE_NAME ?>">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= SITE_NAME ?> Logo"><?php else: ?><span class="logo-txt">IMO DECIDES</span><?php endif; ?>
      </a>
    </div>
    <div class="hdr-mid">
      <form class="hdr-search" action="<?= url('search/') ?>" method="get">
        <input type="search" name="q" placeholder="Type Search Words" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search">
        <button type="submit" aria-label="Search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
      </form>
    </div>
    <div class="hdr-right">
      <a class="hbtn hbtn-green" href="<?= url('house-of-representative/') ?>">Vote Now</a>
      <a class="hbtn hbtn-grey" href="<?= url('search/') ?>">Search</a>
    </div>
  </div>
</header>
<?= $heroHtml ?? '' ?>
<div class="site">
  <aside class="lnav">
    <nav>
      <a class="<?= in_array($curPath, [url(''), url('home/'), '/']) ? 'on' : '' ?>" href="<?= url('') ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h5v-6h4v6h5v-8h3z"/></svg> Home</a>
      <?php foreach ($navPositions as $np): ?>
        <a class="<?= rtrim($curPath, '/') === rtrim(url($np['slug']), '/') ? 'on' : '' ?>" href="<?= url($np['slug'] . '/') ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h16v4H4zM4 10h16v4H4zM4 16h16v4H4z" opacity=".8"/></svg> <?= e($np['title']) ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main class="mid">
<?php if ($f = flash()): ?><div class="flash <?= e($f[1]) ?>"><?= e($f[0]) ?></div><?php endif; ?>
