with-file-provider plugin
========================

Propósito
--------
Este plugin (plugins/with-file-provider.js) añade un FileProvider al AndroidManifest y, por defecto, inyecta también el permiso
`android.permission.REQUEST_INSTALL_PACKAGES` necesario cuando la app descarga un APK y lanza el instalador del sistema.

Evitar permiso en builds para Play Store
--------------------------------------
Para que la app no incluya `REQUEST_INSTALL_PACKAGES` en los builds destinados a Google Play (recomendado), establece una variable
de entorno antes de la build. El plugin respeta cualquiera de estas dos variables:

- `SKIP_REQUEST_INSTALL_PACKAGES=true`
- `PLAY_STORE=1`

Ejemplos
--------
- En `eas.json` (ya configurado en este repo): agrega la variable en el profile de `production`.
- Build local (macOS / Linux): `SKIP_REQUEST_INSTALL_PACKAGES=true eas build --platform android --profile production`
- Build local (Windows PowerShell): `$env:SKIP_REQUEST_INSTALL_PACKAGES = 'true'; eas build --platform android --profile production`

Comportamiento
--------------
- Si la variable está presente: el plugin sigue añadiendo el `FileProvider` necesario para compartir el APK vía `content://` URI,
  pero NO añadirá la entrada `<uses-permission android:name="android.permission.REQUEST_INSTALL_PACKAGES"/>`.
- Si la variable NO está presente: el plugin añadirá también el permiso. Útil para builds fuera de Play donde realmente necesites
  lanzar el instalador desde la app.

Notas
-----
- Google Play recomienda evitar instalaciones fuera del canal de Play. Si puedes usar In-App Updates o `expo-updates`, es preferible.
- Tras generar el prebuild, revisa `android/app/src/main/AndroidManifest.xml` para confirmar la presencia/ausencia del permiso.
