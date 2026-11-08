<?php
require_once __DIR__ . '/../includes/functions.php';
$p = $_GET['p'] ?? 'dashboard';
$isAdmin = !empty($_SESSION['admin']);

// ---------- Auth ----------
if ($p === 'logout') { unset($_SESSION['admin']); redirect(url('admin/')); }
if (!$isAdmin) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0) + 1;
        if ($_SESSION['login_tries'] > 8) $err = 'Too many attempts. Try again later.';
        elseif (($_POST['user'] ?? '') === ADMIN_USER && password_verify($_POST['pass'] ?? '', setting('admin_hash'))) {
            session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['login_tries'] = 0; redirect(url('admin/'));
        } else $err = 'Invalid login.';
    }
    admin_head('Admin login'); ?>
    <div class="login-box card">
      <h1>Imo Decides <span>Admin</span></h1>
      <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
      <form method="post"><?= csrf_field() ?>
        <label>Username<input name="user" required autofocus></label>
        <label>Password<input type="password" name="pass" required></label>
        <button class="btn">Sign in</button>
      </form>
    </div>
    <?php admin_foot(); exit;
}

// ---------- Actions (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['a'] ?? '';
    $db = db();
    switch ($a) {
        case 'poll_save':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name']); $heading = trim($_POST['heading']) ?: $name;
            $slug = slugify($_POST['slug'] ?: $name);
            $vals = [(int)$_POST['position_id'], $slug, $name, $heading, trim($_POST['description'] ?? ''), isset($_POST['is_open']) ? 1 : 0, (int)$_POST['sort']];
            try {
                if ($id) { $db->prepare('UPDATE polls SET position_id=?, slug=?, name=?, heading=?, description=?, is_open=?, sort=? WHERE id=?')->execute([...$vals, $id]); }
                else { $db->prepare('INSERT INTO polls (position_id, slug, name, heading, description, is_open, sort) VALUES (?,?,?,?,?,?,?)')->execute($vals); $id = $db->lastInsertId(); }
                flash('Poll saved.');
            } catch (PDOException $e) { flash('That slug is already used by another poll.', 'err'); }
            redirect(url('admin/?p=poll&id=' . $id));
        case 'poll_delete':
            $db->prepare('DELETE FROM polls WHERE id=?')->execute([(int)$_POST['id']]);
            flash('Poll deleted.'); redirect(url('admin/?p=polls'));
        case 'poll_reset':
            $db->prepare('DELETE FROM votes WHERE poll_id=?')->execute([(int)$_POST['id']]);
            $db->prepare('UPDATE candidates SET base_votes=0 WHERE poll_id=?')->execute([(int)$_POST['id']]);
            flash('Votes reset to zero.'); redirect(url('admin/?p=poll&id=' . (int)$_POST['id']));
        case 'cand_save':
            $id = (int)($_POST['id'] ?? 0); $pid = (int)$_POST['poll_id'];
            $photo = isset($_FILES['photo']) ? save_upload($_FILES['photo'], 'cand') : null;
            if ($id) {
                $db->prepare('UPDATE candidates SET name=?, party=?, bio=?, sort=?, base_votes=? WHERE id=?')->execute([trim($_POST['name']), trim($_POST['party']), trim($_POST['bio']), (int)$_POST['sort'], max(0, (int)($_POST['base_votes'] ?? 0)), $id]);
                if ($photo) $db->prepare('UPDATE candidates SET photo=? WHERE id=?')->execute([$photo, $id]);
                if (isset($_POST['remove_photo'])) $db->prepare('UPDATE candidates SET photo=NULL WHERE id=?')->execute([$id]);
            } else {
                $sort = (int)$db->query('SELECT COALESCE(MAX(sort),0)+1 FROM candidates WHERE poll_id=' . $pid)->fetchColumn();
                $db->prepare('INSERT INTO candidates (poll_id, name, party, bio, photo, sort) VALUES (?,?,?,?,?,?)')->execute([$pid, trim($_POST['name']), trim($_POST['party']), trim($_POST['bio'] ?? ''), $photo, $sort]);
            }
            flash('Candidate saved.'); redirect(url('admin/?p=poll&id=' . $pid));
        case 'cand_bulk':
            $pid = (int)$_POST['poll_id'];
            $sort = (int)$db->query('SELECT COALESCE(MAX(sort),0) FROM candidates WHERE poll_id=' . $pid)->fetchColumn();
            $n = 0;
            foreach (preg_split('/\r?\n/', $_POST['lines']) as $line) {
                $line = trim($line, " \t-•*");
                if ($line === '') continue;
                $parts = preg_split('/\s+[-–—|]\s+|\s*,\s*(?=[A-Za-z]+$)/', $line);
                $name = trim($parts[0]); $party = trim($parts[1] ?? '');
                $db->prepare('INSERT INTO candidates (poll_id, name, party, sort) VALUES (?,?,?,?)')->execute([$pid, $name, $party, ++$sort]); $n++;
            }
            flash("$n candidates added."); redirect(url('admin/?p=poll&id=' . $pid));
        case 'cand_delete':
            $db->prepare('DELETE FROM candidates WHERE id=?')->execute([(int)$_POST['id']]);
            flash('Candidate removed.'); redirect(url('admin/?p=poll&id=' . (int)$_POST['poll_id']));
        case 'comment':
            $id = (int)$_POST['id'];
            if ($_POST['do'] === 'delete') $db->prepare('DELETE FROM comments WHERE id=?')->execute([$id]);
            else $db->prepare('UPDATE comments SET status=? WHERE id=?')->execute([$_POST['do'] === 'approve' ? 'approved' : 'pending', $id]);
            flash('Comment updated.'); redirect(url('admin/?p=comments&f=' . ($_POST['f'] ?? '')));
        case 'settings':
            set_setting('results_public', isset($_POST['results_public']) ? '1' : '0');
            set_setting('moderate', isset($_POST['moderate']) ? '1' : '0');
            set_setting('max_votes_per_ip', (string)max(0, (int)$_POST['max_votes_per_ip']));
            if (!empty($_POST['new_pass'])) {
                if (strlen($_POST['new_pass']) < 8) { flash('Password must be at least 8 characters.', 'err'); redirect(url('admin/?p=settings')); }
                set_setting('admin_hash', password_hash($_POST['new_pass'], PASSWORD_DEFAULT));
            }
            if (!empty($_FILES['logo']['tmp_name']) && ($f = save_upload($_FILES['logo'], 'tmp'))) rename(UPLOAD_DIR . "/$f", UPLOAD_DIR . '/logo.png');
            if (!empty($_FILES['og']['tmp_name']) && ($f = save_upload($_FILES['og'], 'tmp'))) rename(UPLOAD_DIR . "/$f", UPLOAD_DIR . '/og.jpg');
            flash('Settings saved.'); redirect(url('admin/?p=settings'));
        case 'user':
            $uid = (int)$_POST['id'];
            if ($_POST['do'] === 'delete') $db->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            elseif ($_POST['do'] === 'suspend') $db->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$uid]);
            elseif ($_POST['do'] === 'activate') $db->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$uid]);
            elseif ($_POST['do'] === 'password' && strlen($_POST['pass'] ?? '') >= 6) $db->prepare('UPDATE users SET pass_hash=? WHERE id=?')->execute([password_hash($_POST['pass'], PASSWORD_DEFAULT), $uid]);
            flash('User updated.'); redirect(url('admin/?p=users'));
        case 'report':
            $rid = (int)$_POST['id'];
            if ($_POST['do'] === 'remove') {
                $r = $db->query('SELECT * FROM reports WHERE id=' . $rid)->fetch();
                if ($r && $r['target_type'] === 'comment') $db->prepare('DELETE FROM comments WHERE id=?')->execute([$r['target_id']]);
                if ($r && $r['target_type'] === 'question') $db->prepare("DELETE FROM polls WHERE id=? AND kind='question'")->execute([$r['target_id']]);
            }
            $db->prepare("UPDATE reports SET status='closed' WHERE id=?")->execute([$rid]);
            flash('Report handled.'); redirect(url('admin/?p=reports'));
        case 'refetch':
            $r = fetch_legacy_assets();
            flash('Asset fetch: ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($r), $r)));
            redirect(url('admin/?p=settings'));
    }
}

