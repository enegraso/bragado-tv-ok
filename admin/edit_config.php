<?php
$CONFIG_FILE = __DIR__ . '/config.txt';

session_start();
if (empty($_SESSION['admin'])) {
  header('Location: index.php');
  exit;
}

function extract_hex($color)
{
  if (!$color) return null;
  $c = trim($color);
  if ($c[0] === '#') return strlen($c) === 4 ? '#' . substr($c, 1) : $c;
  return null;
}

function parse_rgba($color)
{
  if (!$color) return ['#000000', 1];
  $c = trim($color);
  if (stripos($c, 'rgba(') === 0) {
    $inside = substr($c, 5, -1);
    $parts = array_map('trim', explode(',', $inside));
    if (count($parts) === 4) {
      $r = intval($parts[0]);
      $g = intval($parts[1]);
      $b = intval($parts[2]);
      $a = floatval($parts[3]);
      $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
      return [$hex, $a];
    }
  }
  $h = extract_hex($c);
  if ($h) return [$h, 1];
  return ['#000000', 1];
}

function read_config($path)
{
  if (!file_exists($path)) return ['', '', ['fondo' => '#000000', 'botonfondo' => 'rgba(255,255,255,0.08)', 'colorletras' => '#ffffff']];
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $logo = $lines[0] ?? '';
  $stream = $lines[1] ?? '';
  $extras = ['fondo' => '#000000', 'botonfondo' => 'rgba(255,255,255,0.08)', 'colorletras' => '#ffffff'];
  for ($i = 2; $i < count($lines); $i++) {
    $p = explode(':', $lines[$i], 2);
    if (count($p) == 2) $extras[strtolower(trim($p[0]))] = trim($p[1]);
  }
  return [$logo, $stream, $extras];
}

function write_config($path, $logo, $stream, $extras)
{
  $lines = [];
  $lines[] = trim($logo);
  $lines[] = trim($stream);
  foreach (['fondo', 'botonfondo', 'colorletras', 'slogan', 'boton_border', 'boton_border_width'] as $k) {
    if (isset($extras[$k])) $lines[] = "$k: " . trim($extras[$k]);
  }
  return file_put_contents($path, implode("\n", $lines) . "\n") !== false;
}

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $logo = $_POST['logo'] ?? '';
  $stream = $_POST['stream'] ?? '';
  $fondo = $_POST['fondo'] ?? '#000000';
  $botonfondo = $_POST['botonfondo'] ?? 'rgba(255,255,255,0.08)';
  $colorletras = $_POST['colorletras'] ?? '#ffffff';
  $slogan = $_POST['slogan'] ?? '';
  $boton_border = $_POST['boton_border'] ?? '';
  $boton_border_width = $_POST['boton_border_width'] ?? '';

  $extras = [
    'fondo' => $fondo,
    'botonfondo' => $botonfondo,
    'colorletras' => $colorletras,
    'slogan' => $slogan,
    'boton_border' => $boton_border,
    'boton_border_width' => $boton_border_width
  ];

  if (!empty($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['logo_file'];
    $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
    if ($f['size'] > 8 * 1024 * 1024) {
      $message = 'Archivo demasiado grande (máx 8MB)';
      $messageType = 'error';
    } else {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $f['tmp_name']);
      finfo_close($finfo);
      if (!in_array($mime, $allowed)) {
        $message = 'Tipo de archivo no permitido';
        $messageType = 'error';
      } else {
        $assets_dir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
        if (!file_exists($assets_dir)) {
          @mkdir($assets_dir, 0755, true);
        }
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $base = pathinfo($f['name'], PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
        $filename = time() . '_' . $base . '.' . $ext;
        $dest = rtrim($assets_dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
          $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? '';
          $logo_url = $scheme . '://' . $host . '/assets/' . $filename;
          $logo = $logo_url;
          $message = 'Archivo subido correctamente';
        } else {
          $message = 'Error al mover el archivo subido';
          $messageType = 'error';
        }
      }
    }
  }

  if (!$message) {
    $ok = write_config($CONFIG_FILE, $logo, $stream, $extras);
    $message = $ok ? 'Guardado correctamente' : 'Error al guardar';
    $messageType = $ok ? 'success' : 'error';
  }
}

list($logo, $stream, $extras) = read_config($CONFIG_FILE);

