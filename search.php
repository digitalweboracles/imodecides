<?php
require_once __DIR__ . '/includes/functions.php';
$q = trim($_GET['q'] ?? '');
$cands = $polls = [];
if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $st = db()->prepare('SELECT c.name, c.party, pl.slug, pl.heading FROM candidates c JOIN polls pl ON pl.id = c.poll_id
                         WHERE c.name LIKE ? OR c.party LIKE ? ORDER BY c.name LIMIT 50');
    $st->execute([$like, $like]);
    $cands = $st->fetchAll();
    $st = db()->prepare('SELECT slug, heading FROM polls WHERE name LIKE ? OR heading LIKE ? LIMIT 20');
    $st->execute([$like, $like]);
    $polls = $st->fetchAll();
}
$pageTitle = 'Search – ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<section class="page-head"><div class="wrap"><h1>Search</h1>
  <form action="<?= url('search/') ?>" class="big-search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Candidate, party or constituency" autofocus><button class="btn">Search</button></form>
</div></section>
<section class="wrap section">
  <?php if ($q && !$cands && !$polls): ?><div class="card empty">No results for “<?= e($q) ?>”.</div><?php endif; ?>
  <?php if ($polls): ?><h2 class="h-sm">Constituencies</h2><div class="grid polls-grid">
    <?php foreach ($polls as $p): ?><a class="card poll-card" href="<?= poll_url($p) ?>"><h3><?= e($p['heading']) ?></h3><span class="link">Open poll →</span></a><?php endforeach; ?>
  </div><?php endif; ?>
  <?php if ($cands): ?><h2 class="h-sm">Candidates</h2><div class="card"><ul class="results-list">
    <?php foreach ($cands as $c): ?><li><a href="<?= poll_url($c) ?>"><b><?= e($c['name']) ?></b> <span class="party sm" style="--pc: <?= party_color($c['party']) ?>"><?= e($c['party']) ?></span><span class="muted small"><?= e($c['heading']) ?></span></a></li><?php endforeach; ?>
  </ul></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