// ---------- Views ----------
$positions = db()->query('SELECT * FROM positions ORDER BY sort')->fetchAll();
admin_head('Admin'); ?>
<div class="admin">
  <aside class="a-side">
    <div class="a-brand">Imo Decides<span>Admin</span></div>
    <a class="<?= $p === 'dashboard' ? 'on' : '' ?>" href="?p=dashboard">📊 Dashboard</a>
    <a class="<?= in_array($p, ['polls', 'poll']) ? 'on' : '' ?>" href="?p=polls">🗳 Polls & candidates</a>
    <a class="<?= $p === 'comments' ? 'on' : '' ?>" href="?p=comments">💬 Answers</a>
    <a class="<?= $p === 'users' ? 'on' : '' ?>" href="?p=users">👤 Users</a>
    <?php $openRep = (int)db()->query("SELECT COUNT(*) FROM reports WHERE status='open'")->fetchColumn(); ?>
    <a class="<?= $p === 'reports' ? 'on' : '' ?>" href="?p=reports">🚩 Reports<?= $openRep ? " ($openRep)" : '' ?></a>
    <a class="<?= $p === 'settings' ? 'on' : '' ?>" href="?p=settings">⚙️ Settings</a>
    <a href="<?= url('') ?>" target="_blank">↗ View site</a>
    <a href="?p=logout">⎋ Log out</a>
  </aside>
  <div class="a-main">
  <?php if ($f = flash()): ?><div class="flash <?= e($f[1]) ?>"><?= e($f[0]) ?></div><?php endif; ?>

  <?php if ($p === 'dashboard'):
    $s = db()->query("SELECT (SELECT COUNT(*) FROM polls) polls, (SELECT COUNT(*) FROM candidates) cands, ((SELECT COUNT(*) FROM votes) + (SELECT COALESCE(SUM(base_votes),0) FROM candidates)) votes,
        (SELECT COUNT(*) FROM comments) comments, (SELECT COUNT(*) FROM comments WHERE status='pending') pending,
        (SELECT COUNT(*) FROM votes WHERE created_at >= datetime('now','-1 day')) today")->fetch();
    $top = db()->query("SELECT pl.id, pl.heading, pl.views, (SELECT COUNT(*) FROM votes v WHERE v.poll_id=pl.id) + (SELECT COALESCE(SUM(c2.base_votes),0) FROM candidates c2 WHERE c2.poll_id = pl.id) votes FROM polls pl ORDER BY votes DESC, pl.views DESC LIMIT 10")->fetchAll(); ?>
    <h1>Dashboard</h1>
    <div class="stats">
      <div class="card"><b><?= $s['polls'] ?></b>Polls</div>
      <div class="card"><b><?= $s['cands'] ?></b>Candidates</div>
      <div class="card"><b><?= number_format($s['votes']) ?></b>Votes</div>
      <div class="card"><b><?= $s['today'] ?></b>Votes (24h)</div>
      <div class="card"><b><?= $s['comments'] ?></b>Answers <?= $s['pending'] ? "<em>{$s['pending']} pending</em>" : '' ?></div>
    </div>
    <div class="card"><h2>Top polls</h2><table><tr><th>Poll</th><th>Views</th><th>Votes</th><th></th></tr>
      <?php foreach ($top as $t): ?><tr><td><?= e($t['heading']) ?></td><td><?= number_format($t['views']) ?></td><td><?= number_format($t['votes']) ?></td><td><a href="?p=poll&id=<?= $t['id'] ?>">Manage</a></td></tr><?php endforeach; ?>
    </table></div>

  <?php elseif ($p === 'polls'):
    $polls = db()->query("SELECT pl.*, ps.title pos, (SELECT COUNT(*) FROM candidates c WHERE c.poll_id=pl.id) cands, (SELECT COUNT(*) FROM votes v WHERE v.poll_id=pl.id) + (SELECT COALESCE(SUM(c2.base_votes),0) FROM candidates c2 WHERE c2.poll_id = pl.id) votes
        FROM polls pl JOIN positions ps ON ps.id=pl.position_id ORDER BY ps.sort, pl.sort, pl.name")->fetchAll(); ?>
    <div class="a-top"><h1>Polls & candidates</h1><a class="btn" href="?p=poll">+ New poll</a></div>
    <div class="card"><table><tr><th>Position</th><th>Constituency / poll</th><th>Candidates</th><th>Votes</th><th>Status</th><th></th></tr>
      <?php foreach ($polls as $pl): ?><tr>
        <td><?= e($pl['pos']) ?></td><td><b><?= e($pl['name']) ?></b><br><small class="muted">/question/<?= e($pl['slug']) ?>/</small></td>
        <td><?= $pl['cands'] ?></td><td><?= number_format($pl['votes']) ?></td><td><?= $pl['is_open'] ? '🟢 Open' : '⚪ Closed' ?></td>
        <td><a href="?p=poll&id=<?= $pl['id'] ?>">Manage</a></td></tr><?php endforeach; ?>
    </table></div>

  <?php elseif ($p === 'poll'):
    $id = (int)($_GET['id'] ?? 0);
    $poll = ['id' => 0, 'position_id' => $positions[2]['id'] ?? 0, 'slug' => '', 'name' => '', 'heading' => '', 'description' => '', 'is_open' => 1, 'sort' => 0];
    if ($id) { $st = db()->prepare('SELECT * FROM polls WHERE id=?'); $st->execute([$id]); $poll = $st->fetch() ?: $poll; }
    [$cands, $total] = $poll['id'] ? poll_results((int)$poll['id']) : [[], 0]; ?>
    <div class="a-top"><h1><?= $poll['id'] ? e($poll['heading']) : 'New poll' ?></h1><?php if ($poll['id']): ?><a class="btn btn-ghost" target="_blank" href="<?= poll_url($poll) ?>">View ↗</a><?php endif; ?></div>
    <form class="card form-grid" method="post"><?= csrf_field() ?><input type="hidden" name="a" value="poll_save"><input type="hidden" name="id" value="<?= $poll['id'] ?>">
      <label>Position<select name="position_id"><?php foreach ($positions as $ps): ?><option value="<?= $ps['id'] ?>" <?= $ps['id'] == $poll['position_id'] ? 'selected' : '' ?>><?= e($ps['title']) ?></option><?php endforeach; ?></select></label>
      <label>Constituency / name<input name="name" required value="<?= e($poll['name']) ?>" placeholder="e.g. Ahiazu/Ezinnihitte Mbaise"></label>
      <label class="full">Page heading<input name="heading" value="<?= e($poll['heading']) ?>" placeholder="e.g. Ahiazu/Ezinnihitte Mbaise FHR Candidates 2027"></label>
      <label>URL slug<input name="slug" value="<?= e($poll['slug']) ?>" placeholder="auto from name"></label>
      <label>Sort order<input type="number" name="sort" value="<?= (int)$poll['sort'] ?>"></label>
      <label class="full">Intro text (optional)<textarea name="description" rows="3"><?= e($poll['description']) ?></textarea></label>
      <label class="check"><input type="checkbox" name="is_open" <?= $poll['is_open'] ? 'checked' : '' ?>> Poll open for voting</label>
      <div class="full"><button class="btn">Save poll</button></div>
    </form>
    <?php if ($poll['id']): ?>
      <div class="card"><div class="a-top"><h2>Candidates (<?= count($cands) ?>) · <?= number_format($total) ?> votes</h2></div>
        <table><tr><th>#</th><th>Photo</th><th>Name / party / bio</th><th>Votes</th><th></th></tr>
        <?php foreach ($cands as $c): $ph = media_url($c['photo']); ?>
          <tr><td colspan="5"><form method="post" enctype="multipart/form-data" class="cand-row"><?= csrf_field() ?>
            <input type="hidden" name="a" value="cand_save"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
            <input class="w-sort" type="number" name="sort" value="<?= (int)$c['sort'] ?>">
            <span class="avatar" style="--pc:<?= party_color($c['party']) ?>"><?php if ($ph): ?><img src="<?= e($ph) ?>" alt=""><?php else: ?><?= e(initials($c['name'])) ?><?php endif; ?></span>
            <span class="cr-fields"><input name="name" value="<?= e($c['name']) ?>" required><input class="w-party" name="party" value="<?= e($c['party']) ?>" placeholder="Party">
              <input name="bio" value="<?= e($c['bio']) ?>" placeholder="Short bio (optional)">
              <label class="sm">Imported votes <input type="number" min="0" name="base_votes" value="<?= (int)$c['base_votes'] ?>"></label>
              <input type="file" name="photo" accept="image/*"><?php if ($ph): ?><label class="check sm"><input type="checkbox" name="remove_photo"> remove photo</label><?php endif; ?></span>
            <b class="w-votes"><?= number_format($c['votes']) ?> · <?= $c['pct'] ?>%</b>
            <button class="btn btn-sm">Save</button>
            <button class="btn btn-sm btn-danger" name="a" value="cand_delete" onclick="return confirm('Remove this candidate and their votes?')">✕</button>
          </form></td></tr>
        <?php endforeach; ?></table>
      </div>
      <div class="two">
        <form class="card form-grid" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="a" value="cand_save"><input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
          <h2 class="full">Add candidate</h2>
          <label>Full name<input name="name" required></label><label>Party<input name="party" placeholder="APC, PDP, LP…"></label>
          <label class="full">Short bio<input name="bio"></label><label class="full">Photo<input type="file" name="photo" accept="image/*"></label>
          <div class="full"><button class="btn">Add</button></div>
        </form>
        <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="a" value="cand_bulk"><input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
          <h2>Bulk paste</h2><p class="muted small">One per line: <code>Full Name - PARTY</code></p>
          <textarea name="lines" rows="7" placeholder="Igbokwe Raphael Nnanna - APC&#10;Chinedu Emeka Martins - YPP"></textarea>
          <button class="btn">Add all</button>
        </form>
      </div>
      <div class="card danger-zone"><h2>Danger zone</h2>
        <form method="post" onsubmit="return confirm('Reset ALL votes for this poll to zero?')"><?= csrf_field() ?><input type="hidden" name="a" value="poll_reset"><input type="hidden" name="id" value="<?= $poll['id'] ?>"><button class="btn btn-ghost">Reset votes</button></form>
        <form method="post" onsubmit="return confirm('Delete this poll, its candidates, votes and answers?')"><?= csrf_field() ?><input type="hidden" name="a" value="poll_delete"><input type="hidden" name="id" value="<?= $poll['id'] ?>"><button class="btn btn-danger">Delete poll</button></form>
      </div>
    <?php endif; ?>

  <?php elseif ($p === 'comments'):
    $f = $_GET['f'] ?? '';
    $sql = "SELECT c.*, pl.heading, pl.slug FROM comments c JOIN polls pl ON pl.id=c.poll_id " . ($f === 'pending' ? "WHERE c.status='pending' " : '') . "ORDER BY c.created_at DESC LIMIT 300";
    $rows = db()->query($sql)->fetchAll(); ?>
    <div class="a-top"><h1>Answers</h1><div class="tabs"><a class="<?= $f ? '' : 'on' ?>" href="?p=comments">All</a><a class="<?= $f ? 'on' : '' ?>" href="?p=comments&f=pending">Pending</a></div></div>
    <div class="card"><table><tr><th>Who / when</th><th>Answer</th><th>Poll</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $c): ?><tr>
      <td><b><?= e($c['name']) ?></b><br><small class="muted"><?= nice_datetime($c['created_at']) ?></small></td>
      <td class="c-cell"><?= $c['parent_id'] ? '<small class="muted">↳ reply</small> ' : '' ?><?= e(mb_strimwidth($c['body'], 0, 200, '…')) ?><?= $c['image'] ? ' 📷' : '' ?></td>
      <td><a target="_blank" href="<?= poll_url($c) ?>#comment-<?= $c['id'] ?>"><?= e($c['heading']) ?></a></td>
      <td><?= $c['status'] === 'approved' ? '🟢' : '🟡 pending' ?></td>
      <td class="nowrap"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="a" value="comment"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="f" value="<?= e($f) ?>">
        <?php if ($c['status'] !== 'approved'): ?><button class="btn btn-sm" name="do" value="approve">Approve</button><?php else: ?><button class="btn btn-sm btn-ghost" name="do" value="hide">Hide</button><?php endif; ?>
        <button class="btn btn-sm btn-danger" name="do" value="delete" onclick="return confirm('Delete?')">✕</button></form></td></tr><?php endforeach; ?>
    </table></div>

  <?php elseif ($p === 'users'):
    $q = trim($_GET['q'] ?? '');
    $st = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM comments c WHERE c.user_id=u.id) answers, (SELECT COUNT(*) FROM polls p WHERE p.user_id=u.id) questions FROM users u WHERE u.username LIKE ? OR u.email LIKE ? ORDER BY u.created_at DESC LIMIT 300");
    $st->execute(["%$q%", "%$q%"]); $users = $st->fetchAll(); ?>
    <div class="a-top"><h1>Users (<?= (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() ?>)</h1>
      <form><input type="hidden" name="p" value="users"><input name="q" value="<?= e($q) ?>" placeholder="Search username or email"></form></div>
    <div class="card"><table><tr><th>User</th><th>Email</th><th>Joined</th><th>Q / A</th><th>Status</th><th></th></tr>
    <?php foreach ($users as $u): ?><tr>
      <td><a target="_blank" href="<?= profile_url($u['username']) ?>"><b><?= e($u['username']) ?></b></a></td><td><?= e($u['email']) ?></td>
      <td><small><?= nice_date($u['created_at']) ?></small></td><td><?= $u['questions'] ?> / <?= $u['answers'] ?></td><td><?= $u['status'] === 'active' ? '🟢' : '⛔ suspended' ?></td>
      <td class="nowrap"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="a" value="user"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <input name="pass" placeholder="new password" style="width:120px;padding:5px"><button class="btn btn-sm btn-ghost" name="do" value="password">Set</button>
        <?php if ($u['status'] === 'active'): ?><button class="btn btn-sm btn-ghost" name="do" value="suspend">Suspend</button><?php else: ?><button class="btn btn-sm" name="do" value="activate">Activate</button><?php endif; ?>
        <button class="btn btn-sm btn-danger" name="do" value="delete" onclick="return confirm('Delete this user?')">✕</button></form></td></tr><?php endforeach; ?>
    </table></div>

  <?php elseif ($p === 'reports'):
    $reps = db()->query("SELECT r.*, u.username FROM reports r LEFT JOIN users u ON u.id=r.user_id ORDER BY r.status='open' DESC, r.created_at DESC LIMIT 300")->fetchAll(); ?>
    <h1>Reports</h1>
    <div class="card"><table><tr><th>When</th><th>What</th><th>Reason</th><th>By</th><th>Status</th><th></th></tr>
    <?php foreach ($reps as $r):
      if ($r['target_type'] === 'comment') { $t = db()->query('SELECT c.body, p.slug FROM comments c JOIN polls p ON p.id=c.poll_id WHERE c.id=' . (int)$r['target_id'])->fetch(); $link = $t ? poll_url($t) . '#comment-' . $r['target_id'] : null; $what = $t ? 'Answer: ' . mb_strimwidth($t['body'], 0, 80, '…') : 'Answer (deleted)'; }
      else { $t = db()->query('SELECT heading, slug FROM polls WHERE id=' . (int)$r['target_id'])->fetch(); $link = $t ? poll_url($t) : null; $what = $t ? 'Question: ' . $t['heading'] : 'Question (deleted)'; } ?>
      <tr><td><small><?= nice_datetime($r['created_at']) ?></small></td><td><?php if ($link): ?><a target="_blank" href="<?= $link ?>"><?= e($what) ?></a><?php else: ?><?= e($what) ?><?php endif; ?></td>
      <td class="c-cell"><?= e($r['reason']) ?></td><td><?= e($r['username'] ?? 'guest') ?></td><td><?= $r['status'] === 'open' ? '🟡 open' : '✔ closed' ?></td>
      <td class="nowrap"><?php if ($r['status'] === 'open'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="a" value="report"><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button class="btn btn-sm btn-ghost" name="do" value="dismiss">Dismiss</button><button class="btn btn-sm btn-danger" name="do" value="remove" onclick="return confirm('Remove the reported content?')">Remove content</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></table></div>

  <?php elseif ($p === 'settings'): ?>
    <h1>Settings</h1>
    <form class="card form-grid" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="a" value="settings">
      <label class="check full"><input type="checkbox" name="results_public" <?= setting('results_public') === '1' ? 'checked' : '' ?>> Show results to visitors before they vote</label>
      <label class="check full"><input type="checkbox" name="moderate" <?= setting('moderate') === '1' ? 'checked' : '' ?>> Hold new answers for approval</label>
      <label>Max votes per IP per poll (0 = no limit)<input type="number" name="max_votes_per_ip" value="<?= e(setting('max_votes_per_ip', '10')) ?>"></label>
      <label>New admin password<input type="password" name="new_pass" placeholder="leave blank to keep" autocomplete="new-password"></label>
      <label>Replace logo (PNG)<input type="file" name="logo" accept="image/*"></label>
      <label>Replace share image<input type="file" name="og" accept="image/*"></label>
      <div class="full"><button class="btn">Save settings</button></div>
    </form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="a" value="refetch">
      <h2>Original site images</h2>
      <p class="muted small">Logo: <?= media_url('logo.png') ? '✅' : '❌' ?> · Share image: <?= media_url('og.jpg') ? '✅' : '❌' ?> · Icon: <?= media_url('icon.jpg') ? '✅' : '❌' ?> · Answer photo: <?= media_url('comment-233.jpg') ? '✅' : '❌' ?></p>
      <button class="btn btn-ghost">Re-download from imodecides.com</button>
    </form>
    <?php if ($rep = setting('legacy_sync_report')): ?><div class="card"><h2>Old-site import report</h2><pre style="white-space:pre-wrap;font-size:.8rem"><?= e($rep) ?></pre></div><?php endif; ?>
  <?php endif; ?>
  </div>
</div>
<?php admin_foot();

function admin_head(string $t): void { ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= e($t) ?> – Imo Decides</title><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=11"></head><body class="admin-body"><?php }
function admin_foot(): void { echo '</body></html>'; }
