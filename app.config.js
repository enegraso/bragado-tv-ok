export default {
  "expo": {
    "name": "Bragado TV OK",
    "slug": "bragado-tv-ok",
    "version": "1.1.0",
    "orientation": "default",

    "plugins": [
      [
        "@react-native-tvos/config-tv",
        {
          "androidTVBanner": "./assets/tvbannernew.png"
        }
      ],
      "./plugins/fix-microphone",
      "./plugins/fix-tv-manifest",
      [
        "expo-notifications",
        {
          "icon": "./assets/icon.png",
          "color": "#ffffff"
        }
      ],
      [
        "expo-video",
        {
          "supportsBackgroundPlayback": true,
          "supportsPictureInPicture": true
        }
      ],
      "./plugins/with-file-provider",
      "./plugins/with-android-auto"
    ],

    "androidStatusBar": {
      "translucent": true,
      "backgroundColor": "#000000"
    },

    "icon": "./assets/icon.png",

    "splash": {
      "image": "./assets/splash.png",
      "resizeMode": "cover",
      "backgroundColor": "#000000"
    },

    "android": {
      "package": "com.bragado.tvok",
      "versionCode": 1,
      "supportsPictureInPicture": true,
      "resizeableActivity": true,
      // Required for Android 13+ to request runtime notification permission
      "permissions": ["POST_NOTIFICATIONS"],
      // Ensure google-services.json is bundled into the native Android build
      googleServicesFile: process.env.GOOGLE_SERVICES_JSON || "./google-services.json",
    },

    "ios": {
      "bundleIdentifier": "com.bragado.tvok",
      "supportsTablet": true,
      infoPlist: {
        ITSAppUsesNonExemptEncryption: false
      }
    },

    "updates": {
      "enabled": true,
      "checkAutomatically": "ON_LAUNCH",
      "fallbackToCacheTimeout": 0
    },
    "runtimeVersion": "1.0.0",

    "assetBundlePatterns": ["**/*"],
    "extra": {
      "eas": {
        "projectId": "e72fcaf9-08bb-4061-ad2e-b63685caad89"
      }
    },

    "owner": "enegraso"
  }
};
