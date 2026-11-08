<?php
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    foreach ([DATA_DIR, UPLOAD_DIR] as $d) if (!is_dir($d)) @mkdir($d, 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
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
    if ((int)$pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn() === 0) seed($pdo);
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
    if (setting('assets_fetched') === '1') return;
    set_setting('assets_fetched', '1'); // one attempt only; re-run from Admin → Settings
    fetch_legacy_assets();
}
function fetch_legacy_assets(): array {
    $out = [];
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'user_agent' => 'Mozilla/5.0 ImoDecides-Migrator'],
                                  'ssl' => ['verify_peer' => true]]);
    foreach (LEGACY_ASSETS as $local => $url) {
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
