const { withAndroidManifest } = require('@expo/config-plugins');

// The injection of the REQUEST_INSTALL_PACKAGES permission is only necessary
// when the app downloads an APK and launches the installer. To avoid having
// this permission present for Play Store builds, you can set the
// environment variable SKIP_REQUEST_INSTALL_PACKAGES=true (for example in
// eas.json build profile env) or PLAY_STORE=1. When set, the plugin will
// still add the FileProvider but won't add the install permission.
const SKIP_ENV = process.env.SKIP_REQUEST_INSTALL_PACKAGES === 'true' || process.env.PLAY_STORE === '1';

module.exports = function withFileProvider(config) {
  return withAndroidManifest(config, (cfg) => {
    const manifest = cfg.modResults.manifest;

    if (!manifest['uses-permission']) {
      manifest['uses-permission'] = [];
    }

    if (!SKIP_ENV) {
      const hasInstallPerm = manifest['uses-permission'].some(
        (p) => p.$?.['android:name'] === 'android.permission.REQUEST_INSTALL_PACKAGES'
      );
      if (!hasInstallPerm) {
        manifest['uses-permission'].push({
          $: { 'android:name': 'android.permission.REQUEST_INSTALL_PACKAGES' },
        });
      }
    } else {
      // eslint-disable-next-line no-console
      console.log('with-file-provider: SKIP_REQUEST_INSTALL_PACKAGES is set; not adding REQUEST_INSTALL_PACKAGES permission');
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
      // Determine package name reliably: prefer app config, then AndroidManifest package, fallback to known package
      const configPackage = (cfg.modRequest && cfg.modRequest.platformProject && cfg.modRequest.platformProject.android && cfg.modRequest.platformProject.android.package) || (cfg.expo && cfg.expo.android && cfg.expo.android.package) || (cfg.android && cfg.android.package) || null;
      const manifestPackage = cfg.modResults && cfg.modResults.manifest && cfg.modResults.manifest.$ && cfg.modResults.manifest.$.package;
      const pkg = configPackage || manifestPackage || 'com.bragado.tvok';

      app['provider'].push({
        $: {
          'android:name': 'androidx.core.content.FileProvider',
          // make authorities explicit to avoid conflicts between different apps
          'android:authorities': `${pkg}.fileprovider`,
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
