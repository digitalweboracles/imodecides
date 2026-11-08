<?php
/**
 * One-time import of the Senate and House of Assembly polls from the old imodecides.com.
 * Runs from the CLI at container start (docker-entrypoint.sh) and records a flag when done.
 *   php tools/sync_legacy.php          -> runs once (skips if already done)
 *   php tools/sync_legacy.php --force  -> runs again (still never overwrites polls that have candidates/answers)
 * Pulls: candidates (original order), recorded vote totals, answers/replies, answer photos, views, open date.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/functions.php';

const FLAG = 'legacy_senate_assembly_v1';
const VFLAG = 'legacy_sa_votes_v1';
const OLD = 'https://imodecides.com';
$force = in_array('--force', $argv, true);
$skipMain = !$force && setting(FLAG);

$sets = [
    'senate' => [
        'imo-east-senatorial-candidates-2027' => 'Imo East (Owerri Zone)',
        'imo-west-senatorial-candidates'      => 'Imo West (Orlu Zone)',
        'imo-north-senatorial-candidates'     => 'Imo North (Okigwe Zone)',
    ],
    'house-of-assembly' => [
        'aboh-mbaise' => 'Aboh Mbaise', 'ahiazu-mbaise' => 'Ahiazu Mbaise', 'ehime-mbano' => 'Ehime Mbano',
        'ezinihitte-mbaise' => 'Ezinnihitte Mbaise', 'ideato-north' => 'Ideato North', 'ideato-south' => 'Ideato South',
        'ihitte-uboma' => 'Ihitte/Uboma', 'ikeduru' => 'Ikeduru', 'isiala-mbano' => 'Isiala Mbano', 'isu' => 'Isu',
        'mbaitoli' => 'Mbaitoli', 'ngor-okpala' => 'Ngor Okpala', 'njaba' => 'Njaba', 'nkwerre' => 'Nkwerre',
        'nwangele' => 'Nwangele', 'obowo' => 'Obowo', 'oguta' => 'Oguta', 'ohaji-egbema' => 'Ohaji/Egbema',
        'okigwe' => 'Okigwe', 'oru-west-state-constituency-2027' => 'Oru West',
        'owerri-municipal-state-constituency-2027' => 'Owerri Municipal',
        'owerri-north-state-constituency-2027' => 'Owerri North', 'owerri-west-state-constituency-2027' => 'Owerri West',
    ],
];

$partyMap = [
    'all progressives congress' => 'APC', 'action peoples party' => 'APP', 'national democratic congress' => 'NDC',
    'peoples democratic party' => 'PDP', 'african democratic congress' => 'ADC', 'young progressives party' => 'YPP',
    'all progressives grand alliance' => 'APGA', 'african action congress' => 'AAC', 'labour party' => 'LP',
    'labour' => 'LP', 'accord' => 'Accord', 'action alliance' => 'AA', 'action democratic party' => 'ADP',
    'allied peoples movement' => 'APM', 'national rescue movement' => 'NRM', 'social democratic party' => 'SDP',
    'zenith labour party' => 'ZLP', 'new nigeria peoples party' => 'NNPP', 'peoples redemption party' => 'PRP',
];

$GLOBALS['lastHttp'] = '';
function http(string $url, ?string $post = null): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
        CURLOPT_ENCODING => '', CURLOPT_HTTPHEADER => ['Accept: */*', 'X-Requested-With: XMLHttpRequest', 'Referer: ' . OLD . '/']]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    $GLOBALS['lastHttp'] = "HTTP $code" . ($err ? " ($err)" : '') . ' len=' . strlen((string)$body) . ' head=' . substr(preg_replace('/\s+/', ' ', (string)$body), 0, 160);
    return ($body !== false && $code === 200) ? $body : null;
}

