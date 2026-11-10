# Guía de Implementación - Bragado TV

Guía completa para replicar todas las funcionalidades desarrolladas en Bragado TV.

---

## 1. Botones de Redes Sociales

### HomeScreen.js

Reemplazar el botón "LLAMAR" por botones de redes sociales que se configuren desde el admin.

**Imports necesarios:**
```js
import { View, Text, Image, Pressable, StyleSheet, Linking, Modal, ScrollView, Animated, Easing, Platform, BackHandler } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
```

**Props del componente:**
```js
export default function HomeScreen({
  onStart,
  logoUrl,
  bgColor = '#000',
  buttonBg = 'rgba(255,255,255,0.08)',
  textColor = '#fff',
  slogan = 'Tu slogan aquí',
  webUrl = 'https://tusitio.com',
  whatsapp,
  facebook,
  instagram,
  twitter,
  tiktok,
  youtube,
  mail,
  buttonFocusBorder = '#fff',
  buttonFocusWidth = 3
})
```

**Lógica de renderizado condicional:**
```jsx
{Platform.isTV ? (
  // TV: solo botón CERRAR
  <FocusableButton label="CERRAR" onPress={() => BackHandler.exitApp()} ... />
) : (
  // Celular: botones de redes sociales
  <View style={styles.socialRow}>
    {whatsapp ? <SocialIcon icon={require('../assets/social-whatsapp.webp')} url={`https://wa.me/${whatsapp}`} bg={buttonBg} /> : null}
    {facebook ? <SocialIcon icon={require('../assets/social-facebook.webp')} url={facebook} bg={buttonBg} /> : null}
    {instagram ? <SocialIcon icon={require('../assets/social-instagram.webp')} url={instagram} bg={buttonBg} /> : null}
    {twitter ? <SocialIcon icon={require('../assets/social-twitter.webp')} url={twitter} bg={buttonBg} /> : null}
    {tiktok ? <SocialIcon icon={require('../assets/social-tiktok.webp')} url={tiktok} bg={buttonBg} /> : null}
    {youtube ? <SocialIcon icon={require('../assets/social-youtube.webp')} url={youtube} bg={buttonBg} /> : null}
    {mail ? <SocialIcon icon={require('../assets/social-mail.webp')} url={`mailto:${mail}`} bg={buttonBg} /> : null}
  </View>
)}
```

**Estilos de redes sociales:**
```js
socialRow: {
  flexDirection: 'row',
  flexWrap: 'wrap',
  justifyContent: 'center',
  gap: 12,
  marginTop: 20,
},
socialBtn: {
  margin: 4,
},
socialIcon: {
  width: 52,
  height: 52,
  borderRadius: 16,
  alignItems: 'center',
  justifyContent: 'center',
},
socialLogo: {
  width: 28,
  height: 28,
},
```

**Componente SocialIcon:**
```js
function SocialIcon({ icon, url, bg }) {
  const [focused, setFocused] = useState(false);
  const anim = useRef(new Animated.Value(0)).current;
  const onFocus = () => { setFocused(true); Animated.timing(anim, { toValue: 1, duration: 180, easing: Easing.out(Easing.ease), useNativeDriver: true }).start(); };
  const onBlur = () => { setFocused(false); Animated.timing(anim, { toValue: 0, duration: 120, easing: Easing.in(Easing.ease), useNativeDriver: true }).start(); };
  return (
    <Pressable focusable={true} onFocus={onFocus} onBlur={onBlur} onPress={() => Linking.openURL(url)} style={styles.socialBtn}>
      <Animated.View style={[styles.socialIcon, { backgroundColor: bg }, { transform: [{ scale: anim.interpolate({ inputRange: [0,1], outputRange: [1, 1.1] }) }] }, focused ? { borderWidth: 3, borderColor: '#fff' } : null]}>
        <Image source={icon} style={styles.socialLogo} resizeMode="contain" />
      </Animated.View>
    </Pressable>
  );
}
```

### Admin (config.txt)

Agregar estas líneas al `config.txt` del admin:
```
whatsapp: 5492342447086
facebook: https://www.facebook.com/TuPagina
instagram: https://www.instagram.com/TuUsuario
twitter: https://twitter.com/TuUsuario
tiktok: https://www.tiktok.com/@TuUsuario
youtube: https://www.youtube.com/@TuCanal
mail: tu@email.com
```

### Admin PHP (register_token.php / send_notification.php)

No requiere cambios adicionales, solo que el `config.txt` tenga los campos de redes sociales.

---

## 2. Botones Configurar, Calificar y Salir (solo celular)

**Botones superiores (posicionados absolute en la esquina superior derecha):**
```jsx
// Configurar (gear)
<Pressable style={[styles.topButton, { top: (insets.top || 10) + 4 }]}>
  <Animated.View style={[styles.topButtonInner, gearFocused && { borderColor: buttonFocusBorder, borderWidth: buttonFocusWidth }]}>
    <Text style={{color:textColor, fontSize:22}}>⚙️</Text>
  </Animated.View>
