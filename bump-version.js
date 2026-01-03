const fs = require('fs');

const file = './app.config.js';
let content = fs.readFileSync(file, 'utf8');

console.log(`Comenzando a incrementar versión en ${file}...`);

// 🔢 Subir version visible (1.0.x)

let versionMatch = content.match(/"version":\s*"(\d+)\.(\d+)\.(\d+)"/);
if (versionMatch) {
  let major = Number(versionMatch[1]);
  let minor = Number(versionMatch[2]);
  let patch = Number(versionMatch[3]) + 1;
  let newVersion = `"version": "${major}.${minor}.${patch}"`;
  content = content.replace(/"version":\s*"\d+\.\d+\.\d+"/, newVersion);
}

// 🔢 Subir versionCode

/* try {
  content = content.replace(
    /versionCode:\s*(\d+)/,
    (match, v) => {
      return `versionCode: ${Number(v) + 1}`;
    }
  );
} catch (e) {
  console.error('Error al incrementar versionCode:', e);
  return  
} */

fs.writeFileSync(file, content);

console.log('✔ Versión incrementada correctamente');