/** Fetch recorded totals for one old poll: returns [normKey(label) => votes], total, begin date, diagnostics */
function fetchVotes(int $pid): array {
    $votes = []; $total = 0; $begin = null;
    $j = http(OLD . '/wp-admin/admin-ajax.php', 'action=dem_ajax&dem_act=view&dem_pid=' . $pid);
    $diag = $GLOBALS['lastHttp'];
    $r = $j ? json_decode($j, true) : null;
    if (!empty($r['screen_html'])) {
        $rx = xp('<div>' . $r['screen_html'] . '</div>');
        foreach ($rx->query('//ul[' . cls('dem-answers') . ']/li') as $li) {
            $labNode = $rx->query('.//div[' . cls('dem-label') . ']', $li)->item(0);
            if (!$labNode) continue;
            $pctNode = $rx->query('.//span[' . cls('dem-label-percent-txt') . ']', $labNode)->item(0);
            $lab = txt($labNode); $pct = txt($pctNode);
            if ($pct !== '' && ($pos = strrpos($lab, $pct)) !== false) $lab = trim(substr($lab, 0, $pos));
            preg_match('/^(\d+)/', $li->getAttribute('title'), $vm2);
            $votes[normKey($lab)] = (int)($vm2[1] ?? 0);
        }
        if (preg_match('/Total Votes:\s*([\d,]+)/', $r['screen_html'], $tm)) $total = (int)str_replace(',', '', $tm[1]);
        $bn = $rx->query('//span[' . cls('dem-begin-date') . ']')->item(0);
        if ($bn) $begin = date('Y-m-d 09:00:00', strtotime(txt($bn)));
    }
    return [$votes, $total, $begin, $diag];
}
function xp(string $html): DOMXPath {
    $d = new DOMDocument(); libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8"?>' . $html); libxml_clear_errors();
    return new DOMXPath($d);
}
function cls(string $c): string { return "contains(concat(' ', normalize-space(@class), ' '), ' $c ')"; }
function txt(?DOMNode $n): string { return $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent)) : ''; }
function splitLabel(string $l, array $map): array {
    $l = trim(preg_replace('/\s+/u', ' ', $l));
    if (preg_match('/^(.*\S)\s+[—–-]\s+(.+)$/u', $l, $m)) {
        $p = trim($m[2]); $key = strtolower($p);
        return [trim($m[1]), $map[$key] ?? (strtoupper($p) === 'ACCORD' ? 'Accord' : $p)];
    }
    // "Full Name PARTY" (no dash): last word is the party when it is a known abbreviation
    $abbr = ['A','AA','AAC','ACCORD','ADC','ADP','APC','APGA','APM','APP','BP','DLA','LP','NDC','NDP','NNPP','NRM','PDP','PRP','SDP','YP','YPP','ZLP'];
    if (preg_match('/^(.*\S)\s+(\S+)$/u', $l, $m) && in_array(strtoupper($m[2]), $abbr, true)) {
        return [trim($m[1]), strtoupper($m[2]) === 'ACCORD' ? 'Accord' : strtoupper($m[2])];
    }
    return [$l, ''];
}
function normKey(string $s): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', $s)); }

