<?php
// AJAX endpoint to upload logo to /assets and return JSON { ok: bool, url: string }
session_start();
// Avoid exposing PHP warnings/notices in the JSON response that breaks client parsing
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ob_start();

function respond($code, $payload) {
    // Ensure no prior output breaks the JSON
    $buf = '';
    if (ob_get_length()) { $buf = ob_get_clean(); } else { ob_end_clean(); }
    // Log the response and any unexpected buffer (helpful for debugging uploads that return invalid JSON)
    $logfile = __DIR__ . '/upload_debug.log';
    $entry = date('Y-m-d H:i:s') . " | RESP code=$code payload=" . json_encode($payload) . " buffer_len=" . strlen($buf) . "\n";
    @file_put_contents($logfile, $entry, FILE_APPEND | LOCK_EX);
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['admin'])) { respond(403, ['ok'=>false,'error'=>'auth']); }

$MAX = 8 * 1024 * 1024;
$allowed = ['image/png','image/jpeg','image/jpg','image/gif','image/webp'];
// Accept either 'logo_file' (original) or 'logo2_file' (owner logo)
$fileField = null;
if (!empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $fileField = 'logo_file';
} elseif (!empty($_FILES['logo2_file']) && $_FILES['logo2_file']['error'] === UPLOAD_ERR_OK) {
    $fileField = 'logo2_file';
}

if ($fileField === null) { respond(400, ['ok'=>false,'error'=>'nofile']); }

$f = $_FILES[$fileField];

// Additional debug logging for uploads (appends minimal info to admin/upload_debug.log)
$logfile = __DIR__ . '/upload_debug.log';
$dbg = date('Y-m-d H:i:s') . " | UPLOAD attempt field=$fileField name=" . ($f['name'] ?? '') . " size=" . ($f['size'] ?? 0) . " tmp=" . ($f['tmp_name'] ?? '') . " error=" . ($f['error'] ?? '') . "\n";
@file_put_contents($logfile, $dbg, FILE_APPEND | LOCK_EX);

if ($f['size'] > $MAX) { respond(400, ['ok'=>false,'error'=>'toolarge','size'=>$f['size']]); }

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $f['tmp_name']); finfo_close($finfo);
if (!in_array($mime, $allowed)) { respond(400, ['ok'=>false,'error'=>'badtype','mime'=>$mime]); }

$assets_dir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
if (!file_exists($assets_dir)) {
    if (!@mkdir($assets_dir, 0755, true)) { respond(500, ['ok'=>false,'error'=>'mkdir','path'=>$assets_dir]); }
}
if (!is_writable($assets_dir)) { respond(500, ['ok'=>false,'error'=>'nowritable','path'=>$assets_dir]); }

$ext = pathinfo($f['name'], PATHINFO_EXTENSION);
$base = pathinfo($f['name'], PATHINFO_FILENAME);
$base = preg_replace('/[^a-zA-Z0-9_-]/','_', $base);
$filename = time() . '_' . $base . '.' . $ext;
$dest = rtrim($assets_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($f['tmp_name'], $dest)) { $err = error_get_last(); respond(500, ['ok'=>false,'error'=>'move','detail'=>$err]); }

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$logo_url = $scheme . '://' . $host . '/assets/' . $filename;
respond(200, ['ok'=>true,'url'=>$logo_url,'filename'=>$filename]);
