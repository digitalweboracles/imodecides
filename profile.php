<?php
require_once __DIR__ . '/includes/functions.php';
$uname = $_GET['u'] ?? '';
$me = current_user();

// admin profile (the account that posted the original questions)
if (strcasecmp($uname, ADMIN_USER) === 0) {
    $user = ['id' => 0, 'username' => ADMIN_USER, 'display_name' => 'admin', 'bio' => '', 'created_at' => '2026-10-26 00:00:00', 'avatar' => null];
} else {
    $st = db()->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $st->execute([$uname]);
    $user = $st->fetch();
}
if (!$user) { http_response_code(404); $pageTitle = 'Page not found – ' . SITE_NAME; require __DIR__ . '/includes/header.php'; echo '<div class="box page-box empty"><h1 class="big">404</h1><p>Oops! Page Not Found.</p></div>'; require __DIR__ . '/includes/footer.php'; exit; }

$isOwn = $me && $user['id'] && (int)$me['id'] === (int)$user['id'];

// own profile edit
if ($isOwn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $dn = trim(mb_substr($_POST['display_name'] ?? '', 0, 60)) ?: $me['username'];
    $bio = trim(mb_substr($_POST['bio'] ?? '', 0, 500));
    db()->prepare('UPDATE users SET display_name = ?, bio = ? WHERE id = ?')->execute([$dn, $bio, $me['id']]);
    if (!empty($_FILES['avatar']['tmp_name']) && ($f = save_upload($_FILES['avatar'], 'avatar'))) {
        db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$f, $me['id']]);
    }
    if (!empty($_POST['new_pass'])) {
        if (!password_verify($_POST['cur_pass'] ?? '', $me['pass_hash'])) { flash('Current password is incorrect.', 'err'); redirect(profile_url($me['username'])); }
        if (strlen($_POST['new_pass']) < 6) { flash('New password must be at least 6 characters.', 'err'); redirect(profile_url($me['username'])); }
        db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash($_POST['new_pass'], PASSWORD_DEFAULT), $me['id']]);
    }
    flash('Profile updated.'); redirect(profile_url($me['username']));
}

if ($user['id']) {
    $q = db()->prepare("SELECT * FROM polls WHERE user_id = ? ORDER BY created_at DESC"); $q->execute([$user['id']]);
    $a = db()->prepare("SELECT c.*, p.slug, p.heading FROM comments c JOIN polls p ON p.id = c.poll_id WHERE c.user_id = ? AND c.status = 'approved' ORDER BY c.created_at DESC LIMIT 50"); $a->execute([$user['id']]);
} else {
    $q = db()->query("SELECT * FROM polls WHERE user_id IS NULL AND kind = 'poll' AND (SELECT COUNT(*) FROM candidates c WHERE c.poll_id = polls.id) > 0 ORDER BY created_at DESC");
    $a = db()->query("SELECT c.*, p.slug, p.heading FROM comments c JOIN polls p ON p.id = c.poll_id WHERE 0");
}
$questions = $q->fetchAll(); $answers = $a->fetchAll();
$tab = $_GET['tab'] ?? 'questions';
$avatar = $user['avatar'] ? media_url($user['avatar']) : null;

$pageTitle = ($user['display_name'] ?: $user['username']) . ' – ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="box">
  <div class="crumbs"><a href="<?= url('') ?>">Home</a> <span>/</span> Profile</div>
  <div class="prof">
    <img class="prof-av" src="<?= e($avatar ?: url('assets/img/avatar.svg')) ?>" alt="">
    <div>
      <h1 class="q-title"><?= e($user['display_name'] ?: $user['username']) ?></h1>
      <div class="muted-sm">@<?= e($user['username']) ?> · Member since <?= nice_date($user['created_at']) ?></div>
      <?php if ($user['bio']): ?><p class="prof-bio"><?= nl2br(e($user['bio'])) ?></p><?php endif; ?>
    </div>
  </div>
  <ul class="q-stats prof-stats">
    <li><b><?= count($questions) ?></b> Questions</li>
    <li><b><?= count($answers) ?></b> Answers</li>
  </ul>
  <div class="ans-head" style="padding:16px 30px">
    <div class="ans-tabs">
      <a class="<?= $tab === 'questions' ? 'on' : '' ?>" href="?tab=questions">Questions</a>
      <a class="<?= $tab === 'answers' ? 'on' : '' ?>" href="?tab=answers">Answers</a>
      <?php if ($isOwn): ?><a class="<?= $tab === 'edit' ? 'on' : '' ?>" href="?tab=edit">Edit Profile</a><?php endif; ?>
    </div>
  </div>
  <div class="page-box" style="padding-top:0">
    <?php if ($tab === 'edit' && $isOwn): ?>
      <form method="post" enctype="multipart/form-data" class="aform"><?= csrf_field() ?>
        <label>Display name<input name="display_name" maxlength="60" value="<?= e($me['display_name']) ?>"></label>
        <label>About me<textarea name="bio" rows="4" maxlength="500"><?= e($me['bio']) ?></textarea></label>
        <label>Profile picture<input type="file" name="avatar" accept="image/*"></label>
        <label>Current password (only to change password)<input type="password" name="cur_pass" autocomplete="current-password"></label>
        <label>New password<input type="password" name="new_pass" minlength="6" autocomplete="new-password"></label>
        <button class="btn-green" type="submit">Save</button>
      </form>
    <?php elseif ($tab === 'answers'): ?>
      <?php if (!$answers): ?><p class="ans-empty">No answers yet.</p><?php endif; ?>
      <ul class="feed">
        <?php foreach ($answers as $c): ?>
          <li><a class="feed-t" href="<?= poll_url($c) ?>#comment-<?= $c['id'] ?>"><?= e($c['heading']) ?></a><p><?= e(mb_strimwidth($c['body'], 0, 200, '…')) ?></p><span class="muted-sm"><?= nice_datetime($c['created_at']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?php if (!$questions): ?><p class="ans-empty">No questions yet.</p><?php endif; ?>
      <ul class="feed">
        <?php foreach ($questions as $p): ?>
          <li><a class="feed-t" href="<?= poll_url($p) ?>"><?= e($p['heading']) ?></a><span class="muted-sm">Asked: <?= nice_date($p['created_at']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