</Pressable>

// Calificar (star) - solo celular
{!Platform.isTV && (
  <Pressable style={[styles.topButton, { top: (insets.top || 10) + 60 }]}>
    <Animated.View style={[styles.topButtonInner, starFocused && { borderColor: buttonFocusBorder, borderWidth: buttonFocusWidth }]}>
      <Text style={{color:textColor, fontSize:22}}>⭐</Text>
    </Animated.View>
  </Pressable>
)}

// Salir (exit) - solo celular
{!Platform.isTV && (
  <Pressable style={[styles.topButton, { top: (insets.top || 10) + 116 }]}>
    <Animated.View style={[styles.topButtonInner, exitFocused && { borderColor: buttonFocusBorder, borderWidth: buttonFocusWidth }]}>
      <Text style={{color:textColor, fontSize:22}}>🚪</Text>
    </Animated.View>
  </Pressable>
)}
```

**Estilos:**
```js
topButton: {
  position: 'absolute',
  right: 16,
  zIndex: 10,
},
topButtonInner: {
  backgroundColor: 'rgba(0,0,0,0.25)',
  borderRadius: 14,
  paddingVertical: 12,
  paddingHorizontal: 14,
  alignItems: 'center',
  justifyContent: 'center',
  borderWidth: 2,
  borderColor: 'transparent',
},
```

---

## 3. Gradiente Moderno con Colores del Config Remoto

**Instalar:**
```bash
npx expo install expo-linear-gradient
```

**Funciones auxiliares para color:**
```js
function darkenColor(hex, factor = 0.7) {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgb(${Math.round(r * factor)}, ${Math.round(g * factor)}, ${Math.round(b * factor)})`;
}

function lightenColor(hex, factor = 1.3) {
  const r = Math.min(255, parseInt(hex.slice(1, 3), 16) * factor);
  const g = Math.min(255, parseInt(hex.slice(3, 5), 16) * factor);
  const b = Math.min(255, parseInt(hex.slice(5, 7), 16) * factor);
  return `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
}

function isValidHex(color) {
  return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(color);
}
```

**Uso en el componente:**
```js
const gradientColors = isValidHex(bgColor)
  ? [darkenColor(bgColor, 0.6), bgColor, lightenColor(bgColor, 1.1)]
  : [bgColor, bgColor, bgColor];
```

**Container con gradiente:**
```jsx
<LinearGradient colors={gradientColors} style={[styles.container, { paddingTop: (insets.top || 12) + 40 }]}>
  {/* contenido */}
</LinearGradient>
```

---

## 4. Botones Redondeados y Más Grandes

**Estilos de botones principales:**
```js
button: {
  backgroundColor: 'rgba(255,255,255,0.08)',
  paddingVertical: 16,
  paddingHorizontal: 32,
  borderRadius: 16,
  minWidth: 240,
  alignItems: 'center',
  marginVertical: 8,
},
buttonText: {
  color: '#fff',
  fontWeight: '700',
  fontSize: 16,
  letterSpacing: 1,
},
```

---

## 5. Notificaciones Push con expo-notifications

### Instalación
```bash
npx expo install expo-notifications
```

### Configuración en app.config.js
```js
plugins: [
  [
    "expo-notifications",
    {
      icon: "./assets/notification-icon.png",
      color: "#FFFFFF"
    }
  ]
],
```

### Icono de notificación
Crear `assets/notification-icon.png`:
- PNG monocromático (blanco sobre transparente)
- Tamaño: 96x96px o 192x192px
- Sin colores, sin fondo

### Servicio de notificaciones (services/notifications.js)
```js
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';

const SERVER_REGISTER_URL = 'https://tuserver.com/app/register_token.php';

