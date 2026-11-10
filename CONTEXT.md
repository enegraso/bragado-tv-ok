# CONTEXT.md - Documentación Técnica Bragado TV OK

## 1. Resumen de Arquitectura

**Bragado TV OK** es una aplicación de streaming en vivo construida con **JavaScript puro** sobre el framework **React Native** utilizando **Expo SDK 54**. La app está diseñada para funcionar tanto en **Android TV** como en **Android móvil/iOS**.

### Stack Tecnológico
- **Runtime**: JavaScript (sin TypeScript)
- **Framework**: React Native 0.81.5 + React 19.1.0
- **Build System**: Expo SDK 54 + EAS Build
- **Video Player**: expo-video (con fallback a expo-av para Expo Go)
- **Almacenamiento local**: AsyncStorage
- **Notificaciones**: expo-notifications
- **Actualizaciones OTA**: expo-updates
- **Configuración remota**: Fetch desde archivo plano en servidor

### Flujo Principal
1. La app inicia en `index.js` → carga `App.js`
2. `App.js` obtiene configuración remota desde `tvbragado.com.ar/admin/config.txt`
3. Muestra `HomeScreen` con opciones: Ver en Vivo, Web Oficial, Llamar
4. Al presionar "VER EN VIVO" → navega a `PlayerScreen`
5. `PlayerScreen` reproduce stream HLS vía expo-video/expo-av

---

## 2. Estructura de Carpetas

### `/app` - Módulos Nuevos (en desarrollo)
Contiene componentes y servicios que están siendo refactorizados desde la raíz.

```
/app/
├── screens/
│   └── SettingsScreen.js    # Pantalla de configuración de notificaciones
└── services/
    └── notifications.js     # Servicio de registro de tokens push
```

**`/app/screens/SettingsScreen.js`**
- Permite al usuario configurar preferencias de notificaciones (Portal/Programación)
- Gestiona permisos de notificaciones del sistema
- Registra/desregistra tokens Expo Push en servidor remoto
- Se muestra como Modal desde HomeScreen

**`/app/services/notifications.js`**
- `getExpoPushTokenAsync()`: Obtiene token Expo Push del dispositivo
- `registerTokenOnServer()`: Registra token en `tvbragado.com.ar/admin/register_token.php`
- `unregisterTokenOnServer()`: Elimina registro del token

### `/screens` - Pantallas Principales (producción actual)
Pantallas principales que la app carga directamente.

```
/screens/
├── HomeScreen.js            # Pantalla de inicio con menú principal
└── PlayerScreen.js          # Reproductor de video streaming
```

**`/screens/HomeScreen.js`**
- Muestra logo, slogan y botones de acción
- Botón "VER EN VIVO" → activa PlayerScreen
- Botón "WEB OFICIAL" → abre bragadotv.com.ar
- Botón "LLAMAR" → abre marcador telefónico
- Botón configuración (⚙️) → abre SettingsScreen como Modal
- Soporte completo para navegación con control remoto TV

**`/screens/PlayerScreen.js`**
- Reproduce stream HLS con expo-video (fallback a expo-av)
- Soporte nativo para Picture-in-Picture en Android
- Controles de pantalla completa automáticos
- Manejo de errores con opción de reintento
- Detección automática de orientación landscape/portrait
- Soporte para TV: toggle de overlay con botón Select/OK

### `/services` - Servicios Compartidos
Servicios utilizados por los componentes principales.

```
/services/
└── remoteConfig.js          # Configuración remota desde servidor
```

**`/services/remoteConfig.js`**
- `getConfig()`: Lee configuración desde `tvbragado.com.ar/admin/config.txt`
- Formato: Línea 1 = logo URL, Línea 2 = stream URL, siguientes líneas = key: value
- Parámetros soportados: fondo, botonfondo, colorletras, slogan, boton_border, boton_border_width

### `/admin` - Panel de Administración (servidor)
Scripts PHP para gestión remota de la app.

```
/admin/
├── config.txt               # Archivo de configuración remota (leído por la app)
├── edit_config.php          # Editor web de config.txt
├── index.php                # Panel de administración principal
├── register_token.php       # API para registro de tokens push
├── send_notification.php    # Envío de notificaciones push
├── upload_logo.php          # Upload de logo personalizado
├── tokens.json              # Almacén local de tokens registrados
├── README_DEPLOY.md         # Instrucciones de despliegue
└── README_notifications.md  # Documentación del sistema de notificaciones
```

### `/plugins` - Plugins Expo
Plugins personalizados para Expo Config.

```
/plugins/
├── fix-microphone.js        # Fix para permisos de micrófono
└── fix-tv-manifest.js       # Fix para manifest de Android TV
```

### `/assets` - Recursos Estáticos
Imágenes de splash, iconos y banners para TV.

---

## 3. Dependencias Actuales (package.json)

