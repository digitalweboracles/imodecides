<?php
require_once __DIR__ . '/includes/functions.php';
$st = db()->prepare('SELECT pl.*, p.title AS pos_title, p.slug AS pos_slug FROM polls pl JOIN positions p ON p.id = pl.position_id WHERE pl.slug = ?');
$st->execute([$_GET['slug'] ?? '']);
$poll = $st->fetch();
if (!$poll) { http_response_code(404); $pageTitle = 'Page not found – ' . SITE_NAME; require __DIR__ . '/includes/header.php'; echo '<div class="box page-box empty"><h1 class="big">404</h1><p>Oops! Page Not Found.</p></div>'; require __DIR__ . '/includes/footer.php'; exit; }
$pid = (int)$poll['id'];

if (empty($_SESSION['viewed'][$pid])) {
    db()->prepare('UPDATE polls SET views = views + 1 WHERE id = ?')->execute([$pid]);
    $_SESSION['viewed'][$pid] = 1; $poll['views']++;
}

[$cands, $total] = poll_results($pid);
$myVote = has_voted($pid);
$byVotes = $cands; usort($byVotes, fn($a, $b) => $b['votes'] <=> $a['votes'] ?: $a['sort'] <=> $b['sort']);
$maxVotes = $byVotes[0]['votes'] ?? 0;
$openResults = $myVote !== null || isset($_GET['results']) || !$poll['is_open'];
$fullParty = $poll['pos_slug'] === 'senate';
$label = fn($c) => $fullParty && $c['party'] ? $c['name'] . ' — ' . party_full($c['party']) : ($c['party'] ? $c['name'] . ' - ' . $c['party'] : $c['name']);

$cs = db()->prepare("SELECT * FROM comments WHERE poll_id = ? AND status = 'approved' ORDER BY created_at ASC, id ASC");
$cs->execute([$pid]);
$all = $cs->fetchAll();
$top = array_values(array_filter($all, fn($c) => !$c['parent_id']));
$replies = [];
foreach ($all as $c) if ($c['parent_id']) $replies[$c['parent_id']][] = $c;
$sort = $_GET['show'] ?? 'votes';
if ($sort === 'recent') $top = array_reverse($top);
if ($sort === 'random') shuffle($top);

