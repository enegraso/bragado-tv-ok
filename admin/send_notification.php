<?php
// send_notification.php
// Protected form to send push notifications via Expo Push API using tokens.json

$TOKENS_FILE = __DIR__ . '/tokens.json';

function load_tokens() {
    global $TOKENS_FILE;
    if (!file_exists($TOKENS_FILE)) return [];
    return json_decode(file_get_contents($TOKENS_FILE), true) ?: [];
}

function send_batch($messages) {
    $ch = curl_init('https://exp.host/--/api/v2/push/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json','Accept-encoding: gzip, deflate','Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return ['resp'=>$res,'info'=>$info,'error'=>$err];
}

function get_receipts($ids) {
    $ch = curl_init('https://exp.host/--/api/v2/push/getReceipts');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json','Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ids' => $ids]));
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return ['resp'=>$res,'info'=>$info,'error'=>$err];
}

session_start();
// require auth for admin actions
if (empty($_SESSION['admin'])) {
    header('Location: index.php'); exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // session-based auth enforced above; proceed with sending
    {
        $type = $_POST['type'] ?? 'portal';
        $priority = $_POST['priority'] ?? 'default'; // 'default' or 'high'
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $url = $_POST['url'] ?? '';
        $canSend = true;
        // server-side validation: if portal type requires a valid URL
        if ($type === 'portal') {
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $message = 'Para envíos al portal debe proporcionar una URL válida (incluya http:// o https://).';
                $canSend = false;
            }
        }

        $tokens = load_tokens();
        // filter by preferences
        $filtered = [];
        foreach ($tokens as $t) {
            if ($type === 'portal' && !$t['portal']) continue;
            if ($type === 'programacion' && !$t['programacion']) continue;
            $filtered[] = $t['token'];
        }

        $messages = [];
        // keep map of token for later receipt handling
        $tokenMap = [];
        if (!$canSend) {
            // do not proceed with sending
            echo "<div style='color:#b00;margin:12px 0;'><strong>Envío cancelado:</strong> " . htmlspecialchars($message) . "</div>";
        } else {
        foreach ($filtered as $tk) {
            $msg = [
                'to' => $tk,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => ['type'=>$type,'url'=>$url],
                'priority' => $priority
            ];
            $messages[] = $msg;
            $tokenMap[] = $tk;
        }

        $results = [];
        $tickets = []; // list of ticket ids
        $ticketToToken = []; // map ticket id => token
        $rawResponses = []; // store raw responses for debugging

        // expo allows batches; send in batches of 100
        $chunks = array_chunk($messages, 100);
        $tokenChunks = array_chunk($tokenMap, 100);
        foreach ($chunks as $i => $chunk) {
            // send with retries
            $attempt = 0;
            $maxAttempts = 3;
            $sent = null;
            while ($attempt < $maxAttempts) {
                $attempt++;
                $res = send_batch($chunk);
                if (!$res['error'] && $res['info'] && isset($res['info']['http_code']) && intval($res['info']['http_code']) === 200) {
                    $sent = $res['resp'];
                    break;
                }
                // wait before retry
                sleep(pow(2, $attempt));
            }
            if (!$sent) {
                // log and continue
                error_log("Failed to send chunk #$i after $maxAttempts attempts\n");
                continue;
            }

            // keep raw response for debugging
            $rawResponses[] = $res;

            // parse tickets response
            $decoded = json_decode($sent, true);
            // expo may return array or object with 'data'
            if (is_array($decoded) && isset($decoded[0]) && isset($decoded[0]['status'])) {
                $ticketArray = $decoded;
            } elseif (is_array($decoded) && isset($decoded['data'])) {
                $ticketArray = $decoded['data'];
            } else {
                $ticketArray = [];
            }

            // map tickets to tokens
            foreach ($ticketArray as $idx => $tkt) {
                if (isset($tkt['id'])) {
                    $ticketId = $tkt['id'];
                    $tickets[] = $ticketId;
                    // map to token from tokenChunks
                    if (isset($tokenChunks[$i][$idx])) {
                        $ticketToToken[$ticketId] = $tokenChunks[$i][$idx];
                    }
                }
            }
            $results[] = $ticketArray;
        }

        // if we have ticket ids, request receipts
        $failedTokens = [];
        }
        if (!empty($tickets)) {
            // get receipts in chunks of 100 as well
            $ticketChunks = array_chunk($tickets, 100);
            foreach ($ticketChunks as $chunk) {
                $res = get_receipts($chunk);
                if ($res['error']) {
                    error_log('get_receipts error: ' . $res['error']);
                    continue;
                }
                $decoded = json_decode($res['resp'], true);
                if (!is_array($decoded)) continue;
                // decoded should be map id => receipt
                foreach ($decoded as $ticketId => $receipt) {
                    if (isset($receipt['status']) && $receipt['status'] === 'error') {
                        // check details
                        $details = $receipt['details'] ?? null;
                        $token = $ticketToToken[$ticketId] ?? null;
                        // common unrecoverable errors: DeviceNotRegistered
                        $errorCode = $details['error'] ?? null;
                        if ($errorCode === 'DeviceNotRegistered' || ($receipt['message'] ?? '') === 'DeviceNotRegistered') {
                            if ($token) $failedTokens[] = $token;
                        }
                    }
                }
            }
        }

        // remove failed tokens from tokens.json
        if (!empty($failedTokens)) {
            $tokensFile = __DIR__ . '/tokens.json';
            if (file_exists($tokensFile)) {
                $list = json_decode(file_get_contents($tokensFile), true) ?: [];
                $newList = [];
                foreach ($list as $item) {
                    if (!in_array($item['token'], $failedTokens, true)) {
                        $newList[] = $item;
                    }
                }
                file_put_contents($tokensFile, json_encode($newList, JSON_PRETTY_PRINT));
            }
        }

        $message = 'Envío realizado. Mensajes: ' . count($messages) . '. Tickets: ' . count($tickets) . '. Removed tokens: ' . count($failedTokens);

        // Persist a concise log entry for audit (one JSON line per send)
        $logFile = __DIR__ . '/push_log.txt';
        $now = date('c');
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        // summarize rawResponses
        $respSummaries = [];
        foreach ($rawResponses as $r) {
            $code = $r['info']['http_code'] ?? null;
            $body = $r['resp'] ?? '';
            // keep body trimmed to 1024 chars
            if (is_string($body) && strlen($body) > 1024) $body = substr($body, 0, 1024) . '...';
            $respSummaries[] = ['http_code' => $code, 'body' => $body];
        }

        $logEntry = [
            'ts' => $now,
            'who' => $_SESSION['admin'] ? 'admin' : 'anonymous',
            'remote' => $remoteIp,
            'type' => $type,
            'priority' => $priority,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'counts' => ['messages' => count($messages), 'tickets' => count($tickets), 'removed' => count($failedTokens)],
            'responses' => $respSummaries,
            'tickets' => $tickets
        ];

        // Append as one JSON line
        @file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

        // Rotate log if larger than threshold (5 MB)
        $maxBytes = 5 * 1024 * 1024;
        if (file_exists($logFile) && filesize($logFile) > $maxBytes) {
            $bak = __DIR__ . '/push_log_' . date('Ymd_His') . '.txt';
            // rename current to backup
            @rename($logFile, $bak);
            // gzip the backup and remove original
            $contents = @file_get_contents($bak);
            if ($contents !== false) {
                $gz = $bak . '.gz';
                $fp = @gzopen($gz, 'w9');
                if ($fp) {
                    @gzwrite($fp, $contents);
                    @gzclose($fp);
                    @unlink($bak);
                }
            }
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Send Notification</title>
  <style>
    :root{font-family:Arial,Helvetica,sans-serif;color:#222}
    .container{max-width:820px;margin:18px auto;padding:18px;border-radius:8px;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.06);box-sizing:border-box;width:calc(100% - 32px)}
    h1{font-size:28px;margin:0 0 12px}
    .notice{padding:10px;margin:10px 0;border-radius:6px;background:#eef6ff;color:#014f86}
    .form-row{display:flex;gap:12px;align-items:center;margin:10px 0}
    .form-row label{width:180px;font-weight:600}
    .form-row .control{flex:1}
    input[type=text], input[type=password], input[type=url], select{width:100%;padding:10px;border:1px solid #d0d7de;border-radius:6px;box-sizing:border-box}
    input[type=submit]{background:#0b74de;color:#fff;border:0;padding:12px 18px;border-radius:6px;font-weight:700;cursor:pointer}
    @media (max-width:640px){
      .form-row{flex-direction:column;align-items:stretch}
      .form-row label{width:auto;margin-bottom:6px}
      .container{padding:12px;width:calc(100% - 24px)}
    }
    .debug{margin-top:18px;padding:12px;background:#f7f7f9;border-radius:6px;font-family:monospace;font-size:13px}
  </style>
</head>
<body>
<div class="container">
  <h1>Enviar Push</h1>
  <?php if($message): ?><div class="notice"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

  <form method="post">

    <div class="form-row">
      <label>Envío hacia</label>
      <div class="control">
        <select name="type">
          <option value="portal">Portal (noticias)</option>
          <option value="programacion">Programación (aviso)</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <label>Prioridad</label>
      <div class="control">
        <select name="priority">
          <option value="default">Normal</option>
          <option value="high">Alta</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <label>Título</label>
      <div class="control"><input type="text" name="title" placeholder="Título de la notificación"/></div>
    </div>

    <div class="form-row">
      <label>Mensaje</label>
      <div class="control"><input type="text" name="body" placeholder="Texto breve"/></div>
    </div>

    <div class="form-row" id="url_row">
      <label>URL (opcional)</label>
      <div class="control">
        <input type="url" id="url_input" name="url" placeholder="https://... (obligatorio para portal)" />
        <div id="url_error" style="color:#b00;font-size:13px;display:none;margin-top:6px;">Por favor ingrese una URL válida que comience con http:// o https://</div>
      </div>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
      <a href="index.php" class="button-link" style="background:#6c757d;padding:9px 14px;border-radius:6px;color:#fff;text-decoration:none;display:inline-block">Volver</a>
      <a href="edit_config.php" class="button-link" style="background:#17a2b8;padding:9px 14px;border-radius:6px;color:#fff;text-decoration:none;display:inline-block">Config</a>
      <input type="submit" value="Enviar"/>
    </div>
  </form>

  <!-- debug output removed; summary shown above -->
</div>
<script>
  (function(){
    const typeSelect = document.querySelector('select[name="type"]');
    const urlInput = document.getElementById('url_input');
    const urlError = document.getElementById('url_error');
    const form = document.querySelector('form');

    function updateUrlRequirement(){
      if (!typeSelect || !urlInput) return;
      const urlRow = document.getElementById('url_row');
      if (typeSelect.value === 'portal'){
        urlInput.required = true;
        if (urlRow) urlRow.style.display = '';
      } else {
        urlInput.required = false;
        if (urlRow) urlRow.style.display = 'none';
        urlError.style.display = 'none';
      }
    }

    typeSelect.addEventListener('change', updateUrlRequirement);
    updateUrlRequirement();

    form.addEventListener('submit', function(e){
      if (typeSelect.value === 'portal'){
        // simple client-side validation
        const v = urlInput.value.trim();
        if (!v || !(v.startsWith('http://') || v.startsWith('https://'))){
          e.preventDefault();
          urlError.style.display = 'block';
          urlInput.focus();
          return false;
        }
      }
      return true;
    });
  })();
</script>
</body>
</html>
