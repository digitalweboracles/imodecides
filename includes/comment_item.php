<?php $img = media_url($c['image']); ?>
<div class="cm-head">
  <span class="cm-avatar"><img src="<?= url('assets/img/avatar.svg') ?>" alt="<?= e($c['name']) ?>"></span>
  <div>
    <?php if (!empty($c['user_id']) && ($cu = db()->query('SELECT username FROM users WHERE id = ' . (int)$c['user_id'])->fetchColumn())): ?><a class="cm-name" href="<?= profile_url($cu) ?>"><b><?= e($c['name']) ?></b></a><?php else: ?><b class="cm-name"><?= e($c['name']) ?></b><?php endif; ?>
    <a class="cm-date" href="#comment-<?= (int)$c['id'] ?>"><?= $c['parent_id'] ? 'Replied to answer' : 'Added an answer' ?> on <?= nice_datetime($c['created_at']) ?></a>
  </div>
</div>
<div class="cm-text">
  <?php if ($img): ?><a href="<?= e($img) ?>" target="_blank" rel="noopener"><img class="cm-img" src="<?= e($img) ?>" alt="" loading="lazy"></a><?php endif; ?>
  <?php if (trim($c['body']) !== ''): ?><p><?= nl2br(e($c['body'])) ?></p><?php endif; ?>
</div>
<?php $cScore = score('comment', (int)$c['id'], (int)($c['base_score'] ?? 0)); $cMine = my_reaction('comment', (int)$c['id']); ?>
<ul class="cm-foot">
  <li class="vote-box vb-inline" data-type="comment" data-id="<?= (int)$c['id'] ?>"><button class="vb-up <?= $cMine === 1 ? 'on' : '' ?>" data-do="up" title="Like" aria-label="Like">&#9650;</button><span class="vb-score"><?= $cScore ?></span><button class="vb-down <?= $cMine === -1 ? 'on' : '' ?>" data-do="down" title="Dislike" aria-label="Dislike">&#9660;</button></li>
  <?php if (!$c['parent_id']): ?><li><a href="#respond" class="c-reply" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>">&#8617; Reply</a></li><?php endif; ?>
  <li><button class="linkbtn rep-open" data-type="comment" data-id="<?= (int)$c['id'] ?>">Report</button></li>
  <li><a href="https://api.whatsapp.com/send?text=<?= rawurlencode(mb_strimwidth($c['body'], 0, 140, '…') . ' - ' . $shareUrl . '#comment-' . $c['id']) ?>" target="_blank" rel="noopener">&lt; Share</a></li>
</ul>
