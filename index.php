<?php
require_once __DIR__ . '/includes/functions.php';
$positions = db()->query("SELECT p.*,
    (SELECT COUNT(*) FROM polls pl WHERE pl.position_id = p.id) AS polls,
    (SELECT COUNT(*) FROM votes v JOIN polls pl ON pl.id = v.poll_id WHERE pl.position_id = p.id) AS votes
    FROM positions p ORDER BY sort")->fetchAll();
$totalVotes = (int)db()->query('SELECT COUNT(*) FROM votes')->fetchColumn();
$totalCands = (int)db()->query('SELECT COUNT(*) FROM candidates')->fetchColumn();
$latest = db()->query("SELECT pl.*, (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = pl.id) AS cands
    FROM polls pl WHERE (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = pl.id) > 0
    ORDER BY pl.created_at DESC LIMIT 4")->fetchAll();
$icons = ['governor' => '🏛️', 'senate' => '⚖️', 'house-of-representative' => '🏢', 'house-of-assembly' => '🗳️'];
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="wrap hero-in">
    <span class="eyebrow">IMO DECIDES 2027</span>
    <h1>Who Should Lead in Imo?</h1>
    <p>Explore the candidates, compare your options and participate in Imo State’s public opinion polls.</p>
    <div class="hero-stats">
      <div><b><?= number_format($totalCands) ?></b><span>Candidates</span></div>
      <div><b><?= number_format($totalVotes) ?></b><span>Votes cast</span></div>
      <div><b>27</b><span>LGAs</span></div>
    </div>
  </div>
</section>

<section class="wrap section">
  <div class="section-head">
    <h2>Choose a Position</h2>
    <p>Select an election position below to explore the candidates and participate in the poll.</p>
  </div>
  <div class="grid positions">
    <?php foreach ($positions as $p): ?>
      <a class="card position" href="<?= position_url($p) ?>">
        <span class="pos-icon"><?= $icons[$p['slug']] ?? '🗳️' ?></span>
        <h3><?= e($p['title']) ?></h3>
        <p><?= e($p['tagline']) ?></p>
        <div class="pos-meta"><span><?= (int)$p['polls'] ?> polls</span><span><?= number_format($p['votes']) ?> votes</span></div>
        <span class="btn btn-sm">Explore Candidates →</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($latest): ?>
<section class="wrap section">
  <div class="section-head"><h2>Active Polls</h2></div>
  <div class="grid polls-grid">
    <?php foreach ($latest as $pl): ?>
      <a class="card poll-card" href="<?= poll_url($pl) ?>">
        <h3><?= e($pl['heading']) ?></h3>
        <div class="pos-meta"><span><?= (int)$pl['cands'] ?> candidates</span><span><?= compact_num((int)$pl['views']) ?> views</span></div>
        <span class="link">Vote now →</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