$pageTitle = $poll['heading'] . ' – ' . SITE_NAME;
$ogTitle = $poll['heading'];
$pageDesc = '';
$shareUrl = abs_url('question/' . $poll['slug'] . '/');
$shareTxt = $poll['heading'];
$begin = date('F j, Y', strtotime($poll['created_at']));
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs crumbs-flex">
    <div><a href="<?= url('') ?>"><svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 12h3v8h5v-6h4v6h5v-8h3z"/></svg> Home</a> <span>/</span> <a href="<?= url($poll['pos_slug'] . '/') ?>"><?= e($poll['pos_title']) ?></a> <span>/</span> <?= e($poll['name']) ?></div>
    <span class="badge-proc"><?= $poll['is_open'] ? '&#9889; In Process' : 'Closed' ?></span>
  </div>

  <article class="q">
    <div class="q-head">
      <span class="q-avatar"><img src="<?= url('assets/img/avatar.svg') ?>" alt="admin"></span>
      <div class="q-meta">
        <div><a class="q-author" href="<?= url('') ?>">admin</a> <span class="q-date">Asked: <span><?= $begin ?></span></span></div>
        <h1 class="q-title"><?= e($poll['heading']) ?></h1>
      </div>
    </div>

    <div class="q-body">
      <?php if ($poll['description']): ?><div class="q-desc"><?= nl2br(e($poll['description'])) ?></div><?php endif; ?>

      <?php if (!$cands): ?>
        <p class="muted">Candidates for this constituency will be published soon.</p>
      <?php else: ?>
      <div class="dem" id="poll">
        <div class="dem-title"><?= e($poll['name']) ?></div>

        <!-- vote screen -->
        <form class="dem-screen dem-vote" method="post" action="<?= url('vote.php') ?>" id="voteForm" <?= $openResults ? 'hidden' : '' ?>>
          <?= csrf_field() ?>
          <input type="hidden" name="poll_id" value="<?= $pid ?>">
          <ul>
            <?php foreach ($cands as $c): ?>
              <li><label><input type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" required> <span class="spot"></span> <?= e($label($c)) ?></label></li>
            <?php endforeach; ?>
          </ul>
          <div class="dem-bottom">
            <button class="dem-btn" type="submit" <?= $poll['is_open'] ? '' : 'disabled' ?>>Vote</button>
            <a class="dem-link" href="#poll" data-show="results">Results</a>
          </div>
        </form>

        <!-- results screen -->
        <div class="dem-screen dem-results" id="pollResults" <?= $openResults ? '' : 'hidden' ?>>
          <ul>
            <?php foreach ($byVotes as $c): $pct = $total ? round($c['votes'] * 100 / $total) : 0; $w = $maxVotes ? $c['votes'] * 100 / $maxVotes : 0; $mine = $myVote === (int)$c['id']; ?>
              <li class="<?= $c['votes'] === $maxVotes && $maxVotes > 0 ? 'win' : '' ?> <?= $mine ? 'mine' : '' ?>" title="<?= number_format($c['votes']) ?> vote<?= $c['votes'] == 1 ? '' : 's' ?> - <?= $pct ?>% of all votes">
                <div class="dem-label"><?= e($label($c)) ?> <?= $mine ? '<em class="yv">This is your vote.</em>' : '' ?></div>
                <div class="dem-graph"><div class="dem-fill" style="width:<?= $c['votes'] ? max(0.5, $pct) : 0 ?>%"></div><span class="dem-votes"><?= number_format($c['votes']) ?> vote<?= $c['votes'] == 1 ? '' : 's' ?></span><span class="dem-pct"><?= $pct ?>%</span></div>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="dem-info">
            <div>Total Votes: <?= number_format($total) ?></div>
            <div><?= $begin ?> - <?= date('F j, Y', strtotime(POLL_END)) ?></div>
          </div>
          <?php if ($myVote === null && $poll['is_open']): ?><div class="dem-bottom"><a class="dem-link" href="#poll" data-show="vote">Vote</a></div><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <ul class="q-stats">
      <li><a href="#comments"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M4 4h16v12H7l-3 3z"/></svg> <?= count($all) ?> Answer<?= count($all) == 1 ? '' : 's' ?></a></li>
      <li><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5C6 5 2 12 2 12s4 7 10 7 10-7 10-7-4-7-10-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/></svg> <?= compact_num((int)$poll['views']) ?> Views</li>
      <li><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M9 11V6a3 3 0 0 1 6 0v5h3l-6 7-6-7z"/></svg> <?= number_format($total) ?> Votes</li>
    </ul>
    <div class="q-foot"><span class="muted-sm">&#9888; Report</span><?php include __DIR__ . '/includes/share.php'; ?></div>
  </article>

  <div class="leave"><a class="btn-green wide" href="#respond" data-toggle-form>Leave an answer</a></div>
  <form class="cform" method="post" action="<?= url('comment.php') ?>" enctype="multipart/form-data" id="respond" hidden>
    <h3>Leave an answer <span class="replying" id="replyingTo" hidden>&mdash; replying to <b></b> <a href="#" id="cancelReply">Cancel reply</a></span></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="poll_id" value="<?= $pid ?>">
    <input type="hidden" name="parent_id" id="parentId" value="">
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
    <label>Name<span class="req">*</span><input type="text" name="name" required maxlength="60" value="<?= e($_SESSION['cname'] ?? '') ?>"></label>
    <label>Featured image <input type="file" name="image" accept="image/*"></label>
    <label>Answer<span class="req">*</span><textarea name="body" rows="6" required maxlength="2000"></textarea></label>
    <button class="btn-green" type="submit">Leave Your Answer</button>
  </form>

  <section class="answers" id="comments">
    <div class="ans-head">
      <h3><?= count($all) ?> Answer<?= count($all) == 1 ? '' : 's' ?></h3>
      <div class="ans-tabs">
        <?php foreach (['votes' => 'Voted', 'oldest' => 'Oldest', 'recent' => 'Recent', 'random' => 'Random'] as $k => $v): ?>
          <a class="<?= $sort === $k ? 'on' : '' ?>" href="?show=<?= $k ?>#comments"><?= $v ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (!$top): ?><p class="ans-empty">There are no answers yet. Be the first to answer.</p><?php endif; ?>
    <ol class="clist">
      <?php foreach ($top as $c): ?>
        <li class="cm" id="comment-<?= $c['id'] ?>">
          <?php include __DIR__ . '/includes/comment_item.php'; ?>
          <?php if (!empty($replies[$c['id']])): ?>
            <ol class="creplies">
              <?php foreach ($replies[$c['id']] as $rc): $c2 = $c; $c = $rc; ?>
                <li class="cm" id="comment-<?= $c['id'] ?>"><?php include __DIR__ . '/includes/comment_item.php'; ?></li>
              <?php $c = $c2; endforeach; ?>
            </ol>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
