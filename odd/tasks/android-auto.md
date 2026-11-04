# Android Auto (audio) — Bragado TV OK

**Objetivo:** hacer que Bragado TV aparezca en Android Auto como app de media (audio solo), reproduciendo el stream HLS en vivo sin video.
**Rama:** `feature/android-auto` · **Estrategia:** single-pr · **Test runner:** `gradlew assembleDebug` + Android Auto Desktop Head Unit (DHU).

## Specs

- S1: "adaptarla para que funcione en Android Auto, obviamente sin el video o un widget chiquito de esos que se muestran al lado del mapa, aunque sea con el audio solo" → app de media (audio) en Android Auto. El auto muestra solo la tarjeta estándar (ícono + título + play/pause). No hay video ni widget custom.
- S2: "con la url de stream, aunque sea de audio video, se puede tomar solo el audio?" → reproducir el stream HLS solo-audio, sin seleccionar/decodificar la pista de video.
- S3: "podremos generar esta apk para probar en mi celu y ver la compatibilidad con android auto, antes de subirla a play store?" → build local del APK y prueba con DHU (modo desarrollador + "Orígenes desconocidos" en Android Auto) antes de Play Store.
- S4: distribución en Play Console → opt-in del factor de forma Android Auto (Configuración avanzada → Factores de forma), testing track, revisión de calidad de autos (DD-2). La categoría del listing queda "Entretenimiento" por ahora.

## Tasks

- [x] T1 (S1,S2,S3): config plugin `plugins/with-android-auto.js` — inyecta deps Media3, entradas de manifest y copia archivos nativos en cada prebuild. [inline] — `f88ac03`
- [x] T2 (S1,S2): `BragadoTvMediaService.kt` — `MediaLibraryService` con ExoPlayer solo-audio (HLS), MediaSession y browse tree (raíz → "En vivo"). [inline] — `f88ac03`
- [x] T3 (S1,S4): `automotive_app_desc.xml` — declara la capacidad `media` para Android Auto. [inline] — `f88ac03`
- [x] T4 (S1): registrar `./plugins/with-android-auto` en `app.config.js`. [inline] — sin commitear (mezclado con cambios previos del usuario)
- [ ] T5 (S3,S4): build APK local + prueba DHU + opt-in de factor de forma en Play Console. [usuario]

## Log

- L1: "podes ver el funcionamiento de esta app y decirme de que manera podemos adaptarla para que funcione en Android Auto, obviamente sin el video o un widget chiquito de esos que se muestran al lado del mapa, aunque sea con el audio solo"
- L2 (2026-11-04): Confirmado camino = media app audio-only (Media3 MediaLibraryService). El video no existe en Android Auto; "video parked" es solo AAOS. La categoría Play actual es "Entretenimiento" (no se cambia por ahora). expo-video ya trae Media3 1.8.0 → alineo a esa versión.
