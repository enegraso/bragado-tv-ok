export default {
  "expo": {
    "name": "Bragado TV OK",
    "slug": "bragado-tv-ok",
    "version": "1.0.16",
    "orientation": "default",

    "plugins": [
      [
        "@react-native-tvos/config-tv",
        {
          "androidTVBanner": "./assets/iconobannertv.png"
        }
      ],
      "./plugins/fix-microphone"
    ],

    "androidStatusBar": {
      "translucent": true,
      "backgroundColor": "#000000"
    },

    "icon": "./assets/icono.png",

    "splash": {
      "image": "./assets/splash.png",
      "resizeMode": "cover",
      "backgroundColor": "#000000"
    },

    "android": {
      "package": "com.bragado.tvok",
      "adaptiveIcon": {
        "foregroundImage": "./assets/iconotv.png",
        "backgroundColor": "#000000"
      },
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
