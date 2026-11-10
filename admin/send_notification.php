<?php
$TOKENS_FILE = __DIR__ . '/tokens.json';

function load_tokens() {
    global $TOKENS_FILE;
    if (!file_exists($TOKENS_FILE)) return [];
    return json_decode(file_get_contents($TOKENS_FILE), true) ?: [];
}

function send_batch($messages) {
    $ch = curl_init('https://exp.host/--/api/v2/push/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // accept and decode compressed responses
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json','Accept-encoding: gzip, deflate','Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messages));
    // reasonable timeouts
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return ['resp'=>$res,'info'=>$info,'error'=>$err];
}

function get_receipts($ids) {
    $ch = curl_init('https://exp.host/--/api/v2/push/getReceipts');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json','Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ids' => $ids]));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return ['resp'=>$res,'info'=>$info,'error'=>$err];
}

session_start();
if (empty($_SESSION['admin'])) {
    header('Location: index.php'); exit;
}

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    {
        $type = $_POST['type'] ?? 'portal';
        $priority = $_POST['priority'] ?? 'default';
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $url = $_POST['url'] ?? '';
        $canSend = true;
        if ($type === 'portal') {
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $message = 'Para envíos al portal debe proporcionar una URL válida (incluya http:// o https://).';
                $messageType = 'error';
                $canSend = false;
            }
        }

        $tokens = load_tokens();
        $filtered = [];
        foreach ($tokens as $t) {
            if ($type === 'portal' && !$t['portal']) continue;
            if ($type === 'programacion' && !$t['programacion']) continue;
            $filtered[] = $t['token'];
        }

        $messages = [];
        $tokenMap = [];
        if ($canSend) {
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
            $tickets = [];
            $ticketToToken = [];
            $rawResponses = [];

            $chunks = array_chunk($messages, 100);
            $tokenChunks = array_chunk($tokenMap, 100);
            foreach ($chunks as $i => $chunk) {
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
                    sleep(pow(2, $attempt));
                }
                if (!$sent) {
                    error_log("Failed to send chunk #$i after $maxAttempts attempts\n");
                    continue;
                }

                $rawResponses[] = $res;
                $decoded = json_decode($sent, true);
                if (is_array($decoded) && isset($decoded[0]) && isset($decoded[0]['status'])) {
                    $ticketArray = $decoded;
                } elseif (is_array($decoded) && isset($decoded['data'])) {
                    $ticketArray = $decoded['data'];
                } else {
                    $ticketArray = [];
                }

                foreach ($ticketArray as $idx => $tkt) {
                    if (isset($tkt['id'])) {
                        $ticketId = $tkt['id'];
                        $tickets[] = $ticketId;
                        if (isset($tokenChunks[$i][$idx])) {
                            $ticketToToken[$ticketId] = $tokenChunks[$i][$idx];
                        }
                    }
                }
                $results[] = $ticketArray;
            }

            $failedTokens = [];

            if (!empty($tickets)) {
                $ticketChunks = array_chunk($tickets, 100);
                foreach ($ticketChunks as $chunk) {
                    $res = get_receipts($chunk);
                    if ($res['error']) {
                        error_log('get_receipts error: ' . $res['error']);
                        continue;
                    }
                    $decoded = json_decode($res['resp'], true);
                    if (!is_array($decoded)) continue;
                    foreach ($decoded as $ticketId => $receipt) {
                        if (isset($receipt['status']) && $receipt['status'] === 'error') {
                            $details = $receipt['details'] ?? null;
                            $token = $ticketToToken[$ticketId] ?? null;
                            $errorCode = $details['error'] ?? null;
                            if ($errorCode === 'DeviceNotRegistered' || ($receipt['message'] ?? '') === 'DeviceNotRegistered') {
                                if ($token) $failedTokens[] = $token;
                            }
                        }
                    }
                }
            }

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

            $message = 'Envío realizado. Mensajes: ' . count($messages) . '. Tickets: ' . count($tickets) . '. Tokens eliminados: ' . count($failedTokens);
            $messageType = 'success';

            $logFile = __DIR__ . '/push_log.txt';
            $now = date('c');
            $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'cli';
            $respSummaries = [];
            foreach ($rawResponses as $r) {
                $code = $r['info']['http_code'] ?? null;
                $bodyLog = $r['resp'] ?? '';
                if (is_string($bodyLog) && strlen($bodyLog) > 1024) $bodyLog = substr($bodyLog, 0, 1024) . '...';
                $respSummaries[] = ['http_code' => $code, 'body' => $bodyLog];
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

            @file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

            $maxBytes = 5 * 1024 * 1024;
            if (file_exists($logFile) && filesize($logFile) > $maxBytes) {
                $bak = __DIR__ . '/push_log_' . date('Ymd_His') . '.txt';
                @rename($logFile, $bak);
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
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notificaciones — Bragado TV</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#93c5fd',400:'#60a5fa',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a5a' }
          }
        }
      }
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', system-ui, sans-serif; }
    .segmented-btn { transition: all 0.2s; }
    .segmented-btn.active { background: rgba(20,184,166,0.2); color: #2dd4bf; border-color: rgba(20,184,166,0.4); }
    .segmented-btn:not(.active) { background: transparent; color: #94a3b8; border-color: transparent; }
    .segmented-btn:not(.active):hover { background: rgba(255,255,255,0.03); color: #cbd5e1; }
    .toast { animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { transform: translateY(-12px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body class="min-h-screen bg-slate-950 text-white">

  <!-- Top Bar -->
  <header class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-lg border-b border-white/5">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="edit_config.php" class="w-9 h-9 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition">
          <svg class="w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-lg font-semibold">Notificaciones Push</h1>
      </div>
      <a href="edit_config.php" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-white/5 text-slate-300 hover:bg-white/10 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Config
      </a>
    </div>
  </header>

  <?php if (!empty($_SESSION['admin'])) include __DIR__ . '/stream_status.php'; ?>

  <main class="max-w-3xl mx-auto px-4 py-6 pb-24">

    <!-- Toast Message -->
    <?php if ($message): ?>
      <div class="toast mb-6 px-5 py-3.5 rounded-xl flex items-center gap-3 <?php echo $messageType === 'error' ? 'bg-red-500/15 border border-red-500/30 text-red-300' : 'bg-emerald-500/15 border border-emerald-500/30 text-emerald-300'; ?>">
        <?php if ($messageType === 'error'): ?>
          <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <?php else: ?>
          <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <?php endif; ?>
        <span class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <form method="post" id="notif_form">

      <!-- Card: Configuración del Envío -->
      <div class="bg-slate-900/60 border border-white/5 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-lg bg-teal-500/15 flex items-center justify-center">
            <svg class="w-5 h-5 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          </div>
          <h2 class="text-base font-semibold text-slate-200">Configuración del Envío</h2>
        </div>

        <!-- Type Selector -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-slate-400 mb-2">Envío hacia</label>
          <div class="flex gap-2">
            <button type="button" id="type_portal" data-value="portal"
                    class="segmented-btn active flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium" onclick="setType('portal')">
              Portal (noticias)
            </button>
            <button type="button" id="type_programacion" data-value="programacion"
                    class="segmented-btn flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium" onclick="setType('programacion')">
              Programación
            </button>
          </div>
          <input type="hidden" name="type" id="type_input" value="portal"/>
        </div>

        <!-- Priority -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-slate-400 mb-2">Prioridad</label>
          <div class="flex gap-2">
            <button type="button" id="prio_default" data-value="default"
                    class="segmented-btn active flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium" onclick="setPriority('default')">
              Normal
            </button>
            <button type="button" id="prio_high" data-value="high"
                    class="segmented-btn flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium" onclick="setPriority('high')">
              Alta
            </button>
          </div>
          <input type="hidden" name="priority" id="priority_input" value="default"/>
        </div>

        <!-- Title -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Título</label>
          <input type="text" name="title" required placeholder="Título de la notificación"
                 class="w-full px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40 transition"/>
        </div>

        <!-- Body -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Mensaje</label>
          <input type="text" name="body" required placeholder="Texto breve del mensaje"
                 class="w-full px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40 transition"/>
        </div>

        <!-- URL (conditional) -->
        <div id="url_row">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">URL de la nota web</label>
          <input type="url" id="url_input" name="url" placeholder="https://... (obligatorio para portal)"
                 class="w-full px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-teal-500/40 transition"/>
          <div id="url_error" class="hidden mt-2 text-sm text-red-400 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Por favor ingrese una URL válida que comience con http:// o https://
          </div>
        </div>
      </div>

      <!-- Card: Info -->
      <div class="bg-slate-900/60 border border-white/5 rounded-2xl p-5 mb-6">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-lg bg-blue-500/15 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <p class="text-sm text-slate-300 font-medium">¿Cómo funciona?</p>
            <p class="text-xs text-slate-500 mt-1">Las notificaciones se envían a todos los dispositivos registrados que coincidan con el tipo seleccionado. Los tokens inválidos se eliminan automáticamente.</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <div class="mr-auto flex items-center gap-2 px-4 py-2.5 bg-white/5 rounded-xl">
          <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span class="text-sm text-slate-300 font-medium"><?php echo count(load_tokens()); ?></span>
          <span class="text-sm text-slate-500">tokens registrados</span>
        </div>
        <a href="index.php"
           class="px-5 py-2.5 text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 rounded-xl transition">
          Volver
        </a>
        <button type="submit"
                class="px-6 py-2.5 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-500 rounded-xl shadow-lg shadow-teal-600/25 transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          Enviar
        </button>
      </div>
    </form>

  </main>

  <script>
    function setType(v) {
      document.getElementById('type_input').value = v;
      document.getElementById('type_portal').className = 'segmented-btn ' + (v === 'portal' ? 'active' : '') + ' flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium';
      document.getElementById('type_programacion').className = 'segmented-btn ' + (v === 'programacion' ? 'active' : '') + ' flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium';
      const urlRow = document.getElementById('url_row');
      const urlInput = document.getElementById('url_input');
      if (v === 'portal') {
        urlRow.style.display = '';
        urlInput.required = true;
      } else {
        urlRow.style.display = 'none';
        urlInput.required = false;
        document.getElementById('url_error').style.display = 'none';
      }
    }

    function setPriority(v) {
      document.getElementById('priority_input').value = v;
      document.getElementById('prio_default').className = 'segmented-btn ' + (v === 'default' ? 'active' : '') + ' flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium';
      document.getElementById('prio_high').className = 'segmented-btn ' + (v === 'high' ? 'active' : '') + ' flex-1 px-4 py-2.5 rounded-xl border text-sm font-medium';
    }

    (function(){
      const form = document.getElementById('notif_form');
      const urlInput = document.getElementById('url_input');
      const urlError = document.getElementById('url_error');

      form.addEventListener('submit', function(e){
        const type = document.getElementById('type_input').value;
        if (type === 'portal') {
          const v = urlInput.value.trim();
          if (!v || !(v.startsWith('http://') || v.startsWith('https://'))) {
            e.preventDefault();
            urlError.classList.remove('hidden');
            urlInput.focus();
            return false;
          }
        }
        return true;
      });

      setType('portal');
    })();
  </script>
</body>
</html>
