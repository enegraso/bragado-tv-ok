# Android Auto Integration Spec

Reference for adding **Android Auto (audio-only media app)** support to an Expo (CNG) app that plays a live HLS stream. This documents a working implementation so it can be replicated on other apps.

- **Status:** Working (verified in a real car).
- **Platform:** Android Auto (phone projection). NOT Android Automotive OS (AAOS).
- **Approach:** Expo config plugin that injects native Kotlin (Media3) during `expo prebuild`.
- **No JS bridge needed:** the car connects directly to the native `MediaLibraryService`.

---

## 1. Architecture

Android Auto requires native OS components that JavaScript cannot register:

| Layer | Role |
|---|---|
| `MediaLibraryService` (Media3) | Native service the car discovers and controls |
| `MediaSession` | Play/pause, metadata, media notification |
| `MediaBrowser` browse tree | Root → items the user taps |
| Manifest `<meta-data>` | Declares the app as an Android Auto media app |
| `automotive_app_desc.xml` | Declares the `media` capability |
| Expo config plugin | Injects all of the above during `expo prebuild` |

The JS app itself is unchanged — the service is autonomous.

---

## 2. Files to add

> ⚠️ **Naming:** the code below uses Bragado TV's real identifiers as a *verified working example*. **Do not ship another app with `BragadoTv*` names.** You must rename the class, file, callback, manifest service name, plugin copy paths, and package — see the **Rename checklist (§3.1)**.

### 2.1 `plugins/with-android-auto.js` (config plugin)

Injects dependencies, manifest entries, and copies the native files.

