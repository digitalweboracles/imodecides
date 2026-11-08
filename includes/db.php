<?php
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    foreach ([DATA_DIR, UPLOAD_DIR] as $d) if (!is_dir($d)) @mkdir($d, 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 8000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE IF NOT EXISTS positions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE NOT NULL, title TEXT NOT NULL,
        tagline TEXT, sort INTEGER DEFAULT 0);
    CREATE TABLE IF NOT EXISTS polls (
        id INTEGER PRIMARY KEY AUTOINCREMENT, position_id INTEGER NOT NULL REFERENCES positions(id) ON DELETE CASCADE,
        slug TEXT UNIQUE NOT NULL, name TEXT NOT NULL, heading TEXT NOT NULL, description TEXT,
        is_open INTEGER DEFAULT 1, views INTEGER DEFAULT 0, sort INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT, poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
        name TEXT NOT NULL, party TEXT, photo TEXT, bio TEXT, sort INTEGER DEFAULT 0);
    CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
        candidate_id INTEGER NOT NULL REFERENCES candidates(id) ON DELETE CASCADE,
        voter_hash TEXT NOT NULL, ip_hash TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(poll_id, voter_hash));
    CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
        parent_id INTEGER REFERENCES comments(id) ON DELETE CASCADE,
        name TEXT NOT NULL, body TEXT NOT NULL, image TEXT, status TEXT DEFAULT 'approved',
        ip_hash TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE INDEX IF NOT EXISTS idx_votes_poll ON votes(poll_id);
    CREATE INDEX IF NOT EXISTS idx_comments_poll ON comments(poll_id);
    ");
    $cols = array_column($pdo->query('PRAGMA table_info(candidates)')->fetchAll(), 'name');
    if (!in_array('base_votes', $cols)) $pdo->exec('ALTER TABLE candidates ADD COLUMN base_votes INTEGER DEFAULT 0');
    migrate_accounts($pdo);
    if ((int)$pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn() === 0) seed($pdo);
    if (!$pdo->query("SELECT id FROM positions WHERE slug = 'questions'")->fetchColumn()) {
        $pdo->exec("INSERT INTO positions (slug, title, tagline, sort, hidden) VALUES ('questions', 'Questions', 'Questions from the community', 99, 1)");
    }
    import_legacy_votes($pdo);
    import_legacy_hor($pdo);
    fix_authentic_v1($pdo);
}

/** Match the original site exactly: constituency names as written there, answer times in UTC. Runs once. */
function fix_authentic_v1(PDO $pdo): void {
    if ($pdo->query("SELECT v FROM settings WHERE k = 'authentic_v1'")->fetchColumn()) return;
    $names = [
        'oguta-ohaji-egbema-oru-west' => 'Oguta/ Ohaji Egbema/ Oru west',
        'oru-east-orsu-orlu' => 'Oru East / Orsu / Orlu',
        'owerri-municipal-owerri-west-owerri-north' => 'Owerri Municipal / Owerri West / Owerri North',
        'imo-east-senatorial-candidates-2027' => 'IMO EAST (OWERRI ZONE)',
        'imo-west-senatorial-candidates' => 'IMO WEST (ORLU ZONE)',
        'imo-north-senatorial-candidates' => 'IMO NORTH (OKIGWE ZONE)',
    ];
    $st = $pdo->prepare('UPDATE polls SET name = ? WHERE slug = ?');
    foreach ($names as $slug => $n) $st->execute([$n, $slug]);
    // Imported answers were stored in Lagos time (UTC+1); the original shows UTC. New-site answers (after 8 Nov 2026 18:00 UTC) are already UTC.
    if (!$pdo->query("SELECT v FROM settings WHERE k = 'utc_times'")->fetchColumn()) {
        $pdo->exec("UPDATE comments SET created_at = datetime(created_at, '-1 hour') WHERE created_at < '2026-11-08 18:00:00'");
        $pdo->exec("INSERT INTO settings (k, v) VALUES ('utc_times', '1') ON CONFLICT(k) DO UPDATE SET v = excluded.v");
    }
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('authentic_v1', ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v")->execute([date('c')]);
}

/** Accounts + Q&A features (users, likes, follows, favourites, reports, password resets). */
function migrate_accounts(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL COLLATE NOCASE, email TEXT UNIQUE NOT NULL COLLATE NOCASE,
        pass_hash TEXT NOT NULL, display_name TEXT, avatar TEXT, bio TEXT, role TEXT DEFAULT 'user', status TEXT DEFAULT 'active',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, last_login TEXT);
    CREATE TABLE IF NOT EXISTS reactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, target_type TEXT NOT NULL, target_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, value INTEGER NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(target_type, target_id, user_id));
    CREATE TABLE IF NOT EXISTS follows (
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, poll_id INTEGER NOT NULL REFERENCES polls(id) ON DELETE CASCADE,
        kind TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(user_id, poll_id, kind));
    CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT, target_type TEXT NOT NULL, target_id INTEGER NOT NULL, reason TEXT NOT NULL,
        user_id INTEGER, ip_hash TEXT, status TEXT DEFAULT 'open', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS password_resets (
        token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        expires_at TEXT NOT NULL, used INTEGER DEFAULT 0);
    CREATE INDEX IF NOT EXISTS idx_react_target ON reactions(target_type, target_id);
    ");
    $add = function (string $table, string $col, string $def) use ($pdo) {
        $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        if (!in_array($col, $cols)) $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
    };
    $add('positions', 'hidden', 'INTEGER DEFAULT 0');
    $add('polls', 'user_id', 'INTEGER');
    $add('polls', 'kind', "TEXT DEFAULT 'poll'");
    $add('polls', 'base_score', 'INTEGER DEFAULT 0');
    $add('comments', 'user_id', 'INTEGER');
    $add('comments', 'email', 'TEXT');
    $add('comments', 'base_score', 'INTEGER DEFAULT 0');
}