$f_hex = extract_hex($extras['fondo'] ?? '#000000') ?? '#000000';
$t_hex = extract_hex($extras['colorletras'] ?? '#ffffff') ?? '#ffffff';
list($btn_hex, $btn_alpha) = parse_rgba($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)');
$bb_hex = extract_hex($extras['boton_border'] ?? '#ffffff') ?? '#ffffff';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit config.txt — Bragado TV</title>
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
    input[type="color"] { -webkit-appearance: none; border: none; padding: 0; cursor: pointer; }
    input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
    input[type="color"]::-webkit-color-swatch { border: none; border-radius: 9999px; }
    input[type="color"]::-moz-color-swatch { border: none; border-radius: 9999px; }
    input[type="range"] { -webkit-appearance: none; height: 6px; border-radius: 3px; background: #334155; outline: none; }
    input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 18px; height: 18px; border-radius: 50%; background: #3b82f6; cursor: pointer; border: 2px solid #1e293b; }
    input[type="range"]::-moz-range-thumb { width: 18px; height: 18px; border-radius: 50%; background: #3b82f6; cursor: pointer; border: 2px solid #1e293b; }
    .upload-zone { border: 2px dashed #334155; transition: border-color 0.2s, background 0.2s; }
    .upload-zone:hover, .upload-zone.dragover { border-color: #3b82f6; background: rgba(59,130,246,0.05); }
    .toast { animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { transform: translateY(-12px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  </style>
</head>
<body class="min-h-screen bg-slate-950 text-white">

  <!-- Top Bar -->
  <header class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-lg border-b border-white/5">
    <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="index.php" class="w-9 h-9 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition">
          <svg class="w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-lg font-semibold">Edit config.txt</h1>
      </div>
      <a href="send_notification.php" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-teal-500/15 text-teal-400 hover:bg-teal-500/25 rounded-lg transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        Notificaciones
      </a>
    </div>
  </header>

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

    <form method="post" enctype="multipart/form-data">

      <!-- Card: Logo & Stream -->
      <div class="bg-slate-900/60 border border-white/5 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-lg bg-blue-500/15 flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <h2 class="text-base font-semibold text-slate-200">Logo & Stream</h2>
        </div>

        <!-- Logo Preview -->
        <div class="flex justify-center mb-5">
          <?php if (!empty($logo)): ?>
            <img id="logo_preview" src="<?php echo htmlspecialchars($logo); ?>" alt="logo preview"
                 class="h-20 rounded-xl bg-white/5 p-2 object-contain" onerror="this.style.display='none'"/>
          <?php else: ?>
            <img id="logo_preview" src="" alt="logo preview" class="h-20 rounded-xl bg-white/5 p-2 object-contain" style="display:none"/>
          <?php endif; ?>
        </div>

        <!-- Logo URL -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Logo URL</label>
          <input id="logo_text" type="text" name="logo" value="<?php echo htmlspecialchars($logo); ?>"
                 placeholder="https://.../logo.png"
                 class="w-full px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
        </div>

        <!-- File Upload -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Subir logo (opcional)</label>
          <div class="upload-zone rounded-xl p-6 text-center cursor-pointer" id="upload_zone">
            <input id="logo_file_input" type="file" name="logo_file" accept="image/*" class="hidden"/>
            <svg class="w-8 h-8 mx-auto text-slate-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm text-slate-400">Arrastrá una imagen o <span class="text-blue-400 font-medium">elegí un archivo</span></p>
            <p class="text-xs text-slate-500 mt-1">PNG, JPG, GIF, WebP — máx 8MB</p>
          </div>
          <div id="upload_preview" class="mt-3 hidden">
            <img id="upload_preview_img" src="" class="h-16 rounded-lg border border-white/10 bg-white/5 p-1 object-contain"/>
          </div>
        </div>

        <!-- Stream URL -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Stream URL (HLS)</label>
          <input type="text" name="stream" value="<?php echo htmlspecialchars($stream); ?>"
                 placeholder="https://.../bragadotv.m3u8"
                 class="w-full px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
        </div>

        <!-- Slogan -->
        <div>
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Slogan</label>
          <input id="slogan_text" type="text" name="slogan"
                 value="<?php echo htmlspecialchars($extras['slogan'] ?? 'Canal de streaming en vivo desde Bragado. Programación local, noticias y entrevistas.'); ?>"
                 placeholder="Texto corto para slogan"
                 class="w-full px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
        </div>
      </div>

      <!-- Card: Colores de la App -->
      <div class="bg-slate-900/60 border border-white/5 rounded-2xl p-6 mb-5">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-lg bg-purple-500/15 flex items-center justify-center">
            <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
          </div>
          <h2 class="text-base font-semibold text-slate-200">Colores de la App</h2>
        </div>

        <!-- Color de Fondo -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-slate-400 mb-2">Color de Fondo</label>
          <div class="flex items-center gap-3">
            <input type="color" id="fondo_color" value="<?php echo htmlspecialchars($f_hex); ?>" class="w-10 h-10 rounded-full shrink-0 cursor-pointer"/>
            <input type="text" id="fondo_text" name="fondo" value="<?php echo htmlspecialchars($extras['fondo'] ?? '#000000'); ?>"
                   class="flex-1 px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
          </div>
        </div>

        <!-- Color Letras -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-slate-400 mb-2">Color de Letras</label>
          <div class="flex items-center gap-3">
            <input type="color" id="texto_color" value="<?php echo htmlspecialchars($t_hex); ?>" class="w-10 h-10 rounded-full shrink-0 cursor-pointer"/>
            <input type="text" id="texto_text" name="colorletras" value="<?php echo htmlspecialchars($extras['colorletras'] ?? '#ffffff'); ?>"
                   class="flex-1 px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
          </div>
        </div>

        <!-- Botón Fondo (RGBA) -->
        <div>
          <label class="block text-sm font-medium text-slate-400 mb-2">Botón Fondo (color + opacidad)</label>
          <div class="flex items-center gap-3 mb-3">
            <input type="color" id="boton_color" value="<?php echo htmlspecialchars($btn_hex); ?>" class="w-10 h-10 rounded-full shrink-0 cursor-pointer"/>
            <input type="text" id="boton_text" name="botonfondo" value="<?php echo htmlspecialchars($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>"
                   class="flex-1 px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
          </div>
          <div class="flex items-center gap-3 ml-[52px]">
            <span class="text-xs text-slate-500 w-14">Opacidad</span>
            <input type="range" id="boton_alpha" min="0" max="1" step="0.01" value="<?php echo htmlspecialchars($btn_alpha); ?>" class="flex-1"/>
            <span id="boton_alpha_label" class="text-xs text-slate-400 w-10 text-right"><?php echo round($btn_alpha * 100); ?>%</span>
          </div>
        </div>
      </div>

      <!-- Card: Ajustes TV -->
      <div class="bg-slate-900/60 border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <h2 class="text-base font-semibold text-slate-200">Ajustes para TV</h2>
            <p class="text-xs text-slate-500">Visibles en Android TV</p>
          </div>
        </div>

        <!-- Borde Botón Color -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-2">Borde del Botón (color)</label>
          <div class="flex items-center gap-3">
            <input type="color" id="boton_border_color" value="<?php echo htmlspecialchars($bb_hex); ?>" class="w-10 h-10 rounded-full shrink-0 cursor-pointer"/>
            <input type="text" id="boton_border_text" name="boton_border" value="<?php echo htmlspecialchars($extras['boton_border'] ?? '#ffffff'); ?>"
                   class="flex-1 px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
          </div>
        </div>

        <!-- Borde Ancho -->
        <div>
          <label class="block text-sm font-medium text-slate-400 mb-2">Borde del Botón (grosor)</label>
          <div class="flex items-center gap-3">
            <input type="number" min="0" max="20" id="boton_border_width" name="boton_border_width"
                   value="<?php echo htmlspecialchars($extras['boton_border_width'] ?? '3'); ?>"
                   class="w-24 px-4 py-2.5 bg-slate-800/60 border border-white/10 rounded-xl text-sm text-white font-mono placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 transition"/>
            <span class="text-sm text-slate-500">px</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <a href="index.php"
           class="px-5 py-2.5 text-sm font-medium text-slate-300 bg-white/5 hover:bg-white/10 rounded-xl transition">
          Volver
        </a>
        <a href="send_notification.php"
           class="px-5 py-2.5 text-sm font-medium text-teal-400 bg-teal-500/15 hover:bg-teal-500/25 rounded-xl transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          Notificaciones
        </a>
        <button type="submit"
                class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-xl shadow-lg shadow-blue-600/25 transition flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Guardar
        </button>
      </div>
    </form>

  </main>

  <!-- Upload Progress Modal -->
  <div id="upload_modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm items-center justify-center z-50 hidden">
    <div class="bg-slate-800 border border-white/10 rounded-2xl p-6 max-w-sm w-full mx-4 text-center shadow-2xl">
      <div class="w-12 h-12 rounded-full bg-blue-500/15 flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-blue-400 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
      </div>
      <h3 class="text-white font-semibold mb-3">Subiendo imagen...</h3>
      <div class="h-2 bg-slate-700 rounded-full overflow-hidden mb-2">
        <div id="upload_progress_bar" class="h-full bg-blue-500 rounded-full transition-all duration-300" style="width:0%"></div>
      </div>
      <div id="upload_progress_text" class="text-sm text-slate-400">0%</div>
    </div>
  </div>

  <script>
    function setVal(idColor, idText) {
      const colorInput = document.getElementById(idColor);
      const textInput = document.getElementById(idText);
      if (!colorInput || !textInput) return;
      colorInput.addEventListener('input', () => {
        textInput.value = colorInput.value;
      });
      textInput.addEventListener('input', () => {
        const v = textInput.value.trim();
        if (/^#([0-9a-fA-F]{3}){1,2}$/.test(v)) {
          colorInput.value = v;
        }
      });
    }

    function initBoton() {
      const color = document.getElementById('boton_color');
      const alpha = document.getElementById('boton_alpha');
      const text = document.getElementById('boton_text');
      const label = document.getElementById('boton_alpha_label');

      function update() {
        const c = color.value;
        const a = parseFloat(alpha.value);
        const r = parseInt(c.substr(1, 2), 16);
        const g = parseInt(c.substr(3, 2), 16);
        const b = parseInt(c.substr(5, 2), 16);
        text.value = 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
        if (label) label.textContent = Math.round(a * 100) + '%';
      }
      color.addEventListener('input', update);
      alpha.addEventListener('input', update);
      update();
    }

    document.addEventListener('DOMContentLoaded', function() {
      setVal('fondo_color', 'fondo_text');
      setVal('texto_color', 'texto_text');
      setVal('boton_border_color', 'boton_border_text');
      initBoton();

      // Logo preview live update
      const logoInput = document.getElementById('logo_text');
      const logoPreview = document.getElementById('logo_preview');
      if (logoInput && logoPreview) {
        logoInput.addEventListener('input', () => {
          const v = logoInput.value.trim();
          if (!v) { logoPreview.style.display = 'none'; logoPreview.src = ''; return; }
          logoPreview.src = v;
          logoPreview.style.display = 'block';
        });
      }

      // Upload zone click
      const uploadZone = document.getElementById('upload_zone');
      const logoFileInput = document.getElementById('logo_file_input');
      if (uploadZone && logoFileInput) {
        uploadZone.addEventListener('click', () => logoFileInput.click());
        uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
        uploadZone.addEventListener('drop', (e) => {
          e.preventDefault();
          uploadZone.classList.remove('dragover');
          if (e.dataTransfer.files.length) { logoFileInput.files = e.dataTransfer.files; logoFileInput.dispatchEvent(new Event('change')); }
        });
      }

      // AJAX upload with progress
      const uploadModal = document.getElementById('upload_modal');
      const progressBar = document.getElementById('upload_progress_bar');
      const progressText = document.getElementById('upload_progress_text');
      const uploadPreview = document.getElementById('upload_preview');
      const uploadPreviewImg = document.getElementById('upload_preview_img');

      if (logoFileInput) {
        logoFileInput.addEventListener('change', () => {
          const f = logoFileInput.files && logoFileInput.files[0];
          if (!f) return;
          const fd = new FormData();
          fd.append('logo_file', f);
          const xhr = new XMLHttpRequest();
          xhr.open('POST', 'upload_logo.php', true);
          xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
              const pct = Math.round((e.loaded / e.total) * 100);
              progressBar.style.width = pct + '%';
              progressText.textContent = pct + '%';
              uploadModal.style.display = 'flex';
            }
          };
          xhr.onload = function() {
            uploadModal.style.display = 'none';
            if (xhr.status === 200) {
              try {
                const j = JSON.parse(xhr.responseText);
                if (j.ok && j.url) {
                  if (logoInput) { logoInput.value = j.url; logoInput.dispatchEvent(new Event('input')); }
                  if (uploadPreviewImg) { uploadPreviewImg.src = j.url; uploadPreview.style.display = 'block'; }
                } else {
                  alert('Error subiendo imagen: ' + (j.error || 'desconocido'));
                }
              } catch (e) { alert('Respuesta inválida del servidor'); }
            } else { alert('Error al subir imagen. Código: ' + xhr.status); }
          };
          xhr.onerror = function() { uploadModal.style.display = 'none'; alert('Error de red al subir'); };
          xhr.send(fd);
        });
      }
    });
  </script>
</body>
</html>
