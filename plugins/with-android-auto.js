const { withAndroidManifest, withAppBuildGradle, withDangerousMod } = require('@expo/config-plugins');
const fs = require('fs');
const path = require('path');

// Media3 version aligned with the one bundled by expo-video (SDK 54) to avoid
// binary skew between our service and the video module.
const MEDIA3_VERSION = '1.8.0';

const MEDIA3_DEPS = [
  '    // Android Auto media app (Media3, version aligned with expo-video)',
  `    implementation "androidx.media3:media3-common:${MEDIA3_VERSION}"`,
  `    implementation "androidx.media3:media3-session:${MEDIA3_VERSION}"`,
  `    implementation "androidx.media3:media3-exoplayer:${MEDIA3_VERSION}"`,
  `    implementation "androidx.media3:media3-exoplayer-hls:${MEDIA3_VERSION}"`,
].join('\n');

module.exports = function withAndroidAuto(config) {
  // 1) Media3 dependencies for audio playback + media session.
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

    // com.google.android.gms.car.application → declares this app as an Android
    // Auto media app pointing at the automotive_app_desc.xml resource.
    const metaData = app['meta-data'] || (app['meta-data'] = []);
    const CAR_META = 'com.google.android.gms.car.application';
    if (!metaData.some((m) => m.$?.['android:name'] === CAR_META)) {
      metaData.push({
        $: { 'android:name': CAR_META, 'android:resource': '@xml/automotive_app_desc' },
      });
    }

    // MediaLibraryService declaration (audio-only live stream).
    const services = app['service'] || (app['service'] = []);
    const SERVICE_NAME = '.BragadoTvMediaService';
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

  // 3) Copy the native service and the automotive description XML into the
  // generated Android project during prebuild.
  config = withDangerousMod(config, [
    'android',
    async (cfg) => {
      const projectRoot = cfg.modRequest.projectRoot;
      const platformRoot = cfg.modRequest.platformProjectRoot; // <projectRoot>/android
      const sourceDir = path.join(projectRoot, 'plugins', 'android-auto');

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
