<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url(''));
csrf_check();

$pid = (int)($_POST['poll_id'] ?? 0);
$st = db()->prepare('SELECT * FROM polls WHERE id = ?');
$st->execute([$pid]);
$poll = $st->fetch();
if (!$poll) redirect(url(''));
$back = poll_url($poll);

if (!empty($_POST['website'])) redirect($back); // honeypot

$me = current_user();
$name = $me ? user_name($me) : trim(mb_substr($_POST['name'] ?? '', 0, 60));
$email = $me ? $me['email'] : (filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null);
$body = trim(mb_substr($_POST['body'] ?? '', 0, 2000));
if ($name === '' || mb_strlen($body) < 2) { flash('Please enter your name and answer.', 'err'); redirect($back . '#respond'); }

// simple rate limit: 1 post / 30s per session
if (!empty($_SESSION['last_comment']) && time() - $_SESSION['last_comment'] < 30) {
    flash('Please wait a few seconds before posting again.', 'err'); redirect($back . '#respond');
}

$parent = (int)($_POST['parent_id'] ?? 0) ?: null;
if ($parent) {
    $chk = db()->prepare('SELECT id FROM comments WHERE id = ? AND poll_id = ? AND parent_id IS NULL');
    $chk->execute([$parent, $pid]);
    if (!$chk->fetch()) $parent = null;
}
$img = isset($_FILES['image']) ? save_upload($_FILES['image'], 'answer') : null;
$status = setting('moderate') === '1' ? 'pending' : 'approved';

db()->prepare('INSERT INTO comments (poll_id, parent_id, name, body, image, status, ip_hash, user_id, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$pid, $parent, $name, $body, $img, $status, ip_hash(), $me['id'] ?? null, $email]);
$id = db()->lastInsertId();
$_SESSION['last_comment'] = time();
$_SESSION['cname'] = $name;

flash($status === 'pending' ? 'Thanks! Your answer will appear after review.' : 'Your answer has been posted.');
redirect($back . ($status === 'approved' ? '#comment-' . $id : '#comments'));
