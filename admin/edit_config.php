<?php
// Simple config editor for tvbragado/config.txt
// WARNING: This is intentionally minimal. Place it in the same directory as config.txt
// and set a safer password before using. This script overwrites config.txt.

// Note: authentication is handled by admin/index.php session
// CONFIG_FILE path
 $CONFIG_FILE = __DIR__ . '/config.txt';

session_start();
// require auth
if (empty($_SESSION['admin'])) {
    header('Location: index.php'); exit;
}

function extract_hex($color) {
    // Return hex color if input starts with #, otherwise null
    if (!$color) return null;
    $c = trim($color);
    if ($c[0] === '#') return strlen($c)===4 ? '#'.substr($c,1) : $c;
    return null;
}

function parse_rgba($color) {
    // returns [hex, alpha]
    if (!$color) return ['#000000', 1];
    $c = trim($color);
    if (stripos($c, 'rgba(')===0) {
        $inside = substr($c,5,-1);
        $parts = array_map('trim', explode(',', $inside));
        if (count($parts)===4) {
            $r = intval($parts[0]); $g = intval($parts[1]); $b = intval($parts[2]); $a = floatval($parts[3]);
            $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
            return [$hex, $a];
        }
    }
    $h = extract_hex($c);
    if ($h) return [$h, 1];
    return ['#000000', 1];
}

function read_config($path) {
    if (!file_exists($path)) return ['', '', ['fondo'=>'#000000','botonfondo'=>'rgba(255,255,255,0.08)','colorletras'=>'#ffffff']];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logo = $lines[0] ?? '';
    $stream = $lines[1] ?? '';
    $extras = ['fondo'=>'#000000','botonfondo'=>'rgba(255,255,255,0.08)','colorletras'=>'#ffffff'];
    for ($i=2;$i<count($lines);$i++){
        $p = explode(':',$lines[$i],2);
        if (count($p)==2) $extras[strtolower(trim($p[0]))] = trim($p[1]);
    }
    return [$logo, $stream, $extras];
}

function write_config($path, $logo, $stream, $extras) {
    $lines = [];
    $lines[] = trim($logo);
    $lines[] = trim($stream);
    // Write known keys in a stable order so parsing remains predictable
    foreach (['fondo','botonfondo','colorletras','slogan','boton_border','boton_border_width'] as $k) {
        if (isset($extras[$k])) $lines[] = "$k: " . trim($extras[$k]);
    }
    return file_put_contents($path, implode("\n", $lines) . "\n") !== false;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission (user must be logged in via admin/index.php)
    $logo = $_POST['logo'] ?? '';
    $stream = $_POST['stream'] ?? '';
    $fondo = $_POST['fondo'] ?? '#000000';
    $botonfondo = $_POST['botonfondo'] ?? 'rgba(255,255,255,0.08)';
    $colorletras = $_POST['colorletras'] ?? '#ffffff';
    // new fields
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

    $ok = write_config($CONFIG_FILE, $logo, $stream, $extras);
    $message = $ok ? 'Guardado correctamente' : 'Error al guardar';
}

