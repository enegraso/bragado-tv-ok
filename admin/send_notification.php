<?php
// send_notification.php
// Protected form to send push notifications via Expo Push API using tokens.json

$PASSWORD = 'sergio-btv'; // same password as edit_config.php — change to secure
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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['pwd'] ?? '') !== $PASSWORD) {
        $message = 'Clave incorrecta';
    } else {
        $type = $_POST['type'] ?? 'portal';
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $url = $_POST['url'] ?? '';

        $tokens = load_tokens();
        // filter by preferences
        $filtered = [];
        foreach ($tokens as $t) {
            if ($type === 'portal' && !$t['portal']) continue;
            if ($type === 'programacion' && !$t['programacion']) continue;
            $filtered[] = $t['token'];
        }

        $messages = [];
        foreach ($filtered as $tk) {
            $msg = [
                'to' => $tk,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => ['type'=>$type,'url'=>$url]
            ];
            $messages[] = $msg;
        }

        $results = [];
        // expo allows batches; send in batches of 100
        $chunks = array_chunk($messages, 100);
        foreach ($chunks as $chunk) {
            $results[] = send_batch($chunk);
        }

        $message = 'Envío realizado. Mensajes: ' . count($messages);
    }
}

?>
<!doctype html><html><head><meta charset="utf-8"><title>Send Notification</title></head><body>
<h1>Enviar Push</h1>
<?php if($message): ?><p><strong><?php echo htmlspecialchars($message); ?></strong></p><?php endif; ?>
<form method="post">
  <label>Clave: <input type="password" name="pwd" /></label><br />
  <label>Tipo:
    <select name="type">
      <option value="portal">Portal (noticia)</option>
      <option value="programacion">Programación</option>
    </select>
  </label><br />
  <label>Título: <input type="text" name="title" /></label><br />
  <label>Mensaje: <input type="text" name="body" /></label><br />
  <label>URL (opcional): <input type="text" name="url" /></label><br />
  <input type="submit" value="Enviar" />
</form>
</body></html>
