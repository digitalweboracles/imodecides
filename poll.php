<?php
require_once __DIR__ . '/includes/functions.php';
$st = db()->prepare('SELECT pl.*, p.title AS pos_title, p.slug AS pos_slug FROM polls pl JOIN positions p ON p.id = pl.position_id WHERE pl.slug = ?');
$st->execute([$_GET['slug'] ?? '']);
$poll = $st->fetch();
if (!$poll) { http_response_code(404); $pageTitle = 'Not found'; require __DIR__ . '/includes/header.php'; echo '<div class="wrap section empty">Poll not found.</div>'; require __DIR__ . '/includes/footer.php'; exit; }
$pid = (int)$poll['id'];

// Count one view per session
if (empty($_SESSION['viewed'][$pid])) {
    db()->prepare('UPDATE polls SET views = views + 1 WHERE id = ?')->execute([$pid]);
    $_SESSION['viewed'][$pid] = 1; $poll['views']++;
}

[$cands, $total] = poll_results($pid);
$myVote = has_voted($pid);
$showResults = $myVote !== null || setting('results_public') === '1' || isset($_GET['results']);
$ranked = $cands; usort($ranked, fn($a, $b) => $b['votes'] <=> $a['votes']);

$cs = db()->prepare("SELECT * FROM comments WHERE poll_id = ? AND status = 'approved' ORDER BY created_at ASC");
$cs->execute([$pid]);
$all = $cs->fetchAll();
$top = array_filter($all, fn($c) => !$c['parent_id']);
$replies = [];
foreach ($all as $c) if ($c['parent_id']) $replies[$c['parent_id']][] = $c;
$sort = $_GET['show'] ?? 'oldest';
if ($sort === 'recent') $top = array_reverse($top);

$pageTitle = $poll['heading'] . ' – ' . SITE_NAME;
$pageDesc = 'Vote in the ' . $poll['heading'] . ' poll: ' . implode(', ', array_map(fn($c) => $c['name'] . ' (' . $c['party'] . ')', array_slice($cands, 0, 5))) . (count($cands) > 5 ? ' and more.' : '.');
$shareUrl = abs_url('question/' . $poll['slug'] . '/');
$shareTxt = $poll['heading'];
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="wrap">
    <div class="crumbs"><a href="<?= url('') ?>">Home</a> / <a href="<?= url($poll['pos_slug'] . '/') ?>"><?= e($poll['pos_title']) ?></a> / <?= e($poll['name']) ?></div>
    <h1><?= e($poll['heading']) ?></h1>
    <div class="meta-row">
      <span>📅 <?= nice_date($poll['created_at']) ?></span>
      <span>👁 <?= compact_num((int)$poll['views']) ?> views</span>
      <span>🗳 <?= number_format($total) ?> votes</span>
      <span>💬 <?= count($all) ?> answers</span>
      <span class="status <?= $poll['is_open'] ? 'open' : 'closed' ?>"><?= $poll['is_open'] ? 'Poll open' : 'Poll closed' ?></span>
    </div>
  </div>
</section>

