<?php
// Serves images from the data/uploads folder (works with a Railway volume or cPanel)
require_once __DIR__ . '/config.php';
$f = basename($_GET['f'] ?? '');
$path = UPLOAD_DIR . '/' . $f;
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
if (!$f || !isset($types[$ext]) || !is_file($path)) { http_response_code(404); exit; }
$mtime = filemtime($path);
header('Content-Type: ' . $types[$ext]);
header('Cache-Control: public, max-age=604800');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
