<?php
// Simple config editor for tvbragado/config.txt
// WARNING: This is intentionally minimal. Place it in the same directory as config.txt
// and set a safer password before using. This script overwrites config.txt.

$PASSWORD = 'sergio-btv'; // CHANGE THIS before uploading
$CONFIG_FILE = __DIR__ . '/config.txt';

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
    foreach (['fondo','botonfondo','colorletras'] as $k) {
        if (isset($extras[$k])) $lines[] = "$k: " . trim($extras[$k]);
    }
    return file_put_contents($path, implode("\n", $lines) . "\n") !== false;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwd = $_POST['pwd'] ?? '';
    if ($pwd !== $PASSWORD) {
        $message = 'Clave incorrecta';
    } else {
        $logo = $_POST['logo'] ?? '';
        $stream = $_POST['stream'] ?? '';
        $fondo = $_POST['fondo'] ?? '#000000';
        $botonfondo = $_POST['botonfondo'] ?? 'rgba(255,255,255,0.08)';
        $colorletras = $_POST['colorletras'] ?? '#ffffff';
        $ok = write_config($CONFIG_FILE, $logo, $stream, ['fondo'=>$fondo,'botonfondo'=>$botonfondo,'colorletras'=>$colorletras]);
        $message = $ok ? 'Guardado correctamente' : 'Error al guardar';
    }
}

list($logo,$stream,$extras) = read_config($CONFIG_FILE);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Edit config.txt</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;background:#f5f5f5}label{display:block;margin-top:10px}input[type=text]{width:100%;padding:8px}input[type=submit]{margin-top:14px;padding:10px 16px}</style>
</head>
<body>
  <h1>Edit config.txt</h1>
  <?php if($message):?><p><strong><?php echo htmlspecialchars($message); ?></strong></p><?php endif; ?>
  <form method="post">
    <label>Clave (cambiar en el archivo PHP antes de publicar):
      <input type="password" name="pwd" />
    </label>
    <label>Logo URL:
      <input type="text" name="logo" value="<?php echo htmlspecialchars($logo); ?>" />
    </label>
    <label>Stream URL (HLS .m3u8):
      <input type="text" name="stream" value="<?php echo htmlspecialchars($stream); ?>" />
    </label>
    <label>Fondo (seleccioná color):
      <?php $f_hex = extract_hex($extras['fondo'] ?? '#000000') ?? '#000000'; ?>
      <input type="color" id="fondo_color" value="<?php echo htmlspecialchars($f_hex); ?>" />
      <input type="text" id="fondo_text" name="fondo" value="<?php echo htmlspecialchars($extras['fondo'] ?? '#000000'); ?>" />
      <span id="fondo_sw" style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;margin-left:8px;background:<?php echo htmlspecialchars($extras['fondo'] ?? '#000000'); ?>"></span>
    </label>
    <label>Botón fondo (color + opacidad):
      <?php list($btn_hex,$btn_alpha) = parse_rgba($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>
      <input type="color" id="boton_color" value="<?php echo htmlspecialchars($btn_hex); ?>" />
      <input type="range" id="boton_alpha" min="0" max="1" step="0.01" value="<?php echo htmlspecialchars($btn_alpha); ?>" />
      <input type="text" id="boton_text" name="botonfondo" value="<?php echo htmlspecialchars($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>" />
      <span id="boton_sw" style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;margin-left:8px;background:<?php echo htmlspecialchars($extras['botonfondo'] ?? 'rgba(255,255,255,0.08)'); ?>"></span>
    </label>
    <label>Color letras (seleccioná color):
      <?php $t_hex = extract_hex($extras['colorletras'] ?? '#ffffff') ?? '#ffffff'; ?>
      <input type="color" id="texto_color" value="<?php echo htmlspecialchars($t_hex); ?>" />
      <input type="text" id="texto_text" name="colorletras" value="<?php echo htmlspecialchars($extras['colorletras'] ?? '#ffffff'); ?>" />
      <span id="texto_sw" style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;margin-left:8px;background:<?php echo htmlspecialchars($extras['colorletras'] ?? '#ffffff'); ?>"></span>
    </label>
    <input type="submit" value="Guardar" />
  </form>
  <p>Al subir este archivo a la raíz donde está config.txt, el formulario permitirá editar y sobrescribir config.txt.</p>
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
    });
  </script>
</body>
</html>