$db = db();
$report = [];
if (!$skipMain) foreach ($sets as $posSlug => $polls) {
    $posId = (int)$db->query("SELECT id FROM positions WHERE slug = " . $db->quote($posSlug))->fetchColumn();
    $sort = 0;
    foreach ($polls as $slug => $name) {
        $sort++;
        $html = http(OLD . "/question/$slug/");
        if (!$html) { $report[] = "$slug: page fetch FAILED"; continue; }
        $x = xp($html);

        // heading: og:title (the page's first <h1> can be an editor modal)
        $og = $x->query('//meta[@property="og:title"]/@content')->item(0);
        $heading = $og ? trim($og->nodeValue) : $name;
        preg_match('/([\d.]+)(k?)\s*Views/i', txt($x->query('//body')->item(0)), $vm);
        $views = $vm ? (int)round((float)$vm[1] * ($vm[2] ? 1000 : 1)) : 0;

        // poll id + candidate order from the voting screen
        $dem = $x->query('//div[' . cls('democracy') . ']')->item(0);
        $pid = $dem ? (json_decode($dem->getAttribute('data-opts'), true)['pid'] ?? null) : null;
        $order = [];
        if ($dem) foreach ($x->query('.//ul[' . cls('dem-vote') . ']/li[.//input]', $dem) as $li) {
            $lab = txt($li); if ($lab !== '') $order[] = $lab;
        }

        // vote totals from the results endpoint
        [$votes, $total, $begin] = $pid ? fetchVotes((int)$pid) : [[], 0, null];
        if (!$order) $order = array_keys($votes);

        // create or reuse poll
        $st = $db->prepare('SELECT id FROM polls WHERE slug = ?'); $st->execute([$slug]);
        $pollId = $st->fetchColumn();
        if (!$pollId) {
            $db->prepare('INSERT INTO polls (position_id, slug, name, heading, views, sort, created_at) VALUES (?,?,?,?,?,?,?)')
               ->execute([$posId, $slug, $name, $heading, $views, $sort, $begin ?: date('Y-m-d H:i:s')]);
            $pollId = (int)$db->lastInsertId();
        } else {
            $db->prepare('UPDATE polls SET views = MAX(views, ?) WHERE id = ?')->execute([$views, $pollId]);
        }

        // candidates (only if the poll has none yet)
        $nC = 0; $sum = 0;
        if ((int)$db->query("SELECT COUNT(*) FROM candidates WHERE poll_id = $pollId")->fetchColumn() === 0) {
            $ins = $db->prepare('INSERT INTO candidates (poll_id, name, party, base_votes, sort) VALUES (?,?,?,?,?)');
            foreach ($order as $i => $lab) {
                [$cn, $party] = splitLabel($lab, $partyMap);
                $v = $votes[normKey($lab)] ?? 0; $sum += $v;
                $ins->execute([$pollId, $cn, $party, $v, $i + 1]); $nC++;
            }
        }

        // answers (only if the poll has none yet)
        $nA = 0;
        if ((int)$db->query("SELECT COUNT(*) FROM comments WHERE poll_id = $pollId")->fetchColumn() === 0) {
            $items = [];
            foreach ($x->query('//li[' . cls('comment') . ']') as $li) {
                $id = str_replace('li-comment-', '', $li->getAttribute('id'));
                $parent = null;
                for ($p = $li->parentNode; $p && $p instanceof DOMElement; $p = $p->parentNode) {
                    if ($p->nodeName === 'li' && preg_match('/\bcomment\b/', $p->getAttribute('class'))) { $parent = str_replace('li-comment-', '', $p->getAttribute('id')); break; }
                }
                $body = null; foreach ($li->childNodes as $c) if ($c instanceof DOMElement && str_contains($c->getAttribute('class'), 'comment-body')) $body = $c;
                if (!$body) continue;
                $nm = txt($x->query('.//*[@itemprop="name"]', $body)->item(0));
                $dt = $x->query('.//*[@itemprop="dateCreated"]', $body)->item(0);
                $tx = $x->query('.//*[@itemprop="text"]', $body)->item(0);
                $text = $tx ? trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />'], "\n", $tx->ownerDocument->saveHTML($tx)))))) : '';
                $imgA = $x->query('.//div[' . cls('text') . ']//a[img]', $body)->item(0);
                $items[] = ['id' => $id, 'parent' => $parent, 'name' => $nm ?: 'Anonymous', 'date' => $dt ? $dt->getAttribute('datetime') : null,
                            'body' => $text, 'img' => $imgA ? $imgA->getAttribute('href') : null];
            }
            usort($items, fn($a, $b) => ($a['parent'] ? 1 : 0) <=> ($b['parent'] ? 1 : 0));
            $map = []; $insA = $db->prepare('INSERT INTO comments (poll_id, parent_id, name, body, image, created_at) VALUES (?,?,?,?,?,?)');
            foreach ($items as $c) {
                $local = null;
                if ($c['img'] && preg_match('/\.(jpe?g|png|webp|gif)$/i', $c['img'], $em)) {
                    $local = 'legacy-c' . preg_replace('/\D/', '', $c['id']) . '.' . strtolower($em[1]);
                    if (!is_file(UPLOAD_DIR . "/$local") && ($bin = http($c['img']))) file_put_contents(UPLOAD_DIR . "/$local", $bin);
                    if (!is_file(UPLOAD_DIR . "/$local")) $local = null;
                }
                if ($c['body'] === '' && !$local) continue;
                $insA->execute([$pollId, $c['parent'] ? ($map[$c['parent']] ?? null) : null, $c['name'], $c['body'], $local,
                                $c['date'] ? date('Y-m-d H:i:s', strtotime($c['date'])) : date('Y-m-d H:i:s')]);
                $map[$c['id']] = (int)$db->lastInsertId(); $nA++;
            }
        }
        $check = ($nC === 0) ? 'kept existing' : (($sum === $total) ? 'votes match' : "VOTES $sum != $total");
        $report[] = "$posSlug/$slug: $nC candidates, $sum votes, $nA answers ($check)";
        usleep(300000);
    }
}
if (!$skipMain) {
    $failed = count(array_filter($report, fn($l) => str_contains($l, 'FAILED')));
    if ($failed === 0) set_setting(FLAG, date('c'));
    set_setting('legacy_sync_report', implode("\n", $report));
    echo "[sync] " . implode("\n[sync] ", $report) . "\n";
}

