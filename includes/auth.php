<?php
// ---- user accounts ----
function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $st->execute([(int)$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}
function user_name(array $u): string { return $u['display_name'] ?: $u['username']; }
function profile_url(string $username): string { return url('profile/' . rawurlencode($username) . '/'); }

function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$u['id']]);
}
function require_login(string $back = ''): array {
    $u = current_user();
    if (!$u) {
        flash('You must login to continue.', 'err');
        redirect(url('log-in/') . '?redirect_to=' . rawurlencode($back ?: ($_SERVER['REQUEST_URI'] ?? '/')));
    }
    return $u;
}
function safe_redirect_target(?string $to): string {
    $to = (string)$to;
    return (str_starts_with($to, '/') && !str_starts_with($to, '//')) ? $to : url('');
}

/** simple per-session + per-IP throttle for auth forms */
function throttle(string $key, int $max, int $window): bool {
    $k = 'thr_' . $key . '_' . substr(ip_hash(), 0, 16);
    $now = time();
    $raw = setting($k);
    $data = $raw ? json_decode($raw, true) : ['t' => $now, 'n' => 0];
    if ($now - $data['t'] > $window) $data = ['t' => $now, 'n' => 0];
    $data['n']++;
    set_setting($k, json_encode($data));
    return $data['n'] <= $max;
}

// ---- reactions (like / dislike) ----
function score(string $type, int $id, int $base = 0): int {
    $st = db()->prepare('SELECT COALESCE(SUM(value),0) FROM reactions WHERE target_type = ? AND target_id = ?');
    $st->execute([$type, $id]);
    return $base + (int)$st->fetchColumn();
}
function my_reaction(string $type, int $id): int {
    $u = current_user(); if (!$u) return 0;
    $st = db()->prepare('SELECT value FROM reactions WHERE target_type = ? AND target_id = ? AND user_id = ?');
    $st->execute([$type, $id, $u['id']]);
    return (int)$st->fetchColumn();
}
function follow_count(int $pollId, string $kind): int {
    $st = db()->prepare('SELECT COUNT(*) FROM follows WHERE poll_id = ? AND kind = ?');
    $st->execute([$pollId, $kind]);
    return (int)$st->fetchColumn();
}
function is_following(int $pollId, string $kind): bool {
    $u = current_user(); if (!$u) return false;
    $st = db()->prepare('SELECT 1 FROM follows WHERE poll_id = ? AND kind = ? AND user_id = ?');
    $st->execute([$pollId, $kind, $u['id']]);
    return (bool)$st->fetchColumn();
}

// ---- email (SMTP if configured, else PHP mail()) ----
function send_mail(string $to, string $subject, string $body): bool {
    $from = getenv('MAIL_FROM') ?: 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'imodecide.com');
    $host = getenv('SMTP_HOST');
    if (!$host) {
        return @mail($to, $subject, $body, "From: Imo Decides <$from>\r\nContent-Type: text/plain; charset=UTF-8");
    }
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $user = getenv('SMTP_USER'); $pass = getenv('SMTP_PASS');
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port", $en, $es, 15);
    if (!$fp) return false;
    $read = function () use ($fp) { $d = ''; while (($l = fgets($fp, 515)) !== false) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
    $cmd = function (string $c, string $ok) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return str_starts_with($read(), $ok); };
    $read();
    $ehlo = 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!$cmd($ehlo, '250')) return false;
    if ($port !== 465) {
        if (!$cmd('STARTTLS', '220')) return false;
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$cmd($ehlo, '250')) return false;
    }
    if ($user && (!$cmd('AUTH LOGIN', '334') || !$cmd(base64_encode($user), '334') || !$cmd(base64_encode((string)$pass), '235'))) return false;
    if (!$cmd("MAIL FROM:<$from>", '250') || !$cmd("RCPT TO:<$to>", '25') || !$cmd('DATA', '354')) return false;
    $msg = "From: Imo Decides <$from>\r\nTo: <$to>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
         . str_replace("\n.", "\n..", str_replace("\r\n", "\n", $body));
    $ok = $cmd($msg . "\r\n.", '250');
    $cmd('QUIT', '221'); fclose($fp);
    return $ok;
}
