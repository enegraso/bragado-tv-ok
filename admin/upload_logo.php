<?php
// AJAX endpoint to upload logo to /assets and return JSON { ok: bool, url: string }
session_start();
if (empty($_SESSION['admin'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$MAX = 8 * 1024 * 1024;
$allowed = ['image/png','image/jpeg','image/jpg','image/gif','image/webp'];
if (empty($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'nofile']); exit;
}
$f = $_FILES['logo_file'];
if ($f['size'] > $MAX) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'toolarge']); exit; }
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $f['tmp_name']); finfo_close($finfo);
if (!in_array($mime, $allowed)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'badtype']); exit; }

$assets_dir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
if (!file_exists($assets_dir)) { @mkdir($assets_dir, 0755, true); }
$ext = pathinfo($f['name'], PATHINFO_EXTENSION);
$base = pathinfo($f['name'], PATHINFO_FILENAME);
$base = preg_replace('/[^a-zA-Z0-9_-]/','_', $base);
$filename = time() . '_' . $base . '.' . $ext;
$dest = rtrim($assets_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($f['tmp_name'], $dest)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'move']); exit; }

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$logo_url = $scheme . '://' . $host . '/assets/' . $filename;
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'url'=>$logo_url,'filename'=>$filename]);
