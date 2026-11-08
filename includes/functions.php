<?php
require_once __DIR__ . '/db.php';

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE_PATH . '/' . ltrim($p, '/'); }
function abs_url(string $p = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($p);
}
function poll_url(array $poll): string { return url('question/' . $poll['slug'] . '/'); }
function position_url(array $pos): string { return url($pos['slug'] . '/'); }

function media_url(?string $file): ?string {
    if (!$file || !is_file(UPLOAD_DIR . '/' . basename($file))) return null;
    return url('media.php?f=' . rawurlencode(basename($file)));
}
function logo_url(): ?string { return media_url('logo.png'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expired. Go back and try again.'); }
}

function client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return trim(explode(',', $ip)[0]);
}
function ip_hash(): string { return hash('sha256', client_ip() . '|imo2027'); }

function voter_id(): string {
    $vid = $_COOKIE['imo_vid'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $vid)) {
        $vid = bin2hex(random_bytes(16));
        setcookie('imo_vid', $vid, ['expires' => time() + 63072000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE['imo_vid'] = $vid;
    }
    return $vid;
}
function voter_hash(int $pollId): string { return hash('sha256', voter_id() . '|' . $pollId); }

function has_voted(int $pollId): ?int {
    $st = db()->prepare('SELECT candidate_id FROM votes WHERE poll_id = ? AND voter_hash = ?');
    $st->execute([$pollId, voter_hash($pollId)]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}

function party_color(string $party): string {
    $map = ['APC' => '#1f6fb2', 'PDP' => '#d1222b', 'LP' => '#0a8f3c', 'APGA' => '#1b7a2e', 'YPP' => '#6b3fa0',
            'ADC' => '#0e7c86', 'NNPP' => '#c2410c', 'SDP' => '#b45309', 'Accord' => '#2563eb', 'PRP' => '#be123c',
            'NDP' => '#4d7c0f', 'AA' => '#7c3aed', 'ADP' => '#0369a1', 'APM' => '#a16207', 'APP' => '#15803d',
            'NDC' => '#9333ea', 'NRM' => '#0f766e', 'ZLP' => '#475569', 'YP' => '#db2777', 'BP' => '#1d4ed8'];
    if (isset($map[$party])) return $map[$party];
    $h = crc32($party) % 360;
    return "hsl($h, 55%, 38%)";
}

function party_full(string $p): string {
    $m = ['APC' => 'All Progressives Congress', 'APP' => 'Action Peoples Party', 'NDC' => 'National Democratic Congress',
          'PDP' => 'Peoples Democratic Party', 'ADC' => 'African Democratic Congress', 'YPP' => 'Young Progressives Party',
          'APGA' => 'All Progressives Grand Alliance', 'AAC' => 'African Action Congress', 'LP' => 'Labour Party',
          'Accord' => 'Accord', 'AA' => 'Action Alliance', 'ADP' => 'Action Democratic Party', 'APM' => 'Allied Peoples Movement',
          'NRM' => 'National Rescue Movement', 'SDP' => 'Social Democratic Party', 'ZLP' => 'Zenith Labour Party'];
    return $m[$p] ?? $p;
}

function nice_date(string $dt): string { return date('F j, Y', strtotime($dt)); }
function nice_datetime(string $dt): string { return date('F j, Y \a\t g:i a', strtotime($dt)); }
function compact_num(int $n): string {
    if ($n >= 1000000) return round($n / 1000000, 1) . 'm';
    if ($n >= 1000) return round($n / 1000, 1) . 'k';
    return (string)$n;
}

function poll_results(int $pollId): array {
    $st = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) + COALESCE(c.base_votes, 0) AS votes
                         FROM candidates c WHERE c.poll_id = ? ORDER BY c.sort, c.id');
    $st->execute([$pollId]);
    $rows = $st->fetchAll();
    $total = array_sum(array_column($rows, 'votes'));
    foreach ($rows as &$r) $r['pct'] = $total ? round($r['votes'] * 100 / $total, 1) : 0;
    return [$rows, (int)$total];
}

function flash(string $msg = null, string $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function redirect(string $to): void { header('Location: ' . $to); exit; }

function save_upload(array $file, string $prefix): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $info = @getimagesize($file['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2] ?? 0] ?? null;
    if (!$ext) return null;
    $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name) ? $name : null;
}

function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s ?: 'poll-' . time();
}
