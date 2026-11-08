<?php
// ===== Imo Decides 2027 — configuration =====
// On Railway: set DATA_DIR=/data and attach a volume at /data so votes/comments persist.
// On cPanel: leave as-is; data is stored in ./data (protected by .htaccess).

define('SITE_NAME', 'Imo Decides');
define('SITE_TAGLINE', 'Imo Decides 2027');
define('BRAND', '#049b01');

define('DATA_DIR', rtrim(getenv('DATA_DIR') ?: __DIR__ . '/data', '/'));
define('DB_PATH', DATA_DIR . '/imodecides.sqlite');
define('UPLOAD_DIR', DATA_DIR . '/uploads');

// Leave empty when the site is at the domain root. For a subfolder use e.g. '/imodecides'
define('BASE_PATH', rtrim(getenv('BASE_PATH') ?: '', '/'));

// First-login admin password (change it in Admin → Settings after logging in)
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_DEFAULT_PASS', getenv('ADMIN_PASSWORD') ?: 'ImoDecides@2027');

// Original site assets — pulled once on first boot from the server
define('LEGACY_ASSETS', [
    'logo.png'        => 'https://imodecides.com/wp-content/uploads/2026/10/1002890045-removebg-preview-1.png',
    'og.jpg'          => 'https://imodecides.com/wp-content/uploads/2026/10/cropped-IMG-20261026-WA0008-1.jpg',
    'icon.jpg'        => 'https://imodecides.com/wp-content/uploads/2026/10/cropped-IMG-20261026-WA0008-2-300x300.jpg',
    'comment-233.jpg' => 'https://imodecides.com/wp-content/uploads/2026/11/Snapchat-1179847210.jpg',
    'signin.png'      => 'https://imodecides.com/wp-content/uploads/2019/01/signin.png',
    'signup.png'      => 'https://imodecides.com/wp-content/uploads/2019/01/signup.png',
]);

date_default_timezone_set('UTC'); // the original site displayed times in UTC
define('POLL_END', getenv('POLL_END') ?: '2026-11-15');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
