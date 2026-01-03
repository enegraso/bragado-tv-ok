const { withAndroidManifest } = require("@expo/config-plugins");

module.exports = function fixMicrophone(config) {
  return withAndroidManifest(config, (config) => {
    const manifest = config.modResults.manifest;

    let features = manifest["uses-feature"] || [];

    const found = features.find(
      f => f.$["android:name"] === "android.hardware.microphone"
    );

    if (found) {
      found.$["android:required"] = "false";
    } else {
      features.push({
        $: {
          "android:name": "android.hardware.microphone",
          "android:required": "false"
        }
      });
    }

    manifest["uses-feature"] = features;
    return config;
  });
};
