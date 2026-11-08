<?php $img = media_url($c['image']); ?>
<div class="cm-head">
  <span class="cm-avatar"><img src="<?= url('assets/img/avatar.svg') ?>" alt="<?= e($c['name']) ?>"></span>
  <div>
    <b class="cm-name"><?= e($c['name']) ?></b>
    <a class="cm-date" href="#comment-<?= (int)$c['id'] ?>"><?= $c['parent_id'] ? 'Replied to answer' : 'Added an answer' ?> on <?= nice_datetime($c['created_at']) ?></a>
  </div>
</div>
<div class="cm-text">
  <?php if ($img): ?><a href="<?= e($img) ?>" target="_blank" rel="noopener"><img class="cm-img" src="<?= e($img) ?>" alt="" loading="lazy"></a><?php endif; ?>
  <?php if (trim($c['body']) !== ''): ?><p><?= nl2br(e($c['body'])) ?></p><?php endif; ?>
</div>
<ul class="cm-foot">
  <?php if (!$c['parent_id']): ?><li><a href="#respond" class="c-reply" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>">&#8617; Reply</a></li><?php endif; ?>
  <li><a href="https://api.whatsapp.com/send?text=<?= rawurlencode(mb_strimwidth($c['body'], 0, 140, '…') . ' - ' . $shareUrl . '#comment-' . $c['id']) ?>" target="_blank" rel="noopener">&lt; Share</a></li>
</ul>
