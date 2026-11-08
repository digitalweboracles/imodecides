<?php $img = media_url($c['image']); ?>
<div class="c-head">
  <span class="c-avatar"><?= e(initials($c['name'])) ?></span>
  <div>
    <b><?= e($c['name']) ?></b>
    <span class="muted small"><?= $c['parent_id'] ? 'Replied' : 'Added an answer' ?> on <?= nice_datetime($c['created_at']) ?></span>
  </div>
</div>
<?php if ($img): ?><a href="<?= e($img) ?>" target="_blank"><img class="c-img" src="<?= e($img) ?>" alt="" loading="lazy"></a><?php endif; ?>
<div class="c-body"><?= nl2br(e($c['body'])) ?></div>
<?php if (!$c['parent_id']): ?><button type="button" class="c-reply" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>">Reply</button><?php endif; ?>
