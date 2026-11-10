# Resumen del Proyecto — Bragado TV

Este documento resume, en lenguaje sencillo, todos los cambios y tareas que realizamos en la app y en el panel de administración (admin) hasta la fecha. Está pensado como guía para operaciones, despliegue y para entregar al equipo de QA/producción.

---

## 1) Objetivo

- Recuperar compatibilidad con Android TV / Google TV.
- Añadir soporte para notificaciones push (Expo / FCM) en Android, con panel de administración para enviar notificaciones.
- Añadir una interfaz de administración web (PHP) para editar el `config.txt` remoto y enviar notificaciones.
- Mejorar la experiencia en móviles y televisores (UI responsiva, safe-area, scroll en landscape).

---

## 2) Cambios principales en la app (cliente)

1. Safe area & layout
   - `HomeScreen`: ahora envuelto en `SafeAreaProvider` y usa `ScrollView` para evitar que controles se oculten en landscape o por la barra del sistema.
   - `SettingsScreen`: convertido a `ScrollView`, respeta `useSafeAreaInsets` para que el título no quede por debajo de la status bar.

2. Notificaciones (expo-notifications)
   - Instaladas dependencias: `expo-notifications`, `expo-device`.
   - Código para solicitar permisos (Android 13+ `POST_NOTIFICATIONS`) y registrar el token expo push.
   - Manejo de handlers:
     - `addNotificationReceivedListener` (foreground) — ahora no muestra alertes intrusivas; simplemente registra y loguea.
     - `addNotificationResponseReceivedListener` (tap) — abre `data.url` en navegador para `portal` o navega al `Player` para `programacion` (usa `setShowHome(false)` y actualiza `streamUrl` si viene `data.stream`).
   - La app intenta registrar el token en el servidor automáticamente cuando el usuario activa notificaciones (switches) y guarda preferencias en `AsyncStorage`.

3. Config remota
   - `services/remoteConfig.js` ahora apunta a `https://tvbragado.com.ar/admin/config.txt` y parsea:
     - línea1 = logo URL
     - línea2 = stream URL
     - siguientes líneas con formato `clave: valor` (fondo, botonfondo, colorletras).

4. Endpoints y configuración cliente
   - `app/services/notifications.js` usa `https://tvbragado.com.ar/admin/register_token.php` para registrar tokens.
   - `services/remoteConfig.js` carga config desde `/admin/config.txt`.

5. Plugins nativos para TV
   - `plugins/fix-tv-manifest.js`: plugin de config-plugins que parchea el AndroidManifest durante el build para:
     - asegurar `uses-feature` importantes con `android:required="false"` (touchscreen, portrait, telephony, camera, gps).
     - añadir `LEANBACK_LAUNCHER` intent al activity principal para aparecer en Android TV launcher.
   - El plugin está registrado en `app.config.js`.

6. Otros
   - Ajustes de UI: botones, accesibilidad, scroll, safe area, y extracción de diagnostic tools cuando ya no son necesarias.

---

## 3) Cambios en el backend / admin (PHP)

1. Estructura y autenticación
   - `admin/index.php`: login simple por sesión (15 minutos). Ahora la gestión de acceso al panel requiere iniciar sesión.
   - `ADMIN_PASSWORD` recomendado como variable de entorno en el servidor (no en el código).

2. Formularios
   - `admin/edit_config.php`: formulario responsivo para editar `config.txt` en `/admin/config.txt` — muestra el logo (línea 1) en preview y permite cambiar colores con swatches integrados.
   - `admin/send_notification.php`: formulario responsivo para enviar notificaciones con:
     - selector `type`: `portal` o `programacion`.
     - selector `priority`: `default` o `high`.
     - validación cliente y servidor: si `portal` => URL obligatoria y validada.
     - envío en chunks de 100, reintentos, consulta de recibos y eliminación de tokens `DeviceNotRegistered`.

3. Tokens
   - `admin/register_token.php` registra tokens en `admin/tokens.json`.
   - Se movió `tokens.json` a `admin/`.

4. Logging y rotación
   - `admin/push_log.txt` (se crea en `admin/`) guarda un JSON por línea con resumen de cada envío (ts, tipo, priority, counts, tickets y respuestas resumidas).
   - Rotación automática: si `push_log.txt` supera 5 MB se renombra y se comprime a `push_log_YYYYMMDD_HHMMSS.txt.gz`.

5. Seguridad
   - No guardar credenciales en el repo. `admin/*.p8`, `admin/tokens.json` y otros archivos sensibles están en `.gitignore`.
   - Se sugiere proteger `/admin` con HTTP basic o similar en producción y usar una contraseña fuerte en la variable `ADMIN_PASSWORD`.

---

## 4) Integración con Expo / EAS (builds)

1. Google / FCM
   - `google-services.json` fue añadido a EAS como variable tipo file (se debe subir a Expo/EAS en la UI o por `eas env:set` con `--value @./google-services.json`).
   - Añadimos la FCM Server Key en el panel de Expo para que Expo Push API pueda entregar a FCM.

2. Apple / iOS
   - APNs `.p8` (Key ID, Team ID) debe subirse a EAS/Expo cuando se construya iOS. El panel PHP funciona igual para enviar notificaciones (gestiona tokens y payloads), solo que en iOS la entrega final depende de APNs.

3. Perfiles EAS
   - `eas.json` contiene perfiles: `development` (dev-client), `preview` (apk de prueba), `production` y `production_tv` (extiende production con `EXPO_TV=1`).

4. Plugins nativos
   - `plugins/fix-tv-manifest.js` se registra en `app.config.js` para corregir el manifest durante build y permitir compatibilidad TV.

---

## 5) Cómo generar builds (resumen de comandos)

- Instalar EAS CLI y loguear:
  - `npm i -g eas-cli`
  - `eas login`
- Para dev client (fast dev builds):
  - `expo install expo-dev-client`
  - `eas build --platform android --profile development`
- Para APK de prueba (preview):
  - `eas build --platform android --profile preview`
- Para producción (incluye TV):
  - `eas build --platform android --profile production_tv`

Notas: Asegurate de haber subido `google-services.json` (file env) y la APNs `.p8` en la consola Expo antes de hacer builds que requieren notificaciones.

---

## 6) Qué queda por hacer / recomendaciones

1. iOS: integrar APNs .p8 en EAS y probar en dispositivo físico (simulador no recibe push remotas).
2. Auditoría: migrar `tokens.json` a una base de datos (Postgres) cuando el número de usuarios crezca.
3. Seguridad: proteger /admin con autenticación HTTP y cambiar `ADMIN_PASSWORD` por variable fuerte.
4. QA: pruebas en varios modelos TV (Android TV, Google TV) y móviles para validar layout y notificaciones.

---

## 7) Cómo generar un PDF con este documento

Si querés un PDF, desde tu máquina ejecuta:

1) Convertir markdown a PDF usando pandoc (si lo tienes):
   - `pandoc docs/Resumen-Proyecto-Bragado-TV.md -o Resumen-Proyecto-Bragado-TV.pdf`

2) O abrir el .md en el navegador o VSCode y usar "Print to PDF".

---

Si querés que genere el PDF aquí y lo suba al repo, puedo crear el archivo Markdown en `docs/` (ya lo hice) y luego indicarte el comando. No puedo incluir los ficheros sensibles (.p8, google-services.json) dentro del PDF por razones de seguridad — pero en el documento indico cómo y dónde están almacenados y cómo subirlos a EAS.

Si querés, ahora genero el PDF en tu máquina (te doy el comando) o lo genero localmente y te doy instrucciones para descargarlo. ¿Qué preferís?
