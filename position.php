<?php
require_once __DIR__ . '/includes/functions.php';
$st = db()->prepare('SELECT * FROM positions WHERE slug = ?');
$st->execute([$_GET['slug'] ?? '']);
$pos = $st->fetch();
if (!$pos) { http_response_code(404); $pageTitle = 'Not found'; require __DIR__ . '/includes/header.php'; echo '<div class="wrap section empty">Page not found.</div>'; require __DIR__ . '/includes/footer.php'; exit; }

$st = db()->prepare("SELECT pl.*,
    (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = pl.id) AS cands,
    (SELECT COUNT(*) FROM votes v WHERE v.poll_id = pl.id) + (SELECT COALESCE(SUM(c2.base_votes),0) FROM candidates c2 WHERE c2.poll_id = pl.id) AS votes
    FROM polls pl WHERE pl.position_id = ? ORDER BY pl.sort, pl.name");
$st->execute([$pos['id']]);
$polls = $st->fetchAll();

$pageTitle = $pos['title'] . ' – ' . SITE_NAME;
$pageDesc = $pos['tagline'] . ' Explore candidates by constituency and vote in the poll.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="wrap">
    <div class="crumbs"><a href="<?= url('') ?>">Home</a> / <?= e($pos['title']) ?></div>
    <h1><?= e($pos['title']) ?></h1>
    <p><?= e($pos['tagline']) ?></p>
  </div>
</section>
<section class="wrap section">
  <?php if (!$polls): ?>
    <div class="empty card">Candidates for this position will be published soon. Check back shortly.</div>
  <?php else: ?>
  <div class="grid polls-grid">
    <?php foreach ($polls as $pl): ?>
      <a class="card poll-card" href="<?= poll_url($pl) ?>">
        <h3><?= e($pl['name']) ?></h3>
        <div class="pos-meta">
          <span><?= (int)$pl['cands'] ?: 'No' ?> candidates</span>
          <span><?= number_format($pl['votes']) ?> votes</span>
        </div>
        <span class="btn btn-sm <?= $pl['cands'] ? '' : 'btn-ghost' ?>"><?= $pl['cands'] ? 'Explore Candidates →' : 'Coming soon' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