// ---- Phase 2: fill recorded vote totals for imported Senate/Assembly polls still on zero ----
if ($force || !setting(VFLAG)) {
    $vrep = []; $ok = 0;
    foreach ($sets as $posSlug => $polls) foreach ($polls as $slug => $name) {
        $st = $db->prepare('SELECT id FROM polls WHERE slug = ?'); $st->execute([$slug]); $pollId = (int)$st->fetchColumn();
        if (!$pollId) { $vrep[] = "$slug: poll missing"; continue; }
        $have = (int)$db->query("SELECT COALESCE(SUM(base_votes),0) FROM candidates WHERE poll_id = $pollId")->fetchColumn();
        if ($have > 0) { $vrep[] = "$slug: already has $have imported votes, kept"; $ok++; continue; }
        $html = http(OLD . "/question/$slug/");
        if (!$html) { $vrep[] = "$slug: page FAILED " . $GLOBALS['lastHttp']; continue; }
        $x = xp($html); $dem = $x->query('//div[' . cls('democracy') . ']')->item(0);
        $pid = $dem ? (int)(json_decode($dem->getAttribute('data-opts'), true)['pid'] ?? 0) : 0;
        [$votes, $total, $begin, $diag] = fetchVotes($pid);
        if (!$votes) { $vrep[] = "$slug: pid=$pid votes FAILED $diag"; continue; }
        $order = [];
        foreach ($x->query('.//ul[' . cls('dem-vote') . ']/li[.//input]', $dem) as $li) { $l = txt($li); if ($l !== '') $order[] = $l; }
        $upd = $db->prepare('UPDATE candidates SET base_votes = ? WHERE poll_id = ? AND name = ?');
        $sum = 0; $matched = 0;
        foreach ($order as $lab) {
            [$cn] = splitLabel($lab, $partyMap);
            $v = $votes[normKey($lab)] ?? null;
            if ($v === null) continue;
            $upd->execute([$v, $pollId, $cn]); if ($upd->rowCount()) { $matched++; $sum += $v; }
        }
        if ($begin) $db->prepare('UPDATE polls SET created_at = ? WHERE id = ?')->execute([$begin, $pollId]);
        $vrep[] = "$slug: $matched matched, $sum votes" . ($sum === $total ? ' (matches)' : " (old total $total)");
        if ($sum === $total) $ok++;
        usleep(300000);
    }
    if ($ok === array_sum(array_map('count', $sets))) set_setting(VFLAG, date('c'));
    set_setting('legacy_sync_report', trim(setting('legacy_sync_report', '') . "\n--- votes ---\n" . implode("\n", $vrep)));
    echo "[sync-votes] " . implode("\n[sync-votes] ", $vrep) . "\n";
}
