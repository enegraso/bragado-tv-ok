export default {
  "expo": {
    "name": "Bragado TV OK",
    "slug": "bragado-tv-ok",
    "version": "1.0.14",
    "orientation": "default",

    "plugins": [
      [
        "@react-native-tvos/config-tv",
        {
          "androidTVBanner": "./assets/tv-banner.png"
        }
      ]
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
      "resizeableActivity": true
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