export async function getExpoPushTokenAsync() {
  if (!Device.isDevice) return null;
  const { status: existingStatus } = await Notifications.getPermissionsAsync();
  let finalStatus = existingStatus;
  if (existingStatus !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync();
    finalStatus = status;
  }
  if (finalStatus !== 'granted') return null;
  const tokenData = await Notifications.getExpoPushTokenAsync({
    projectId: 'TU_PROJECT_ID'
  });
  return tokenData.data;
}

export async function registerTokenOnServer(token, deviceId, portal = true, programacion = true) {
  try {
    await fetch(SERVER_REGISTER_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, deviceId, portal, programacion, action: 'register' })
    });
    return true;
  } catch (e) {
    console.log('registerTokenOnServer error', e);
    return false;
  }
}

export async function unregisterTokenOnServer(token, deviceId) {
  try {
    await fetch(SERVER_REGISTER_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, deviceId, action: 'unregister' })
    });
    return true;
  } catch (e) {
    console.log('unregisterTokenOnServer error', e);
    return false;
  }
}
```

### Pedir permiso al iniciar (HomeScreen.js)
```js
useEffect(() => {
  if (!Platform.isTV) {
    getExpoPushTokenAsync();
  }
}, []);
```

### Handler de notificaciones en App.js
```js
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});
```

---

## 6. Actualización APK Directa desde la App

### Instalación
```bash
npx expo install expo-file-system expo-intent-launcher expo-sharing
```

### Verificación de versión (services/checkUpdate.js)
```js
import * as Application from 'expo-application';
import { Platform } from 'react-native';
import Constants from 'expo-constants';

const VERSION_URL = 'https://tuserver.com/app/version.json';

function parseVersion(v) {
  return v.split('.').map(Number);
}

function isNewer(remote, current) {
  const r = parseVersion(remote);
  const c = parseVersion(current);
  for (let i = 0; i < Math.max(r.length, c.length); i++) {
    const a = r[i] || 0;
    const b = c[i] || 0;
    if (a > b) return true;
    if (a < b) return false;
  }
  return false;
}

export async function checkForApkUpdate() {
  if (Platform.OS !== 'android') return null;
  try {
    const res = await fetch(VERSION_URL, { cache: 'no-store' });
    const remote = await res.json();
    const currentVersion = Constants.expoConfig?.version || Application.nativeApplicationVersion || '1.0.0';
    if (remote.version && isNewer(remote.version, currentVersion)) {
      return {
        version: remote.version,
        apkUrl: remote.apkUrl,
        changelog: remote.changelog || '',
      };
    }
  } catch (e) {
    console.log('APK update check failed:', e);
  }
  return null;
}
```

### version.json en el servidor
```json
{
  "version": "1.0.3",
  "apkUrl": "https://tuserver.com/app/app-release.apk",
  "changelog": "Descripción de cambios"
}
```

### Plugin FileProvider (plugins/with-file-provider.js)
```js
const { withAndroidManifest } = require('@expo/config-plugins');

module.exports = function withFileProvider(config) {
  return withAndroidManifest(config, (cfg) => {
    const manifest = cfg.modResults.manifest;

    if (!manifest['uses-permission']) {
      manifest['uses-permission'] = [];
    }
    const hasInstallPerm = manifest['uses-permission'].some(
      (p) => p.$?.['android:name'] === 'android.permission.REQUEST_INSTALL_PACKAGES'
    );
    if (!hasInstallPerm) {
      manifest['uses-permission'].push({
        $: { 'android:name': 'android.permission.REQUEST_INSTALL_PACKAGES' },
      });
    }

    if (!manifest.application) {
      manifest.application = [{}];
    }
    const app = manifest.application[0];
    if (!app['provider']) {
      app['provider'] = [];
    }
    const exists = app['provider'].some(
      (p) => p.$?.['android:name'] === 'androidx.core.content.FileProvider'
    );
    if (!exists) {
      app['provider'].push({
        $: {
          'android:name': 'androidx.core.content.FileProvider',
          'android:authorities': `${cfg.modResults.manifest.$?.package}.fileprovider`,
          'android:exported': 'false',
          'android:grantUriPermissions': 'true',
        },
        'meta-data': [
          {
            $: {
              'android:name': 'android.support.FILE_PROVIDER_PATHS',
              'android:resource': '@xml/file_provider_paths',
            },
          },
        ],
      });
    }
    return cfg;
  });
};
```

### XML FileProvider (android/app/src/main/res/xml/file_provider_paths.xml)
```xml
<?xml version="1.0" encoding="utf-8"?>
<paths>
    <cache-path name="cache" path="." />
    <files-path name="files" path="." />
