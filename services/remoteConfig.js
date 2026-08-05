export async function getConfig() {
  // Expected config format (plain text):
  // line 1: logo URL (or empty)
  // line 2: stream URL (or empty)
  // subsequent lines (key: value) examples:
  // fondo: #000000
  // botonfondo: #222222
  // colorletras: #ffffff
    const url = 'https://tvbragado.com.ar/admin/config.txt';
  try {
    const res = await fetch(url, { cache: 'no-store' });
    const text = await res.text();
    const lines = text.split(/\r?\n/).map((l) => l.trim());

    const logo = lines[0] || null;
    const stream = lines[1] || null;

    const values = {};
    for (let i = 2; i < lines.length; i++) {
      const line = lines[i];
      if (!line) continue;
      const parts = line.split(':');
      if (parts.length < 2) continue;
      const key = parts[0].trim().toLowerCase();
      const val = parts.slice(1).join(':').trim();
      values[key] = val;
    }

    return {
      logo,
      stream,
      fondo: values.fondo || null,
      botonfondo: values.botonfondo || null,
      colorletras: values.colorletras || null,
      slogan: values.slogan || null,
      boton_border: values.boton_border || null,
      boton_border_width: values.boton_border_width || null,
    };
  } catch (e) {
    console.log('remoteConfig: failed to fetch', e);
    return { logo: null, stream: null, fondo: null, botonfondo: null, colorletras: null };
  }
}
