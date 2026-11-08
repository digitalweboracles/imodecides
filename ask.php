<?php
require_once __DIR__ . '/includes/functions.php';
$me = current_user();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = require_login(url('add-question/'));
    csrf_check();
    $title = trim(mb_substr($_POST['title'] ?? '', 0, 200));
    $details = trim(mb_substr($_POST['details'] ?? '', 0, 5000));
    $posSlug = $_POST['category'] ?? 'questions';
    if (mb_strlen($title) < 10) $err = 'Please write a question title of at least 10 characters.';
    elseif (!throttle('ask', 10, 3600)) $err = 'You have asked many questions recently. Please try again later.';
    else {
        $st = db()->prepare('SELECT id FROM positions WHERE slug = ?'); $st->execute([$posSlug]);
        $posId = $st->fetchColumn() ?: db()->query("SELECT id FROM positions WHERE slug = 'questions'")->fetchColumn();
        $base = slugify($title); $slug = $base; $i = 2;
        $chk = db()->prepare('SELECT 1 FROM polls WHERE slug = ?');
        while (true) { $chk->execute([$slug]); if (!$chk->fetchColumn()) break; $slug = $base . '-' . $i++; }
        db()->prepare("INSERT INTO polls (position_id, slug, name, heading, description, user_id, kind, sort) VALUES (?, ?, ?, ?, ?, ?, 'question', 999)")
            ->execute([$posId, $slug, $title, $title, $details, $me['id']]);
        flash('Your question has been published.');
        redirect(url('question/' . $slug . '/'));
    }
}
$cats = db()->query('SELECT slug, title FROM positions ORDER BY hidden, sort')->fetchAll();
$pageTitle = 'Ask A Question – ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> <span>/</span> Ask A Question</div>
  <div class="page-box">
    <h1 class="page-h">Ask A Question</h1>
    <?php if (!$me): ?>
      <div class="login-req">
        <p>You must login to ask a question.</p>
        <a class="btn-green" href="<?= url('log-in/') ?>?redirect_to=<?= rawurlencode(url('add-question/')) ?>">Sign In</a>
        <p class="aform-foot">Need An Account, <a href="<?= url('signup/') ?>?redirect_to=<?= rawurlencode(url('add-question/')) ?>">Sign Up Here</a></p>
      </div>
    <?php else: ?>
      <?php if ($err): ?><div class="flash err" style="margin:0 0 14px"><?= e($err) ?></div><?php endif; ?>
      <form method="post" class="aform"><?= csrf_field() ?>
        <label>Question Title<span class="req">*</span><input name="title" required maxlength="200" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Please choose an appropriate title for the question so it can be answered easily."></label>
        <label>Category<span class="req">*</span><select name="category"><?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= ($c['slug'] === ($_POST['category'] ?? 'questions')) ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></label>
        <label>Details<textarea name="details" rows="8" maxlength="5000" placeholder="Type the description thoroughly and in details."><?= e($_POST['details'] ?? '') ?></textarea></label>
        <button class="btn-green" type="submit">Publish Your Question</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