### Dependencias Principales
| Paquete | Versión |
|---------|---------|
| expo | ~54.0.28 |
| react | 19.1.0 |
| react-native | 0.81.5 |
| expo-video | ^3.0.15 |
| expo-av | ^16.0.8 |
| expo-notifications | ~0.32.17 |
| expo-updates | ~29.0.15 |
| expo-application | ^7.0.8 |
| expo-device | ~8.0.10 |
| expo-linking | ^8.0.10 |
| expo-status-bar | ~3.0.9 |
| @react-native-async-storage/async-storage | 2.2.0 |
| @react-native-tvos/config-tv | ^0.1.4 |
| react-native-safe-area-context | ^5.6.2 |
| expo-dev-client | ~6.0.21 |

### Dependencias de Desarrollo
| Paquete | Versión | Propósito |
|---------|---------|-----------|
| cross-env | ^10.1.0 | Variables de entorno cross-platform |

### Scripts Disponibles
```bash
npm start              # Expo start (dev server)
npm run android        # Expo run:android
npm run ios            # Expo run:ios
npm run web            # Expo start --web
npm run version:bump   # Incrementar versión automáticamente
npm run build:android  # Build producción Android TV (EAS)
npm run build:preview  # Build preview (EAS)
npm run build:produtv  # Build producción TV (EAS)
```

---

## 4. Credenciales y Archivos Sensibles en Raíz

**⚠️ IMPORTANTE: Estos archivos NO deben subirse a repositorios públicos**

### Apple Push Notifications
- **`AuthKey_H4HRLBXK8T.p8`** - Llave de autenticación Apple Push Notification Service (APNs)
- Usada para envío de notificaciones push a iOS

### Firebase / Google Services
- **`com-bragado-tvok-firebase-adminsdk-fbsvc-ec67eeb476.json`** - Credenciales Firebase Admin SDK
  - Usado para administración de Firebase (posiblemente para notificaciones server-side)

- **`google-services.json`** - Configuración de Google Services para Android
  - Requerido para builds nativos de Android
  - Configurado en `app.config.js` para ser incluido en el bundle

### Configuración de Build
- **`eas.json`** - Configuración de EAS Build (Expo Application Services)
- **`app.config.js`** - Configuración de Expo (incluye projectId de EAS: `e72fcaf9-08bb-4061-ad2e-b63685caad89`)

### Otros Archivos
- **`compilaciones.txt`** - Registro de compilaciones realizadas
- **`admin/tokens.json`** - Tokens de dispositivos registrados para notificaciones

---

## 5. Puntos Clave para Futuros Modelos

### Convenciones de Código
- **Sin TypeScript**: Todo el código usa JavaScript puro con JSX
- **Componentes funcionales**: Todos los componentes usan hooks (useState, useEffect, useRef)
- **Estilos**: StyleSheet.create() inline en cada archivo
- **Navegación**: Sin React Navigation - se usa estado `showHome` para alternar pantallas
- **Configuración remota**: Archivo plano TXT en servidor (no Firestore/Firebase Remote Config)

### Flujo de Datos
```
App.js (estado global)
  ├── streamUrl (string) - URL del stream HLS
  ├── logoUrl (string|null) - URL del logo remoto
  ├── bgColor, buttonBg, textColor, slogan - Estilos dinámicos
  └── showHome (boolean) - Control de navegación

services/remoteConfig.js
  └── getConfig() → fetch config.txt → retorna objeto de configuración

app/services/notifications.js
  ├── getExpoPushTokenAsync() → token Expo
  ├── registerTokenOnServer() → POST a register_token.php
  └── unregisterTokenOnServer() → POST a register_token.php
```

### Endpoints Remotos
- **Configuración**: `https://tvbragado.com.ar/admin/config.txt`
- **Tokens Push**: `https://tvbragado.com.ar/admin/register_token.php`
- **Stream HLS**: `https://srv1053170.hstgr.cloud/hls/bragadotv.m3u8` (default)

### Paquete de la App
- **Android**: `com.bragado.tvok`
- **EAS Project ID**: `e72fcaf9-08bb-4061-ad2e-b63685caad89`
- **Owner EAS**: `enegraso`

### Notas de Compatibilidad
- La app funciona en **Expo Go** (con fallback de expo-av)
- Builds nativos usan **expo-video** como reproductor principal
- Soporte completo para **Android TV** con navegación por control remoto
- Intento de **Picture-in-Picture** en Android móvil (best-effort)

---

## 6. Archivos Importantes en Raíz

| Archivo | Propósito |
|---------|-----------|
| `App.js` | Componente raíz - inicialización y navegación |
| `index.js` | Punto de entrada - registro de root component |
| `app.config.js` | Configuración de Expo (plugins, iconos, splash) |
| `package.json` | Dependencias y scripts |
| `eas.json` | Configuración de EAS Build profiles |
| `bump-version.js` | Script de incremento automático de versión |

---

*Documento generado automáticamente. Última actualización: Agosto 2026*