```js
const { withAndroidManifest, withAppBuildGradle, withDangerousMod } = require('@expo/config-plugins');
const fs = require('fs');
const path = require('path');

// MUST match the Media3 version bundled by your expo-video version.
// Expo SDK 54 / expo-video 3.0.x bundles Media3 1.8.0.
const MEDIA3_VERSION = '1.8.0';

const MEDIA3_DEPS = [
  '    // Android Auto media app (Media3, version aligned with expo-video)',
  `    implementation "androidx.media3:media3-common:${MEDIA3_VERSION}"`,
  `    implementation "androidx.media3:media3-session:${MEDIA3_VERSION}"`,
  `    implementation "androidx.media3:media3-exoplayer:${MEDIA3_VERSION}"`,
  `    implementation "androidx.media3:media3-exoplayer-hls:${MEDIA3_VERSION}"`,
].join('\n');

module.exports = function withAndroidAuto(config) {
  // 1) Media3 dependencies (compile classpath — expo-video's `implementation`
  //    deps are NOT visible to the app module, so these must be explicit).
  config = withAppBuildGradle(config, (cfg) => {
    if (cfg.modResults.language !== 'groovy') return cfg;
    const contents = cfg.modResults.contents;
    if (!contents.includes('androidx.media3:media3-session')) {
      cfg.modResults.contents = contents.replace(
        /dependencies\s*\{/,
        `dependencies {\n${MEDIA3_DEPS}`
      );
    }
    return cfg;
  });

  // 2) Manifest: permissions, Android Auto metadata and the media service.
  config = withAndroidManifest(config, (cfg) => {
    const manifest = cfg.modResults.manifest;

    const perms = manifest['uses-permission'] || (manifest['uses-permission'] = []);
    const ensurePermission = (name) => {
      if (!perms.some((p) => p.$?.['android:name'] === name)) {
        perms.push({ $: { 'android:name': name } });
      }
    };
    ensurePermission('android.permission.FOREGROUND_SERVICE');
    ensurePermission('android.permission.FOREGROUND_SERVICE_MEDIA_PLAYBACK');

    const app = manifest.application && manifest.application[0];
    if (!app) return cfg;

    // Declares this app as an Android Auto media app.
    const metaData = app['meta-data'] || (app['meta-data'] = []);
    const CAR_META = 'com.google.android.gms.car.application';
    if (!metaData.some((m) => m.$?.['android:name'] === CAR_META)) {
      metaData.push({
        $: { 'android:name': CAR_META, 'android:resource': '@xml/automotive_app_desc' },
      });
    }

    // MediaLibraryService declaration (audio-only live stream).
    const services = app['service'] || (app['service'] = []);
    const SERVICE_NAME = '.BragadoTvMediaService'; // ← relative to app package
    if (!services.some((s) => s.$?.['android:name'] === SERVICE_NAME)) {
      services.push({
        $: {
          'android:name': SERVICE_NAME,
          'android:exported': 'true',
          'android:foregroundServiceType': 'mediaPlayback',
        },
        'intent-filter': [
          {
            action: [
              { $: { 'android:name': 'androidx.media3.session.MediaLibraryService' } },
              { $: { 'android:name': 'android.media.browse.MediaBrowserService' } },
            ],
          },
        ],
      });
    }

    return cfg;
  });

  // 3) Copy the native service and the automotive description XML.
  config = withDangerousMod(config, [
    'android',
    async (cfg) => {
      const projectRoot = cfg.modRequest.projectRoot;
      const platformRoot = cfg.modRequest.platformProjectRoot; // <projectRoot>/android
      const sourceDir = path.join(projectRoot, 'plugins', 'android-auto');

      // ⚠️ CHANGE per app: the Java package path.
      const javaDir = path.join(platformRoot, 'app', 'src', 'main', 'java', 'com', 'bragado', 'tvok');
      const resDir = path.join(platformRoot, 'app', 'src', 'main', 'res', 'xml');

      fs.mkdirSync(javaDir, { recursive: true });
      fs.mkdirSync(resDir, { recursive: true });

      fs.copyFileSync(
        path.join(sourceDir, 'BragadoTvMediaService.kt'),
        path.join(javaDir, 'BragadoTvMediaService.kt')
      );
      fs.copyFileSync(
        path.join(sourceDir, 'automotive_app_desc.xml'),
        path.join(resDir, 'automotive_app_desc.xml')
      );

      return cfg;
    },
  ]);

  return config;
};
```

### 2.2 `plugins/android-auto/BragadoTvMediaService.kt` (native service)

```kotlin
package com.bragado.tvok   // ⚠️ CHANGE per app (must match your applicationId)

import android.net.Uri
import androidx.media3.common.AudioAttributes
import androidx.media3.common.C
import androidx.media3.common.MediaItem
import androidx.media3.common.MediaMetadata
import androidx.media3.common.util.UnstableApi
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.session.MediaLibraryService
import androidx.media3.session.MediaLibraryService.LibraryParams
import androidx.media3.session.MediaLibraryService.MediaLibrarySession
import androidx.media3.session.LibraryResult          // ← TOP-LEVEL (see gotcha #1)
import androidx.media3.session.MediaSession
import com.google.common.collect.ImmutableList
import com.google.common.util.concurrent.Futures
import com.google.common.util.concurrent.ListenableFuture
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Callable
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit

private const val ROOT_ID = "app-root"
private const val LIVE_ID = "app-live"

// ⚠️ CHANGE per app: remote config (line 1 = logo URL, line 2 = stream URL).
private const val CONFIG_URL = "https://your-server.example.com/admin/config.txt"
// Fallback if the remote config cannot be read.
private const val FALLBACK_LOGO_URL = "https://your-server.example.com/assets/logo.png"
private const val FALLBACK_STREAM_URL = "https://your-stream.example.com/live/stream.m3u8"

private data class RemoteConfig(val logoUrl: String?, val streamUrl: String)

class BragadoTvMediaService : MediaLibraryService() {

  private var mediaLibrarySession: MediaLibrarySession? = null

  override fun onCreate() {
    super.onCreate()

    // Resolve current logo + stream URL before building the session so the
    // browse tree always serves live values (bounded wait; fallback on failure).
    val config = resolveConfig()

    val player = ExoPlayer.Builder(this).build()
    player.setAudioAttributes(
      AudioAttributes.Builder()
        .setUsage(C.USAGE_MEDIA)
        .setContentType(C.AUDIO_CONTENT_TYPE_MUSIC)
        .build(),
      /* handleAudioFocus= */ true
    )
    // Audio-only: never select the video track of the live HLS stream.
    player.trackSelectionParameters =
      player.trackSelectionParameters
        .buildUpon()
        .setTrackTypeDisabled(C.TRACK_TYPE_VIDEO, /* disabled= */ true)
        .build()
    player.playWhenReady = true

    mediaLibrarySession =
      MediaLibrarySession.Builder(
        this,
        player,
        BragadoTvCallback(config.logoUrl, config.streamUrl)
      ).build()
  }

  override fun onGetSession(controllerInfo: MediaSession.ControllerInfo): MediaLibrarySession? =
    mediaLibrarySession

  override fun onDestroy() {
    mediaLibrarySession?.run {
      player.release()
      release()
      mediaLibrarySession = null
    }
    super.onDestroy()
  }

  private fun resolveConfig(): RemoteConfig {
    val executor = Executors.newSingleThreadExecutor()
    return try {
      executor.submit(Callable { fetchRemoteConfig() })
        .get(3, TimeUnit.SECONDS)
    } catch (e: Exception) {
      RemoteConfig(FALLBACK_LOGO_URL, FALLBACK_STREAM_URL)
    } finally {
      executor.shutdownNow()
    }
  }

  private fun fetchRemoteConfig(): RemoteConfig {
    val conn = URL(CONFIG_URL).openConnection() as HttpURLConnection
    conn.connectTimeout = 3000
    conn.readTimeout = 3000
    return try {
      conn.inputStream.bufferedReader(Charsets.UTF_8).use { reader ->
        val lines = reader.readLines()
        val logo = lines.getOrNull(0)?.trim()?.takeIf { it.startsWith("http") }
        val stream = lines.getOrNull(1)?.trim()?.takeIf { it.startsWith("http") }
        if (stream != null) RemoteConfig(logo, stream)
        else RemoteConfig(FALLBACK_LOGO_URL, FALLBACK_STREAM_URL)
      }
    } finally {
      conn.disconnect()
    }
  }

  private class BragadoTvCallback(
    private val logoUrl: String?,
    private val streamUrl: String
  ) : MediaLibrarySession.Callback {

    override fun onGetLibraryRoot(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      params: LibraryParams?
    ): ListenableFuture<LibraryResult<MediaItem>> {
      val rootMetadata =
        MediaMetadata.Builder()
          .setTitle("My App")            // ⚠️ CHANGE
          .setIsBrowsable(true)
          .setIsPlayable(false)
      if (logoUrl != null) {
        rootMetadata.setArtworkUri(Uri.parse(logoUrl))
      }
      val root =
        MediaItem.Builder()
          .setMediaId(ROOT_ID)
          .setMediaMetadata(rootMetadata.build())
          .build()
      return Futures.immediateFuture(LibraryResult.ofItem(root, params))
    }

    override fun onGetChildren(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      parentId: String,
      page: Int,
      pageSize: Int,
      params: LibraryParams?
    ): ListenableFuture<LibraryResult<ImmutableList<MediaItem>>> {
      val children: ImmutableList<MediaItem> =
        if (parentId == ROOT_ID) ImmutableList.of(liveItem())
        else ImmutableList.of()
      return Futures.immediateFuture(LibraryResult.ofItemList(children, params))
    }

    @OptIn(UnstableApi::class)
    override fun onGetItem(
      session: MediaLibrarySession,
      browser: MediaSession.ControllerInfo,
      mediaId: String
    ): ListenableFuture<LibraryResult<MediaItem>> {
      // REQUIRED: the car resolves the tapped selection by id. Without this the
      // default returns an error → "Could not load your selection".
      return Futures.immediateFuture(LibraryResult.ofItem(liveItem(), null))
    }

    @OptIn(UnstableApi::class)
    override fun onSetMediaItems(
      mediaSession: MediaSession,
      browser: MediaSession.ControllerInfo,
      mediaItems: List<MediaItem>,      // ← List, NOT ImmutableList (gotcha #3)
      startIndex: Int,
      startPositionMs: Long
    ): ListenableFuture<MediaSession.MediaItemsWithStartPosition> {
      // Single stream: always return our item with its URI (the controller may
      // strip the URI and send only the mediaId).
      return Futures.immediateFuture(
        MediaSession.MediaItemsWithStartPosition(listOf(liveItem()), 0, 0L)
      )
    }

    override fun onAddMediaItems(
      mediaSession: MediaSession,
      controller: MediaSession.ControllerInfo,
      mediaItems: List<MediaItem>
    ): ListenableFuture<List<MediaItem>> {
      return Futures.immediateFuture(listOf(liveItem()))
    }

    private fun liveItem(): MediaItem {
      val metadata =
        MediaMetadata.Builder()
          .setTitle("Live")              // ⚠️ CHANGE
          .setArtist("My App")           // ⚠️ CHANGE
          .setIsBrowsable(false)
          .setIsPlayable(true)
      if (logoUrl != null) {
        metadata.setArtworkUri(Uri.parse(logoUrl))
      }
      return MediaItem.Builder()
        .setMediaId(LIVE_ID)
        .setUri(streamUrl)
        .setMediaMetadata(metadata.build())
        .build()
    }
  }
}
```

### 2.3 `plugins/android-auto/automotive_app_desc.xml`

```xml
<?xml version="1.0" encoding="utf-8"?>
<automotiveApp>
  <uses name="media" />
</automotiveApp>
```

> For media apps the capability is `media`. (Template/Car-App-Library apps use `template` — different category.)

### 2.4 `app.config.js` — register the plugin

Add to the `plugins` array:

```js
"./plugins/with-android-auto"
```

---

## 3. Per-app customization checklist

### 3.1 Rename the Bragado TV identifiers (do this first)

§2 shows the real, tested Bragado TV code. **Every Bragado-specific identifier must be renamed per app** — otherwise you ship a competitor app with `BragadoTv*` names:

| Identifier | File | Current value |
|---|---|---|
| Kotlin `package` | `BragadoTvMediaService.kt` | `com.bragado.tvok` |
| **Class name** | `BragadoTvMediaService.kt` | `class BragadoTvMediaService` |
| **File name** (must match class) | — | `BragadoTvMediaService.kt` |
| Callback class (cosmetic) | `BragadoTvMediaService.kt` | `class BragadoTvCallback` |
| Manifest service name | `with-android-auto.js` → `SERVICE_NAME` | `'.BragadoTvMediaService'` |
| Plugin copy **source** | `with-android-auto.js` → `copyFileSync` | `'BragadoTvMediaService.kt'` |
| Plugin copy **dest path** | `with-android-auto.js` → `javaDir` | `java/com/bragado/tvok` |

> Keep the file name = the class name, and keep the manifest service name (`.XxxService`) pointing at that class. Rename the callback class too (cosmetic, but avoids confusion).

### 3.2 Content / config

| What | Where | Example |
|---|---|---|
| **Remote config URL** | Kotlin `CONFIG_URL` | your config.txt endpoint |
| **Fallback stream URL** | Kotlin `FALLBACK_STREAM_URL` | your HLS URL |
| **Fallback logo URL** | Kotlin `FALLBACK_LOGO_URL` | your logo |
| **Titles** | Kotlin `onGetLibraryRoot` + `liveItem()` | app name, item title |
| **Media3 version** | Config plugin `MEDIA3_VERSION` | match your expo-video |

---

## 4. Critical gotchas (each cost a failed build or a Play rejection)

1. **`LibraryResult` is TOP-LEVEL**, not nested:
   - ✅ `import androidx.media3.session.LibraryResult`
   - ❌ `import androidx.media3.session.MediaLibraryService.LibraryResult`
   - (But `LibraryParams`, `MediaLibrarySession`, and `MediaItemsWithStartPosition` ARE nested — `MediaLibraryService.LibraryParams`, `MediaSession.MediaItemsWithStartPosition`.)

2. **You MUST override `onGetItem`, `onSetMediaItems`, and `onAddMediaItems`.** If you don't, the defaults fail and Android Auto shows **"Could not load your selection."** The car resolves the tapped item by id via `onGetItem`.

3. **`onSetMediaItems` uses `List<MediaItem>`, NOT `ImmutableList<MediaItem>`** (in Media3 1.8.x). But `onGetChildren` DOES return `ImmutableList`. Mixed — check each signature.

4. **`MediaSession.MediaItemsWithStartPosition` requires `@OptIn(UnstableApi::class)`** (import `androidx.media3.common.util.UnstableApi`).

5. **Do NOT add `<uses-feature android:name="android.hardware.type.automotive">`.** Play Console **rejects** the AAB with:
   > *"The app cannot declare the device feature `android.hardware.type.automotive` and the `com.google.android.gms.car.application` metadata at the same time."*
   That `uses-feature` is for Android Automotive OS (AAOS) — NOT Android Auto. Only the `<meta-data>` + `automotive_app_desc.xml` are needed.

6. **Media3 version MUST match expo-video's bundled version.** Expo SDK 54 / expo-video 3.0.15 bundles **Media3 1.8.0**. Mismatch risks binary skew. Check `node_modules/expo-video/android/build.gradle` for `androidxMedia3Version`.

7. **Do NOT hardcode the stream URL — read it from remote config.** (The stream URL changed server-side once and caused "Source error" against a dead host.) Read line 2 of your config.txt with a bounded timeout + fallback.

8. **Audio-only:** set `player.trackSelectionParameters ... setTrackTypeDisabled(C.TRACK_TYPE_VIDEO, true)`. Android Auto media apps are audio-only; no video surface.

9. **expo-video's Media3 `implementation` deps are NOT on your app's compile classpath.** You must declare Media3 explicitly in the app's `build.gradle` (the plugin does this) or the Kotlin won't compile.

---

## 5. Testing

1. Build a local APK (`assembleDebug` / `assembleRelease`).
2. Install on the phone.
3. **Android Auto → developer mode** (tap the version ~10 times) → enable **"Unknown sources"**.
4. In Android Auto: menu → **"Start head unit server"**.
5. PC: `adb forward tcp:5277 tcp:5277`, then run the **Desktop Head Unit** from `SDK/extras/google/auto/desktop-head-unit.exe`.
6. On the DHU screen (NOT the phone), open audio/media apps → your app should appear → tap the item → audio plays.

> Android Auto has no "app selector" in the phone app — apps appear on the head unit screen only.

---

## 6. Play Console steps

1. **Privacy policy:** host a valid HTML page at a working URL → App content → Privacy policy.
2. **Build AAB** (`eas build -p android --profile ...`) — includes the Android Auto manifest.
3. **Release to a testing track** (Internal) — required for the Android Auto form factor.
4. **Advanced settings → Form factors → Add form factor → Android Auto.**
5. **Car quality review** by Google (DD-2 driver-distraction requirement for audio apps). Can take days.
6. Category: leaving the listing as-is (e.g. Entertainment) does NOT block Android Auto — the manifest is what enables it.

---

## 7. Why this is portable to other apps

The implementation is **self-contained and additive** — it does not modify existing app code:

- New files only (`plugins/with-android-auto.js`, `plugins/android-auto/*`).
- One line in `app.config.js`.
- The service is autonomous (no JS bridge, no changes to App.js/screens).
- Existing form factors (Android TV, mobile) are unaffected — verified: the changes only ADD a service/metadata/deps, removing nothing.

Porting effort per app: copy the 3 files + register the plugin + change the customization checklist (§3).
