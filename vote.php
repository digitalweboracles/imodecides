<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url(''));
csrf_check();

$pid = (int)($_POST['poll_id'] ?? 0);
$cid = (int)($_POST['candidate_id'] ?? 0);
$st = db()->prepare('SELECT * FROM polls WHERE id = ?');
$st->execute([$pid]);
$poll = $st->fetch();
if (!$poll) redirect(url(''));
$back = poll_url($poll) . '#voteForm';

$st = db()->prepare('SELECT id FROM candidates WHERE id = ? AND poll_id = ?');
$st->execute([$cid, $pid]);
if (!$st->fetch()) { flash('Please select a candidate.', 'err'); redirect($back); }
if (!$poll['is_open']) { flash('This poll is closed.', 'err'); redirect($back); }
if (has_voted($pid) !== null) { flash('You have already voted in this poll.', 'err'); redirect($back); }

$limit = (int)setting('max_votes_per_ip', '10');
$st = db()->prepare('SELECT COUNT(*) FROM votes WHERE poll_id = ? AND ip_hash = ?');
$st->execute([$pid, ip_hash()]);
if ($limit > 0 && (int)$st->fetchColumn() >= $limit) { flash('Too many votes from your network for this poll.', 'err'); redirect($back); }

try {
    db()->prepare('INSERT INTO votes (poll_id, candidate_id, voter_hash, ip_hash) VALUES (?, ?, ?, ?)')
        ->execute([$pid, $cid, voter_hash($pid), ip_hash()]);
    flash('Thank you! Your vote has been counted.');
} catch (PDOException $e) {
    flash('You have already voted in this poll.', 'err');
}
redirect($back);
