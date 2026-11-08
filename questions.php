<?php
// Question feed — /questions/ and /home/ ("Imo Decides Latest Questions")
require_once __DIR__ . '/includes/functions.php';
$tab = $_GET['show'] ?? 'recent';
$order = [
    'recent'   => 'p.created_at DESC, p.id DESC',
    'answers'  => 'answers DESC, p.created_at DESC',
    'visited'  => 'p.views DESC',
    'voted'    => 'votes DESC',
][$tab] ?? 'p.created_at DESC, p.id DESC';
$perPage = 15; $page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)db()->query("SELECT COUNT(*) FROM polls p WHERE p.kind = 'question' OR (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = p.id) > 0")->fetchColumn();
$rows = db()->query("SELECT p.*, u.username, u.display_name, u.avatar,
    (SELECT COUNT(*) FROM comments m WHERE m.poll_id = p.id AND m.status = 'approved') AS answers,
    (SELECT COUNT(*) FROM votes v WHERE v.poll_id = p.id) + (SELECT COALESCE(SUM(c.base_votes),0) FROM candidates c WHERE c.poll_id = p.id) AS votes
    FROM polls p LEFT JOIN users u ON u.id = p.user_id
    WHERE p.kind = 'question' OR (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = p.id) > 0
    ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage))->fetchAll();
$pages = max(1, (int)ceil($total / $perPage));
$isHome = ($_GET['feed'] ?? '') === 'home';
$pageTitle = ($isHome ? 'Home' : 'Questions') . ' – ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> <span>/</span> <?= $isHome ? 'Home' : 'Questions' ?></div>
  <div class="ans-head" style="padding:16px 30px">
    <h3>Imo Decides Latest Questions</h3>
    <div class="ans-tabs">
      <?php foreach (['recent' => 'Recent Questions', 'answers' => 'Most Answered', 'visited' => 'Most Visited', 'voted' => 'Most Voted'] as $k => $v): ?>
        <a class="<?= $tab === $k ? 'on' : '' ?>" href="?show=<?= $k ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <ul class="qfeed">
    <?php foreach ($rows as $p): $av = !empty($p['avatar']) ? media_url($p['avatar']) : null; ?>
      <li>
        <img class="qf-av" src="<?= e($av ?: url('assets/img/avatar.svg')) ?>" alt="">
        <div class="qf-body">
          <div class="qf-meta"><a class="q-author" href="<?= profile_url($p['username'] ?? ADMIN_USER) ?>"><?= e($p['display_name'] ?? 'admin') ?></a> <span class="q-date">Asked: <span><?= nice_date($p['created_at']) ?></span></span></div>
          <h2 class="qf-t"><a href="<?= poll_url($p) ?>"><?= e($p['heading']) ?></a></h2>
          <?php if ($p['description']): ?><p class="qf-ex"><?= e(mb_strimwidth($p['description'], 0, 180, '…')) ?></p><?php endif; ?>
          <ul class="q-stats qf-stats">
            <li><a href="<?= poll_url($p) ?>#comments"><?= (int)$p['answers'] ?> Answer<?= $p['answers'] == 1 ? '' : 's' ?></a></li>
            <li><?= compact_num((int)$p['views']) ?> Views</li>
            <?php if ($p['votes']): ?><li><?= number_format($p['votes']) ?> Votes</li><?php endif; ?>
            <li><a href="<?= poll_url($p) ?>#respond">Answer</a></li>
          </ul>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="?show=<?= e($tab) ?>&page=<?= $i ?>"><?= $i ?></a><?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