<div class="wrap layout">
  <div class="main-col">
    <?php if ($poll['description']): ?><div class="card prose"><?= nl2br(e($poll['description'])) ?></div><?php endif; ?>

    <?php if (!$cands): ?>
      <div class="card empty">Candidates for this constituency will be published soon.</div>
    <?php else: ?>
    <form class="card vote-card" method="post" action="<?= url('vote.php') ?>" id="voteForm">
      <?= csrf_field() ?>
      <input type="hidden" name="poll_id" value="<?= $pid ?>">
      <div class="card-head">
        <h2><?= e($poll['name']) ?></h2>
        <span class="muted"><?= count($cands) ?> candidates</span>
      </div>

      <?php if ($myVote !== null): ?>
        <div class="notice ok">✔ Your vote has been recorded. Thank you for taking part.</div>
      <?php elseif (!$poll['is_open']): ?>
        <div class="notice">This poll is closed.</div>
      <?php else: ?>
        <p class="muted small">Select one candidate and tap <b>Submit vote</b>. One vote per person.</p>
      <?php endif; ?>

      <ul class="cand-list">
        <?php foreach ($cands as $c): $photo = media_url($c['photo']); $mine = $myVote === (int)$c['id']; ?>
        <li>
          <label class="cand <?= $mine ? 'mine' : '' ?>">
            <?php if ($myVote === null && $poll['is_open']): ?><input type="radio" name="candidate_id" value="<?= (int)$c['id'] ?>" required><?php endif; ?>
            <span class="avatar" style="--pc: <?= party_color($c['party']) ?>">
              <?php if ($photo): ?><img src="<?= e($photo) ?>" alt="<?= e($c['name']) ?>" loading="lazy"><?php else: ?><?= e(initials($c['name'])) ?><?php endif; ?>
            </span>
            <span class="cand-info">
              <span class="cand-name"><?= e($c['name']) ?> <?= $mine ? '<em class="you">Your vote</em>' : '' ?></span>
              <?php if ($c['bio']): ?><span class="cand-bio"><?= e($c['bio']) ?></span><?php endif; ?>
              <?php if ($showResults): ?>
                <span class="bar"><span style="width: <?= $c['pct'] ?>%; background: <?= party_color($c['party']) ?>"></span></span>
              <?php endif; ?>
            </span>
            <span class="cand-right">
              <span class="party" style="--pc: <?= party_color($c['party']) ?>"><?= e($c['party']) ?></span>
              <?php if ($showResults): ?><span class="pct"><?= $c['pct'] ?>%<small><?= number_format($c['votes']) ?></small></span><?php endif; ?>
            </span>
          </label>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="vote-actions">
        <?php if ($myVote === null && $poll['is_open']): ?><button class="btn" type="submit">Submit vote</button><?php endif; ?>
        <?php if (!$showResults): ?><a class="btn btn-ghost" href="?results=1#voteForm">View results</a><?php endif; ?>
        <span class="muted small">Total: <?= number_format($total) ?> votes</span>
      </div>
    </form>
    <?php endif; ?>

    <!-- Answers / comments -->
    <section class="card" id="comments">
      <div class="card-head">
        <h2><?= count($all) ?> Answers</h2>
        <div class="tabs">
          <a class="<?= $sort === 'oldest' ? 'on' : '' ?>" href="?show=oldest#comments">Oldest</a>
          <a class="<?= $sort === 'recent' ? 'on' : '' ?>" href="?show=recent#comments">Recent</a>
        </div>
      </div>
      <?php if (!$top): ?><p class="muted">No answers yet. Be the first to share your view.</p><?php endif; ?>
      <ol class="comments">
        <?php foreach ($top as $c): ?>
          <li class="comment" id="comment-<?= $c['id'] ?>">
            <?php include __DIR__ . '/includes/comment_item.php'; ?>
            <?php if (!empty($replies[$c['id']])): ?>
              <ol class="replies">
                <?php foreach ($replies[$c['id']] as $rc): $c2 = $c; $c = $rc; ?>
                  <li class="comment" id="comment-<?= $c['id'] ?>"><?php include __DIR__ . '/includes/comment_item.php'; ?></li>
                <?php $c = $c2; endforeach; ?>
              </ol>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>

      <form class="comment-form" method="post" action="<?= url('comment.php') ?>" enctype="multipart/form-data" id="respond">
        <h3>Leave an answer <span class="replying" id="replyingTo" hidden>— replying to <b></b> <a href="#" id="cancelReply">cancel</a></span></h3>
        <?= csrf_field() ?>
        <input type="hidden" name="poll_id" value="<?= $pid ?>">
        <input type="hidden" name="parent_id" id="parentId" value="">
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
        <input type="text" name="name" placeholder="Your name *" required maxlength="60" value="<?= e($_SESSION['cname'] ?? '') ?>">
        <textarea name="body" rows="4" placeholder="Share your view on the candidates… *" required maxlength="2000"></textarea>
        <label class="file">📷 Add a photo (optional) <input type="file" name="image" accept="image/*"></label>
        <button class="btn" type="submit">Post answer</button>
      </form>
    </section>
  </div>

  <aside class="side-col">
    <?php if ($cands && $showResults && $total): ?>
    <div class="card">
      <h3>Leading</h3>
      <ol class="leaders">
        <?php foreach (array_slice($ranked, 0, 5) as $i => $r): ?>
          <li><span class="rank"><?= $i + 1 ?></span><span class="lname"><?= e($r['name']) ?></span><span class="party sm" style="--pc: <?= party_color($r['party']) ?>"><?= e($r['party']) ?></span><b><?= $r['pct'] ?>%</b></li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php endif; ?>
    <div class="card share">
      <h3>Spread the word</h3>
      <p class="muted small">Share this poll with people in your constituency.</p>
      <div class="share-btns">
        <a class="s-wa" target="_blank" rel="noopener" href="https://api.whatsapp.com/send?text=<?= rawurlencode($shareTxt . ' - ' . $shareUrl) ?>">WhatsApp</a>
        <a class="s-fb" target="_blank" rel="noopener" href="https://www.facebook.com/sharer.php?u=<?= rawurlencode($shareUrl) ?>">Facebook</a>
        <a class="s-x" target="_blank" rel="noopener" href="https://twitter.com/share?text=<?= rawurlencode($shareTxt) ?>&url=<?= rawurlencode($shareUrl) ?>">X</a>
        <button type="button" class="s-copy" data-copy="<?= e($shareUrl) ?>">Copy link</button>
      </div>
    </div>
    <div class="card small muted">Results are an online opinion poll of site visitors and are not official election results.</div>
  </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