/** Vote totals carried over from the old imodecides.com poll (read 8 Nov 2026). Runs once. */
function import_legacy_votes(PDO $pdo): void {
    $done = $pdo->query("SELECT v FROM settings WHERE k = 'legacy_votes_v1'")->fetchColumn();
    if ($done) return;
    $legacy = [
        'ahiazu-mbaise-ezinihitte' => [
            'Igbokwe Raphael Nnanna' => 1078, 'Chinedu Emeka Martins' => 348, 'Onyekwere Charles Onyebuchu' => 110,
            'Okoro Rex Chikwesiri' => 44, 'Ejekwumadu Nnamdi John' => 5, 'Anakani Ejikeme Obinwanne' => 5,
            'Anyanwu Chidiebere' => 2, 'Anyanwu Chinedu Noyerem' => 1, 'Ogoko Damian' => 1,
        ],
    ];
    $st = $pdo->prepare('UPDATE candidates SET base_votes = ? WHERE name = ? AND poll_id = (SELECT id FROM polls WHERE slug = ?)');
    foreach ($legacy as $slug => $rows) foreach ($rows as $name => $n) $st->execute([$n, $name, $slug]);
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('legacy_votes_v1', ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v")->execute([date('c')]);
}

/**
 * House of Reps constituencies carried over from the old imodecides.com (read 8 Nov 2026):
 * candidates in their original order, recorded vote totals, answers and replies.
 * Runs once. Never touches a poll that already has candidates/answers (so admin edits stay).
 */
function import_legacy_hor(PDO $pdo): void {
    if ($pdo->query("SELECT v FROM settings WHERE k = 'legacy_hor_v1'")->fetchColumn()) return;
    $file = __DIR__ . '/../legacy/hor-2026-11-08.json';
    if (!is_file($file)) return;
    $data = json_decode(file_get_contents($file), true);
    $pdo->beginTransaction();
    $getPoll = $pdo->prepare('SELECT id FROM polls WHERE slug = ?');
    $cnt = fn($sql, $id) => (int)$pdo->query(sprintf($sql, (int)$id))->fetchColumn();
    $insC = $pdo->prepare('INSERT INTO candidates (poll_id, name, party, base_votes, sort) VALUES (?, ?, ?, ?, ?)');
    $insA = $pdo->prepare('INSERT INTO comments (poll_id, parent_id, name, body, image, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($data['polls'] as $p) {
        $getPoll->execute([$p['slug']]);
        $pid = $getPoll->fetchColumn();
        if (!$pid) continue;
        $opened = date('Y-m-d 09:00:00', strtotime($p['opened']));
        $pdo->prepare('UPDATE polls SET heading = ?, views = MAX(views, ?), created_at = ? WHERE id = ?')
            ->execute([$p['heading'], $p['views'], $opened, $pid]);
        if ($cnt('SELECT COUNT(*) FROM candidates WHERE poll_id = %d', $pid) === 0) {
            foreach ($p['candidates'] as $i => $c) $insC->execute([$pid, $c['name'], $c['party'], $c['votes'], $i + 1]);
        }
        if ($cnt('SELECT COUNT(*) FROM comments WHERE poll_id = %d', $pid) === 0) {
            $map = [];
            $ordered = $p['comments'];
            usort($ordered, fn($a, $b) => ($a['parent'] ? 1 : 0) <=> ($b['parent'] ? 1 : 0)); // parents first
            foreach ($ordered as $c) {
                if (trim($c['body']) === '' && !$c['image']) continue;
                $parent = $c['parent'] ? ($map[$c['parent']] ?? null) : null;
                $insA->execute([$pid, $parent, $c['name'] ?: 'Anonymous', $c['body'], $c['image'],
                                date('Y-m-d H:i:s', strtotime($c['date']))]);
                $map[$c['id']] = (int)$pdo->lastInsertId();
            }
        }
    }
    $pdo->prepare("INSERT INTO settings (k, v) VALUES ('legacy_hor_v1', ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v")->execute([date('c')]);
    $pdo->commit();
}

function seed(PDO $pdo): void {
    $pdo->beginTransaction();
    $settings = [
        'admin_hash'       => password_hash(ADMIN_DEFAULT_PASS, PASSWORD_DEFAULT),
        'results_public'   => '1',
        'moderate'         => '0',
        'max_votes_per_ip' => '10',
        'assets_fetched'   => '0',
    ];
    $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    $positions = [
        ['governor', 'Governor', 'Who Should Lead Imo', 1],
        ['senate', 'Senate', 'Who Should Go To The Senate?', 2],
        ['house-of-representative', 'House of Representatives', 'Who Should Go To Federal House?', 3],
        ['house-of-assembly', 'House of Assembly', 'Who Should Go To State House?', 4],
    ];
    $st = $pdo->prepare('INSERT INTO positions (slug, title, tagline, sort) VALUES (?, ?, ?, ?)');
    foreach ($positions as $p) $st->execute($p);
    $hor = (int)$pdo->query("SELECT id FROM positions WHERE slug='house-of-representative'")->fetchColumn();

    $constituencies = [
        ['aboh-mbaise-ngor-okpala', 'Aboh Mbaise/Ngor Okpala'],
        ['ahiazu-mbaise-ezinihitte', 'Ahiazu/Ezinnihitte Mbaise'],
        ['ehime-mbano-ihitte-uboma-obowo', 'Ehime Mbano/Ihitte-Uboma/Obowo'],
        ['ideato-north-ideato-south', 'Ideato North/Ideato South'],
        ['mbaitoli-ikeduru', 'Mbaitoli/Ikeduru'],
        ['isiala-mbano-okigwe-onuimo', 'Isiala Mbano/Okigwe/Onuimo'],
        ['nwangele-isu-njaba-nkwerre', 'Nwangele/Njaba/ISU/Nkwerre'],
        ['oguta-ohaji-egbema-oru-west', 'Oguta/Ohaji Egbema/Oru West'],
        ['oru-east-orsu-orlu', 'Oru East/Orsu/Orlu'],
        ['owerri-municipal-owerri-west-owerri-north', 'Owerri Municipal/Owerri West/Owerri North'],
    ];
    $st = $pdo->prepare('INSERT INTO polls (position_id, slug, name, heading, sort, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($constituencies as $i => [$slug, $name]) {
        $st->execute([$hor, $slug, $name, "$name FHR Candidates 2027", $i + 1, '2026-10-28 08:31:55']);
    }
    $ahiazu = (int)$pdo->query("SELECT id FROM polls WHERE slug='ahiazu-mbaise-ezinihitte'")->fetchColumn();
    $pdo->prepare('UPDATE polls SET views = 3000 WHERE id = ?')->execute([$ahiazu]);

    $cands = [
        ['Igbokwe Raphael Nnanna', 'APC'], ['Chinedu Emeka Martins', 'YPP'],
        ['Onyekwere Charles Onyebuchu', 'PRP'], ['Okoro Rex Chikwesiri', 'ADC'],
        ['Ejekwumadu Nnamdi John', 'Accord'], ['Anakani Ejikeme Obinwanne', 'PDP'],
        ['Anyanwu Chidiebere', 'LP'], ['Anyanwu Chinedu Noyerem', 'NDP'],
        ['Ogoko Damian', 'SDP'], ['Obani Nnakire Innocent', 'AA'],
        ['Chukwudi Theodore Nneka', 'ADP'], ['Anyanwu Aloysius Valentine', 'APGA'],
        ['Okafor Nkasiobi', 'APM'], ['Agbim Kenneth Chibueze', 'APP'],
        ['Opara Maxwell Chibuike', 'NDC'], ['Nnadi Augustine Enyembe', 'NNPP'],
        ['Nwachukwu Jude Obioma', 'NRM'],
    ];
    $st = $pdo->prepare('INSERT INTO candidates (poll_id, name, party, sort) VALUES (?, ?, ?, ?)');
    foreach ($cands as $i => [$n, $p]) $st->execute([$ahiazu, $n, $p, $i + 1]);

    // Existing answers migrated from the old site
    $c = $pdo->prepare('INSERT INTO comments (poll_id, parent_id, name, body, image, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $c->execute([$ahiazu, null, 'Chukwu Patrick', 'Raphael Igbokwe served the 24 wards in Ahiazu/Ezinihite federal house while he held sway.Others went there only to save monies for their re-election.', null, '2026-10-30 14:26:13']);
    $first = (int)$pdo->lastInsertId();
    $rows = [
        [$first, 'Emma iwuh', 'That will be nice to send him back to the House, to defend his people. More grace', null, '2026-11-04 23:47:22'],
        [null, 'Ajibade Elizabeth', 'He is a youth advocate and a man of dignity and honour', 'comment-233.jpg', '2026-11-04 23:46:54'],
        [null, 'oluwadare james abiodun', 'Raphael Igbokwe is the man to beat.', null, '2026-11-04 23:46:55'],
        [null, 'Nwambara Obinna Raymond', 'Raphael provided infrastructure that made life easier for his constituents and also executed a whole lot of empowerment programs for the youths. He is the best man for the job.', null, '2026-11-04 23:47:22'],
        [null, 'Collins Nicholas', 'Rt. Hon. Raphael Nnanna. The perfect man to represent the people of Ezinihitte and Ahiazu. A rare gem indeed', null, '2026-11-04 23:47:22'],
        [null, "God'stime sunday", 'Hon Emeka Chinedu remain the best', null, '2026-11-07 12:18:43'],
        [null, 'Philip Enyinna', "How on earth will one think of going back to Egypt, when his Moses is alive and active, RT Hon Emeka Chinedu has more impact has done more than expected as a legislator, still willing to serve his people.\nHe's the right man for the job.\nAnd Ndi Ahiazu and Ezinihitte Mbaise Constituency have unitedly agreed 💯 to vote him again.", null, '2026-11-07 12:18:45'],
        [null, 'Philip Holdings', 'Everyone who is sensible enough will understand that Hon Emeka Chinedu is the man for the job', null, '2026-11-07 12:18:45'],
    ];
    foreach ($rows as $r) $c->execute([$ahiazu, $r[0], $r[1], $r[2], $r[3], $r[4]]);
    // the times above were written in Lagos time; store them in UTC like the original site
    $pdo->exec("UPDATE comments SET created_at = datetime(created_at, '-1 hour')");
    $pdo->exec("INSERT INTO settings (k, v) VALUES ('utc_times', '1')");
    $pdo->commit();
}

function setting(string $k, $default = null) {
    $st = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $v]);
}

/** Pull the original logo / share image / comment photo once, server-side. */
function ensure_legacy_assets(): void {
    if (setting('assets_fetched_v3') === '1') return;
    set_setting('assets_fetched_v3', '1'); // one attempt only; re-run from Admin → Settings
    fetch_legacy_assets();
}
function fetch_legacy_assets(): array {
    $out = [];
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'Mozilla/5.0 ImoDecides-Migrator'],
                                  'ssl' => ['verify_peer' => true]]);
    $all = LEGACY_ASSETS;
    $lf = __DIR__ . '/../legacy/hor-2026-11-08.json';
    if (is_file($lf)) $all += (json_decode(file_get_contents($lf), true)['assets'] ?? []);
    foreach ($all as $local => $url) {
        $dest = UPLOAD_DIR . '/' . $local;
        if (is_file($dest) && filesize($dest) > 0) { $out[$local] = 'exists'; continue; }
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 10]);
            $data = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $data = false;
            curl_close($ch);
        }
        if ($data) { file_put_contents($dest, $data); $out[$local] = 'downloaded'; }
        else $out[$local] = 'failed';
    }
    return $out;
}
