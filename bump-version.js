const fs = require('fs');

const configFile = './app.config.js';
const buildGradleFile = './android/app/build.gradle';

console.log(`Incrementando versión...`);

// ── Leer app.config.js ──
let content = fs.readFileSync(configFile, 'utf8');

const versionMatch = content.match(/"version":\s*"(\d+)\.(\d+)\.(\d+)"/);
if (!versionMatch) {
  console.error('✖ No se encontró la versión en app.config.js');
  process.exit(1);
}

let major = Number(versionMatch[1]);
let minor = Number(versionMatch[2]);
let patch = Number(versionMatch[3]);

const oldVersion = `${major}.${minor}.${patch}`;

// ── Roll-over: patch 0-9 → carga al minor; minor 0-9 → carga al major ──
patch += 1;
if (patch > 9) {
  patch = 0;
  minor += 1;
  if (minor > 9) {
    minor = 0;
    major += 1;
  }
}

const newVersion = `${major}.${minor}.${patch}`;

// ── Actualizar app.config.js (verdad para prebuild / EAS) ──
content = content.replace(/"version":\s*"\d+\.\d+\.\d+"/, `"version": "${newVersion}"`);
fs.writeFileSync(configFile, content);

// ── Actualizar también el build.gradle generado, para que los builds locales
//    incrementales (sin prebuild) tomen la versión nueva ──
let gradleUpdated = false;
if (fs.existsSync(buildGradleFile)) {
  let gradle = fs.readFileSync(buildGradleFile, 'utf8');
  if (/versionName\s+"[\d.]+"/.test(gradle)) {
    gradle = gradle.replace(/versionName\s+"[\d.]+"/, `versionName "${newVersion}"`);
    fs.writeFileSync(buildGradleFile, gradle);
    gradleUpdated = true;
  }
}

console.log(`✔ ${oldVersion} → ${newVersion}`);
if (gradleUpdated) {
  console.log(`✔ También se actualizó android/app/build.gradle (versionName)`);
}
