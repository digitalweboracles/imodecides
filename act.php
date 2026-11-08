<?php
// Q&A actions: like/dislike (questions + answers), follow, favourite, report. Returns JSON for fetch(), redirects otherwise.
require_once __DIR__ . '/includes/functions.php';
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
$back = safe_redirect_target($_POST['back'] ?? url(''));
function out(array $data, string $back, bool $ajax) {
    if ($ajax) { header('Content-Type: application/json'); echo json_encode($data); exit; }
    if (!empty($data['error'])) flash($data['error'], 'err'); elseif (!empty($data['msg'])) flash($data['msg']);
    redirect($back);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url(''));
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) out(['error' => 'Session expired. Reload the page and try again.'], $back, $ajax);

$do = $_POST['do'] ?? '';
$type = in_array($_POST['type'] ?? '', ['question', 'comment', 'user'], true) ? $_POST['type'] : '';
$id = (int)($_POST['id'] ?? 0);

if ($do === 'report') {
    $reason = trim(mb_substr($_POST['reason'] ?? '', 0, 1000));
    if (!$type || !$id || mb_strlen($reason) < 3) out(['error' => 'Please briefly explain why you are reporting this.'], $back, $ajax);
    if (!throttle('report', 20, 3600)) out(['error' => 'Too many reports. Please try again later.'], $back, $ajax);
    $u = current_user();
    db()->prepare('INSERT INTO reports (target_type, target_id, reason, user_id, ip_hash) VALUES (?, ?, ?, ?, ?)')
        ->execute([$type, $id, $reason, $u['id'] ?? null, ip_hash()]);
    out(['ok' => 1, 'msg' => 'Thank you. Your report has been sent to the moderators.'], $back, $ajax);
}

$u = current_user();
if (!$u) out(['error' => 'You must login to do this.', 'login' => url('log-in/') . '?redirect_to=' . rawurlencode($back)], url('log-in/') . '?redirect_to=' . rawurlencode($back), $ajax);

if ($do === 'up' || $do === 'down') {
    if (!in_array($type, ['question', 'comment'], true) || !$id) out(['error' => 'Invalid request.'], $back, $ajax);
    $tbl = $type === 'question' ? 'polls' : 'comments';
    $st = db()->prepare("SELECT base_score, " . ($type === 'question' ? 'user_id' : 'user_id') . " AS owner FROM $tbl WHERE id = ?"); $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) out(['error' => 'Not found.'], $back, $ajax);
    if ($row['owner'] && (int)$row['owner'] === (int)$u['id']) out(['error' => 'Sorry, you cannot vote on your own post.'], $back, $ajax);
    $val = $do === 'up' ? 1 : -1;
    $cur = my_reaction($type, $id);
    if ($cur === $val) {
        db()->prepare('DELETE FROM reactions WHERE target_type = ? AND target_id = ? AND user_id = ?')->execute([$type, $id, $u['id']]);
        $mine = 0;
    } else {
        db()->prepare('INSERT INTO reactions (target_type, target_id, user_id, value) VALUES (?, ?, ?, ?)
                       ON CONFLICT(target_type, target_id, user_id) DO UPDATE SET value = excluded.value')->execute([$type, $id, $u['id'], $val]);
        $mine = $val;
    }
    out(['ok' => 1, 'score' => score($type, $id, (int)$row['base_score']), 'mine' => $mine], $back, $ajax);
}

if ($do === 'follow' || $do === 'favorite') {
    $st = db()->prepare('SELECT 1 FROM polls WHERE id = ?'); $st->execute([$id]);
    if (!$st->fetchColumn()) out(['error' => 'Not found.'], $back, $ajax);
    if (is_following($id, $do)) {
        db()->prepare('DELETE FROM follows WHERE user_id = ? AND poll_id = ? AND kind = ?')->execute([$u['id'], $id, $do]);
        $on = false;
    } else {
        db()->prepare('INSERT OR IGNORE INTO follows (user_id, poll_id, kind) VALUES (?, ?, ?)')->execute([$u['id'], $id, $do]);
        $on = true;
    }
    out(['ok' => 1, 'on' => $on, 'count' => follow_count($id, $do)], $back, $ajax);
}
out(['error' => 'Unknown action.'], $back, $ajax);
