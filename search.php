<?php
require_once __DIR__ . '/includes/functions.php';
$q = trim($_GET['q'] ?? '');
$cands = $polls = [];
if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';
    $st = db()->prepare('SELECT c.name, c.party, pl.slug, pl.heading FROM candidates c JOIN polls pl ON pl.id = c.poll_id
                         WHERE c.name LIKE ? OR c.party LIKE ? ORDER BY c.name LIMIT 60');
    $st->execute([$like, $like]);
    $cands = $st->fetchAll();
    $st = db()->prepare('SELECT slug, heading FROM polls WHERE name LIKE ? OR heading LIKE ? LIMIT 20');
    $st->execute([$like, $like]);
    $polls = $st->fetchAll();
}
$pageTitle = 'Search – ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> <span>/</span> Search</div>
  <div class="page-box">
    <h1 class="page-h">Search</h1>
    <form action="<?= url('search/') ?>" class="big-search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Candidate, party or constituency" autofocus><button class="btn-green">Search</button></form>
    <?php if ($q && !$cands && !$polls): ?><p class="empty">No results for “<?= e($q) ?>”.</p><?php endif; ?>
    <?php if ($polls): ?><h3 style="margin:26px 0 10px">Questions</h3><ul class="results-list">
      <?php foreach ($polls as $p): ?><li><a href="<?= poll_url($p) ?>"><?= e($p['heading']) ?></a></li><?php endforeach; ?>
    </ul><?php endif; ?>
    <?php if ($cands): ?><h3 style="margin:26px 0 10px">Candidates</h3><ul class="results-list">
      <?php foreach ($cands as $c): ?><li><a href="<?= poll_url($c) ?>"><b><?= e($c['name']) ?></b> <span class="party"><?= e($c['party']) ?></span> <span class="muted-sm"><?= e($c['heading']) ?></span></a></li><?php endforeach; ?>
    </ul><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