list($logo,$stream,$extras) = read_config($CONFIG_FILE);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit config.txt</title>
  <style>
    :root{font-family:Arial,Helvetica,sans-serif;color:#222}
    .container{max-width:820px;margin:18px auto;padding:18px;border-radius:8px;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,0.06);box-sizing:border-box;width:calc(100% - 32px)}
    h1{font-size:24px;margin:0 0 12px}
    .form-row{display:flex;gap:12px;align-items:center;margin:10px 0}
    .form-row label{width:180px;font-weight:600}
    .form-row .control{flex:1}
    input[type=text], input[type=password], input[type=range], select{width:100%;padding:8px;border:1px solid #d0d7de;border-radius:6px}
    input[type=submit]{background:#0b74de;color:#fff;border:0;padding:10px 16px;border-radius:6px;font-weight:700;cursor:pointer}
    .color-swatch{display:inline-block;width:28px;height:28px;border:1px solid #ccc;margin-left:8px;vertical-align:middle;border-radius:4px}
    .logo-preview{width:100%;height:auto;max-width:480px;display:block;margin:0 auto 12px}
    @media (max-width:640px){.form-row{flex-direction:column;align-items:stretch}.form-row label{width:auto;margin-bottom:6px}}
    .hint{font-size:13px;color:#666}
  </style>
</head>
<body>
  <div class="container">
    <h1>Edit config.txt</h1>
    <?php if($message):?><div class="hint"><strong><?php echo htmlspecialchars($message); ?></strong></div><?php endif; ?>
    <form method="post">
    <!-- Authentication handled via admin/index.php session -->

      <?php if (!empty($logo)): ?>
        <div style="text-align:center;margin-bottom:12px">
          <img id="logo_preview" src="<?php echo htmlspecialchars($logo); ?>" alt="logo" class="logo-preview" onerror="this.style.display='none'" />
        </div>
      <?php else: ?>
        <div style="text-align:center;margin-bottom:12px">
          <img id="logo_preview" src="" alt="logo" class="logo-preview" style="display:none" />
        </div>
      <?php endif; ?>

      <div class="form-row">
        <label>Logo URL</label>
        <div class="control"><input id="logo_text" type="text" name="logo" value="<?php echo htmlspecialchars($logo); ?>" placeholder="https://.../logo.png"/></div>
      </div>

      <div class="form-row">
        <label>Stream URL (HLS)</label>
        <div class="control"><input type="text" name="stream" value="<?php echo htmlspecialchars($stream); ?>" placeholder="https://.../bragadotv.m3u8"/></div>
      </div>

      <div class="form-row">
        <label>Slogan</label>
        <div class="control"><input type="text" id="slogan_text" name="slogan" value="<?php echo htmlspecialchars($extras['slogan'] ?? 'Canal de streaming en vivo desde Bragado. Programación local, noticias y entrevistas.'); ?>" placeholder="Texto corto para slogan"/></div>
      </div>

      <div class="form-row">
        <label>Fondo</label>
        <div class="control">
          <?php $f_hex = extract_hex($extras['fondo'] ?? '#000000') ?? '#000000'; ?>
          <input type="color" id="fondo_color" value="<?php echo htmlspecialchars($f_hex); ?>" />
          <input type="text" id="fondo_text" name="fondo" value="<?php echo htmlspecialchars($extras['fondo'] ?? '#000000'); ?>" />
          <span id="fondo_sw" class="color-swatch" style="background:<?php echo htmlspecialchars($extras['fondo'] ?? '#000000'); ?>"></span>
        </div>
      </div>

      <div class="form-row">
        <label>Botón fondo</label>
        <div class="control">
          <?php list($btn_hex,$btn_alpha) = parse_rgba($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>
          <input type="color" id="boton_color" value="<?php echo htmlspecialchars($btn_hex); ?>" />
          <input type="range" id="boton_alpha" min="0" max="1" step="0.01" value="<?php echo htmlspecialchars($btn_alpha); ?>" />
          <input type="text" id="boton_text" name="botonfondo" value="<?php echo htmlspecialchars($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>" />
          <span id="boton_sw" class="color-swatch" style="background:<?php echo htmlspecialchars($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>"></span>
        </div>
      </div>

      <div class="form-row">
        <label>Borde botón (color)</label>
        <div class="control">
          <?php $bb_hex = extract_hex($extras['boton_border'] ?? '#ffffff') ?? '#ffffff'; ?>
          <input type="color" id="boton_border_color" value="<?php echo htmlspecialchars($bb_hex); ?>" />
          <input type="text" id="boton_border_text" name="boton_border" value="<?php echo htmlspecialchars($extras['boton_border'] ?? '#ffffff'); ?>" />
          <span id="boton_border_sw" class="color-swatch" style="background:<?php echo htmlspecialchars($extras['boton_border'] ?? '#ffffff'); ?>"></span>
        </div>
      </div>

      <div class="form-row">
        <label>Borde (px)</label>
        <div class="control">
          <input type="number" min="0" max="20" id="boton_border_width" name="boton_border_width" value="<?php echo htmlspecialchars($extras['boton_border_width'] ?? '3'); ?>" />
        </div>
      </div>

      <div class="form-row">
        <label>Color letras</label>
        <div class="control">
          <?php $t_hex = extract_hex($extras['colorletras'] ?? '#ffffff') ?? '#ffffff'; ?>
          <input type="color" id="texto_color" value="<?php echo htmlspecialchars($t_hex); ?>" />
          <input type="text" id="texto_text" name="colorletras" value="<?php echo htmlspecialchars($extras['colorletras'] ?? '#ffffff'); ?>" />
          <span id="texto_sw" class="color-swatch" style="background:<?php echo htmlspecialchars($extras['colorletras'] ?? '#ffffff'); ?>"></span>
        </div>
      </div>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
      <a href="index.php" class="button-link" style="background:#6c757d;padding:9px 14px;border-radius:6px;color:#fff;text-decoration:none;display:inline-block">Volver</a>
      <a href="send_notification.php" class="button-link" style="background:#17a2b8;padding:9px 14px;border-radius:6px;color:#fff;text-decoration:none;display:inline-block">Notificaciones</a>
      <input type="submit" value="Guardar" />
    </div>
    </form>

    <p class="hint">Al subir este archivo a la raíz donde está config.txt, el formulario permitirá editar y sobrescribir config.txt.</p>

    <script>
    function setVal(idInput, idColor, idText, idSwatch) {
      const colorInput = document.getElementById(idColor);
      const textInput = document.getElementById(idText);
      const sw = document.getElementById(idSwatch);
      if (!colorInput || !textInput || !sw) return;
      colorInput.addEventListener('input', () => {
        const v = colorInput.value;
        textInput.value = v;
        sw.style.background = v;
      });
      textInput.addEventListener('input', () => {
        const v = textInput.value.trim();
        // if v is hex like #rrggbb update color input
        if (/^#([0-9a-fA-F]{3}){1,2}$/.test(v)) {
          colorInput.value = v;
        }
        sw.style.background = v;
      });
      // init
      sw.style.background = textInput.value;
    }

    // boton: need to combine color + alpha into rgba text
    function initBoton() {
      const color = document.getElementById('boton_color');
      const alpha = document.getElementById('boton_alpha');
      const text = document.getElementById('boton_text');
      const sw = document.getElementById('boton_sw');
      function update() {
        const c = color.value;
        const a = parseFloat(alpha.value);
        // convert hex to rgb
        const r = parseInt(c.substr(1,2),16);
        const g = parseInt(c.substr(3,2),16);
        const b = parseInt(c.substr(5,2),16);
        const rgba = 'rgba('+r+','+g+','+b+','+a+')';
        text.value = rgba;
        sw.style.background = rgba;
      }
      color.addEventListener('input', update);
      alpha.addEventListener('input', update);
      text.addEventListener('input', () => { sw.style.background = text.value; });
      update();
    }

    document.addEventListener('DOMContentLoaded', function(){
      setVal('fondo_text','fondo_color','fondo_text','fondo_sw');
      setVal('texto_text','texto_color','texto_text','texto_sw');
      initBoton();
      // logo preview live update
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

      // make swatches clickable to open color pickers
      const swatchBindings = [
        {sw: 'fondo_sw', color: 'fondo_color', text: 'fondo_text'},
        {sw: 'boton_sw', color: 'boton_color', text: 'boton_text'},
        {sw: 'texto_sw', color: 'texto_color', text: 'texto_text'},
        {sw: 'boton_border_sw', color: 'boton_border_color', text: 'boton_border_text'}
      ];
      swatchBindings.forEach(b => {
        const sw = document.getElementById(b.sw);
        const color = document.getElementById(b.color);
        const txt = document.getElementById(b.text);
        if (sw && color) {
          sw.style.cursor = 'pointer';
          sw.addEventListener('click', () => { try { color.click(); } catch(e){} });
        }
        if (txt && sw) {
          txt.addEventListener('input', () => { sw.style.background = txt.value; });
        }
      });
    });
    </script>
  </body>
  </html>