</paths>
```

### Modal de actualización (components/UpdateModal.js)
```js
import React, { useState } from 'react';
import { View, Text, Modal, Pressable, StyleSheet, ActivityIndicator } from 'react-native';
import * as FileSystem from 'expo-file-system/legacy';
import * as IntentLauncher from 'expo-intent-launcher';

export default function UpdateModal({ visible, version, apkUrl, changelog, onClose }) {
  const [downloading, setDownloading] = useState(false);
  const [progress, setProgress] = useState(0);

  async function handleUpdate() {
    if (!apkUrl) return;
    setDownloading(true);
    setProgress(0);
    try {
      const fileUri = FileSystem.cacheDirectory + 'app-update.apk';
      const downloadResumable = FileSystem.createDownloadResumable(
        apkUrl,
        fileUri,
        {},
        (downloadProgress) => {
          const p = downloadProgress.totalBytesWritten / downloadProgress.totalBytesExpectedToWrite;
          setProgress(p);
        }
      );
      const { uri } = await downloadResumable.downloadAsync();

      const fileInfo = await FileSystem.getInfoAsync(uri);
      if (!fileInfo.exists || fileInfo.size < 1000000) {
        setProgress(-1);
        return;
      }

      const contentUri = await FileSystem.getContentUriAsync(uri);

      await IntentLauncher.startActivityAsync('android.intent.action.VIEW', {
        data: contentUri,
        flags: 0x10000003,
        type: 'application/vnd.android.package-archive',
      });
    } catch (e) {
      console.log('Update error:', e?.message || e);
    } finally {
      setTimeout(() => {
        setDownloading(false);
        setProgress(0);
      }, 1500);
    }
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={!downloading ? onClose : undefined}>
      <View style={styles.overlay}>
        <View style={styles.modal}>
          {downloading ? (
            <>
              <Text style={styles.title}>Actualizando...</Text>
              <ActivityIndicator size="large" color="#4a90d9" style={{ marginVertical: 16 }} />
              <Text style={styles.version}>{Math.round(progress * 100)}%</Text>
            </>
          ) : (
            <>
              <Text style={styles.title}>Nueva versión disponible</Text>
              <Text style={styles.version}>v{version}</Text>
              {changelog ? <Text style={styles.changelog}>{changelog}</Text> : null}
              <View style={styles.buttons}>
                <Pressable style={[styles.btn, styles.btnUpdate]} onPress={handleUpdate}>
                  <Text style={styles.btnText}>Actualizar</Text>
                </Pressable>
                <Pressable style={[styles.btn, styles.btnLater]} onPress={onClose}>
                  <Text style={[styles.btnText, { color: '#ccc' }]}>Ahora no</Text>
                </Pressable>
              </View>
            </>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modal: {
    backgroundColor: '#1a1a1a',
    borderRadius: 16,
    padding: 28,
    width: '85%',
    maxWidth: 380,
    alignItems: 'center',
  },
  title: {
    color: '#fff',
    fontSize: 20,
    fontWeight: '700',
    marginBottom: 8,
  },
  version: {
    color: '#aaa',
    fontSize: 14,
    marginBottom: 12,
  },
  changelog: {
    color: '#ddd',
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 20,
    lineHeight: 20,
  },
  buttons: {
    flexDirection: 'row',
    gap: 12,
  },
  btn: {
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
    minWidth: 110,
    alignItems: 'center',
  },
  btnUpdate: {
    backgroundColor: '#4a90d9',
  },
  btnLater: {
    backgroundColor: 'rgba(255,255,255,0.08)',
  },
  btnText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
```

### Llamada en App.js
```js
import { checkForApkUpdate } from './services/checkUpdate';
import UpdateModal from './components/UpdateModal';

// En el state:
const [apkUpdate, setApkUpdate] = useState(null);

// En useEffect:
checkForApkUpdate().then((update) => {
  if (update) setApkUpdate(update);
});

// En el JSX:
<UpdateModal
  visible={!!apkUpdate}
  version={apkUpdate?.version}
  apkUrl={apkUpdate?.apkUrl}
  changelog={apkUpdate?.changelog}
  onClose={() => setApkUpdate(null)}
/>
```

---

## 7. OTA Updates

### Configuración en app.config.js
```js
updates: {
  enabled: true,
  checkAutomatically: "ON_LAUNCH"
},
runtimeVersion: "1.0.0",
```

### Configuración en eas.json
```json
{
  "build": {
    "production": {
      "autoIncrement": true,
      "environment": "production",
      "channel": "production"
    }
  }
}
```

### Comandos
```bash
# Build para Play Store
eas build --platform android --profile production

# Publicar actualización OTA
eas update --channel production --message "Descripción del cambio"
```

---

## 8. Permisos Android

### AndroidManifest.xml (via config plugin)
```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.VIBRATE"/>
<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES"/>
```

---

## 9. Scripts de Build

### clean-build.bat
```bat
@echo off
cd /d "%~dp0"
echo === Clean + Build APK ===
echo.
echo [1/4] Limpiando build...
echo y | npx expo prebuild --non-interactive --clean
echo.
echo [2/4] Configurando Android...
call .\setup-android.bat
echo.
echo [3/4] Buildando APK...
echo 1. Debug
echo 2. Release
echo.
set /p choice="Elegí una opción (1/2): "
if "%choice%"=="1" (
    echo Buildando Debug...
    cd android
    .\gradlew --stop
    .\gradlew assembleDebug --no-parallel
) else if "%choice%"=="2" (
    echo Buildando Release...
    cd android
    .\gradlew --stop
    .\gradlew assembleRelease --no-parallel
) else (
    echo Opción inválida
    pause
    exit /b 1
)
echo [4/4] ¡Listo!
pause
```

### setup-android.bat
```bat
@echo off
cd /d "%~dp0"
echo Configurando Android...
powershell -Command "$c = Get-Content 'android\gradle.properties' -Raw; $nl = [char]13 + [char]10; $c = $c -replace 'org\.gradle\.jvmargs=-Xmx2048m -XX:MaxMetaspaceSize=512m', ('org.gradle.jvmargs=-Xmx2048m -XX:MaxMetaspaceSize=512m -XX:+UseG1GC' + $nl + 'org.gradle.workers.max=2' + $nl + 'android.experimental.runInProcess=true'); Set-Content 'android\gradle.properties' $c -NoNewline"
echo sdk.dir=C:/Users/TU_USUARIO/AppData/Local/Android/Sdk>android\local.properties
echo Listo.
```

---

## 10. Estructura de Archivos

```
proyecto/
├── app/
│   └── services/
│       ├── notifications.js
│       └── remoteConfig.js
├── assets/
│   ├── notification-icon.png
│   ├── social-whatsapp.webp
│   ├── social-facebook.webp
│   ├── social-instagram.webp
│   ├── social-twitter.webp
│   ├── social-tiktok.webp
│   ├── social-youtube.webp
│   └── social-mail.webp
├── components/
│   └── UpdateModal.js
├── plugins/
│   └── with-file-provider.js
├── screens/
│   └── HomeScreen.js
├── android/
│   └── app/src/main/res/xml/
│       └── file_provider_paths.xml
├── app.config.js
├── eas.json
├── clean-build.bat
├── setup-android.bat
└── package.json
```

---

## 11. Checklist de Implementación

- [ ] Instalar dependencias: `expo-notifications`, `expo-linear-gradient`, `expo-file-system`, `expo-intent-launcher`, `expo-sharing`
- [ ] Crear `assets/notification-icon.png` (monocromático)
- [ ] Crear `assets/social-*.webp` (iconos de redes)
- [ ] Crear `plugins/with-file-provider.js`
- [ ] Crear `android/app/src/main/res/xml/file_provider_paths.xml`
- [ ] Configurar `app.config.js` con plugins, updates y runtimeVersion
- [ ] Configurar `eas.json` con channel de production
- [ ] Implementar `HomeScreen.js` con gradiente, botones condicionales
- [ ] Implementar `UpdateModal.js` con descarga de APK
- [ ] Implementar `services/checkUpdate.js`
- [ ] Implementar `services/notifications.js`
- [ ] Configurar `config.txt` en admin con redes sociales
- [ ] Probar build con `clean-build.bat`
- [ ] Subir a Play Store con `eas build`
