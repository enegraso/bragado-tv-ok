export default {
  "expo": {
    "name": "Bragado TV OK",
    "slug": "bragado-tv-ok",
    "version": "1.0.18",
    "orientation": "default",

      "plugins": [
      [
        "@react-native-tvos/config-tv",
        {
          "androidTVBanner": "./assets/tvbannernew.png"
        }
      ],
      "./plugins/fix-microphone",
      "./plugins/fix-tv-manifest"
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

    "updates": {
      "fallbackToCacheTimeout": 0
    },

    "assetBundlePatterns": ["**/*"],
    "extra": {
      "eas": {
        "projectId": "e72fcaf9-08bb-4061-ad2e-b63685caad89"
      }
    },

    "owner": "enegraso"
  }
};
