<?php
// register_token.php
// Accepts POST { token, deviceId, portal, programacion, action }
// action = register|unregister

header('Content-Type: application/json');

$DATA_FILE = __DIR__ . '/tokens.json';

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
if (!$json) {
    // fallback to form POST
    $json = $_POST;
}

$token = trim($json['token'] ?? '');
$device = trim($json['deviceId'] ?? '');
$portal = isset($json['portal']) ? boolval($json['portal']) : true;
$program = isset($json['programacion']) ? boolval($json['programacion']) : true;
$action = $json['action'] ?? 'register';

if (!$token) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing token']);
    exit;
}

$list = [];
if (file_exists($DATA_FILE)) {
    $list = json_decode(file_get_contents($DATA_FILE), true) ?: [];
}

// helper: find index
function find_index($list, $token, $device) {
    foreach ($list as $i => $item) {
        if ($item['token'] === $token || ($device && ($item['deviceId'] ?? '') === $device)) return $i;
    }
    return -1;
}

$idx = find_index($list, $token, $device);

if ($action === 'unregister') {
    if ($idx >= 0) {
        array_splice($list, $idx, 1);
        file_put_contents($DATA_FILE, json_encode($list, JSON_PRETTY_PRINT));
    }
    echo json_encode(['ok'=>true,'unregistered'=>true]);
    exit;
}

// register or update
$entry = [
    'token' => $token,
    'deviceId' => $device,
    'portal' => $portal ? true : false,
    'programacion' => $program ? true : false,
    'updated_at' => date('c')
];

if ($idx >= 0) {
    $list[$idx] = array_merge($list[$idx], $entry);
} else {
    $list[] = $entry;
}

file_put_contents($DATA_FILE, json_encode($list, JSON_PRETTY_PRINT));

echo json_encode(['ok'=>true,'saved'=>$entry]);
