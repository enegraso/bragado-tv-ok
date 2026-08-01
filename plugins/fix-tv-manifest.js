const { withAndroidManifest } = require("@expo/config-plugins");

module.exports = function fixTvManifest(config) {
  return withAndroidManifest(config, (config) => {
    const manifest = config.modResults.manifest;

    // Ensure touchscreen is not required (so TVs without touch are included)
    let features = manifest["uses-feature"] || [];
    const tsFound = features.find(f => f.$ && f.$["android:name"] === "android.hardware.touchscreen");
    if (tsFound) {
      tsFound.$["android:required"] = "false";
    } else {
      features.push({ $: { "android:name": "android.hardware.touchscreen", "android:required": "false" } });
    }
    manifest["uses-feature"] = features;

    // Add LEANBACK_LAUNCHER intent so the app is available on Android TV/Google TV
    const app = manifest.application && manifest.application[0];
    if (app && app.activity) {
      const activities = app.activity;
      // Find main launcher activity (has intent-filter with MAIN and LAUNCHER)
      let mainActivity = null;
      for (const act of activities) {
        const intents = act['intent-filter'] || [];
        for (const intent of Array.isArray(intents) ? intents : [intents]) {
          const actions = intent.action || [];
          const categories = intent.category || [];
          const hasMain = actions.some(a => a.$ && a.$['android:name'] === 'android.intent.action.MAIN');
          const hasLauncher = categories.some(c => c.$ && c.$['android:name'] === 'android.intent.category.LAUNCHER');
          if (hasMain && hasLauncher) {
            mainActivity = act;
            break;
          }
        }
        if (mainActivity) break;
      }

      if (mainActivity) {
        // Ensure there's an intent-filter with LEANBACK_LAUNCHER
        let found = false;
        const intents = mainActivity['intent-filter'] || [];
        for (const intent of Array.isArray(intents) ? intents : [intents]) {
          const categories = intent.category || [];
          if (categories.some(c => c.$ && c.$['android:name'] === 'android.intent.category.LEANBACK_LAUNCHER')) {
            found = true; break;
          }
        }
        if (!found) {
          // push a new intent-filter for leanback
          const newIntent = {
            'action': [{ $: { 'android:name': 'android.intent.action.MAIN' } }],
            'category': [{ $: { 'android:name': 'android.intent.category.LEANBACK_LAUNCHER' } }]
          };
          if (!mainActivity['intent-filter']) mainActivity['intent-filter'] = [];
          mainActivity['intent-filter'].push(newIntent);
        }
      }
    }

    return config;
  });
};
