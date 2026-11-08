<?php
require_once __DIR__ . '/includes/functions.php';
$st = db()->prepare('SELECT * FROM positions WHERE slug = ?');
$st->execute([$_GET['slug'] ?? '']);
$pos = $st->fetch();
if (!$pos) { http_response_code(404); $pageTitle = 'Page not found – ' . SITE_NAME; require __DIR__ . '/includes/header.php'; echo '<div class="box page-box empty"><h1 class="big">404</h1><p>Oops! Page Not Found.</p></div>'; require __DIR__ . '/includes/footer.php'; exit; }

$st = db()->prepare("SELECT pl.*, (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = pl.id) AS cands
    FROM polls pl WHERE pl.position_id = ? ORDER BY pl.sort, pl.name");
$st->execute([$pos['id']]);
$polls = $st->fetchAll();

$titles = ['senate' => 'SENATE 2027', 'house-of-representative' => 'House of Representative'];
$pageH = $titles[$pos['slug']] ?? $pos['title'];
$pageTitle = $pageH . ' – ' . SITE_NAME;
$pageDesc = implode(' ', array_map(fn($p) => $p['name'] . ' Explore Candidates', array_slice($polls, 0, 2)));
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h5v-6h4v6h5v-8h3z"/></svg> Home</a> <span>/</span> <?= e($pageH) ?></div>
  <div class="page-box">
    <h1 class="page-h"><?= e($pageH) ?></h1>
    <?php if (!$polls): ?>
      <p class="empty">Candidates for this position will be published soon.</p>
    <?php else: ?>
    <div class="gcards g3">
      <?php foreach ($polls as $pl): ?>
        <div class="gcard">
          <h2><?= e($pl['name']) ?></h2>
          <a class="gbtn" href="<?= poll_url($pl) ?>"><?= $pl['cands'] ? 'Explore Candidates' : 'Coming Soon' ?></a>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php $shareUrl = abs_url($pos['slug'] . '/'); $shareTxt = $pageH; include __DIR__ . '/includes/share.php'; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
