<?php
session_start();

// session timeout
$TTL = 15*60; // 15 minutes

// password sourced from environment variable for security
$PASSWORD = getenv('ADMIN_PASSWORD') ?: null;

function is_logged(){
    if (!isset($_SESSION['admin'])) return false;
    if (!isset($_SESSION['last_activity'])) return false;
    if (time() - $_SESSION['last_activity'] > $GLOBALS['TTL']) {
        session_unset(); session_destroy(); return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

if (isset($_GET['logout'])) {
    session_unset(); session_destroy();
    header('Location: index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd = $_POST['pwd'] ?? '';
    if ($pwd === $PASSWORD) {
        $_SESSION['admin'] = true;
        $_SESSION['last_activity'] = time();
        header('Location: index.php'); exit;
    } else {
        $error = 'Clave incorrecta';
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - Bragado TV</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f5f7fb}
    .wrap{max-width:820px;margin:20px auto;padding:18px;background:#fff;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,0.06);box-sizing:border-box;width:calc(100% - 32px)}
    .row{display:flex;gap:12px;align-items:center}
    .right{margin-left:auto}
    input[type=password]{padding:10px;width:100%;border:1px solid #ddd;border-radius:6px}
    button{padding:10px 14px;border-radius:6px;border:0;background:#0b74de;color:#fff;cursor:pointer}
    a.btn{display:inline-block;padding:8px 12px;background:#0b74de;color:#fff;border-radius:6px;text-decoration:none}
    @media (max-width:640px){.wrap{margin:12px;padding:12px} .row{flex-direction:column;align-items:stretch} .right{margin-left:0}}
  </style>
</head>
<body>
<div class="wrap">
  <h2>Admin - Bragado TV</h2>
<?php if (!is_logged()): ?>
  <?php if ($error): ?><p style="color:#b00"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
  <form method="post">
    <div style="margin:12px 0"><input type="password" name="pwd" placeholder="Clave administrativa"/></div>
    <div><button type="submit">Acceder</button></div>
  </form>
<?php else: ?>
  <div class="row" style="margin-top:6px">
    <a class="btn" href="edit_config.php">Config</a>
    <a class="btn" href="send_notification.php">Notificaciones</a>
    <a class="btn right" href="?logout=1">Cerrar sesión</a>
  </div>
  <p style="margin-top:12px;color:#666">Sesión activa. Expira tras <?php echo intval($TTL/60); ?> minutos de inactividad.</p>
<?php endif; ?>
</div>
</body>
</html>
