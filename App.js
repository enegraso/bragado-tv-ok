import React, { useEffect, useRef, useState } from 'react';
import { View, StyleSheet, StatusBar, Platform, AppState, Alert, Linking } from 'react-native';
import * as Notifications from 'expo-notifications';
// Use expo-av player in a dedicated PlayerScreen for compatibility with Expo Go
import * as Updates from 'expo-updates';
import * as Application from 'expo-application';

import AsyncStorage from '@react-native-async-storage/async-storage';

const LAST_UPDATE_CHECK = 'LAST_UPDATE_CHECK';

async function shouldCheckToday() {
  const last = await AsyncStorage.getItem(LAST_UPDATE_CHECK);
  if (!last) return true;

  const lastDate = new Date(parseInt(last, 10));
  const now = new Date();

  return (
    now.getFullYear() !== lastDate.getFullYear() ||
    now.getMonth() !== lastDate.getMonth() ||
    now.getDate() !== lastDate.getDate()
  );
}

async function markCheckedToday() {
  await AsyncStorage.setItem(LAST_UPDATE_CHECK, Date.now().toString());
}

// ───────────── Config ─────────────
const DEFAULT_STREAM_URL = 'https://srv1053170.hstgr.cloud/hls/bragadotv.m3u8';

import { getConfig } from './services/remoteConfig';



function getLocalVersion() {
  return Application.nativeApplicationVersion || '1.0.0';
}

async function getPlayStoreVersion(packageName) {
  const res = await fetch(`https://play.google.com/store/apps/details?id=${packageName}&hl=es`);
  const html = await res.text();
  const match = html.match(/\[\[\["([\d.]+)"\]\]/);
  return match ? match[1] : null;
}

async function getAppStoreVersion(bundleId) {
  const res = await fetch(`https://itunes.apple.com/lookup?bundleId=${bundleId}`);
  const json = await res.json();
  return json.results[0]?.version || null;
}

function isNewer(store, local) {
  const a = store.split('.').map(Number);
  const b = local.split('.').map(Number);

  for (let i = 0; i < Math.max(a.length, b.length); i++) {
    const av = a[i] || 0;
    const bv = b[i] || 0;
    if (av > bv) return true;
    if (av < bv) return false;
  }
  return false;
}

async function checkStoreVersion() {
  if (!(await shouldCheckToday())) return;
  try {

    const local = getLocalVersion();
    let store = null;

    if (Platform.OS === 'android') {
      store = await getPlayStoreVersion('com.bragado.tvok');
    } else if (Platform.OS === 'ios') {
      store = await getAppStoreVersion('com.bragado.tvok');
    }

    if (store && isNewer(store, local)) {
      await markCheckedToday();
      Alert.alert(
        'Actualización disponible',
        'Hay una nueva versión de Bragado TV disponible.',
        [
          {
            text: 'Actualizar',
            onPress: () =>
              Linking.openURL(
                Platform.OS === 'android'
                  ? 'https://play.google.com/store/apps/details?id=com.bragado.tvok'
                  : 'https://apps.apple.com/app/idXXXXXXXX'
              ),
          },
          { text: 'Más tarde', style: 'cancel' },
        ]
      );
    }
  } catch (e) {
    console.log('Error verificando versión de tienda', e);
  }
}

import HomeScreen from './screens/HomeScreen';
import PlayerScreen from './screens/PlayerScreen';
import { SafeAreaProvider } from 'react-native-safe-area-context';

export default function App() {
  const isTV = Platform.isTV;
  const appState = useRef(AppState.currentState);
  const [streamUrl, setStreamUrl] = useState(DEFAULT_STREAM_URL);
  const [logoUrl, setLogoUrl] = useState(null);
  const [bgColor, setBgColor] = useState('#000000');
  const [buttonBg, setButtonBg] = useState('rgba(255,255,255,0.08)');
  const [textColor, setTextColor] = useState('#ffffff');
  const [slogan, setSlogan] = useState('Canal de streaming en vivo desde Bragado...');
  const [buttonFocusBorder, setButtonFocusBorder] = useState('#ffffff');
  const [buttonFocusWidth, setButtonFocusWidth] = useState(3);
  const [showHome, setShowHome] = useState(true);

  // Note: the original implementation used expo-video's native player.
  // For Expo Go compatibility we render a separate expo-av based PlayerScreen.

  // ───────────── Init: remote config + OTA ─────────────
  useEffect(() => {
    let isMounted = true;

  async function init() {
      try {
        const cfg = await getConfig();
        if (!isMounted) return;
        if (cfg?.stream) setStreamUrl(cfg.stream);
        if (cfg?.logo) setLogoUrl(cfg.logo);
        if (cfg?.fondo) setBgColor(cfg.fondo);
        if (cfg?.botonfondo) setButtonBg(cfg.botonfondo);
        if (cfg?.colorletras) setTextColor(cfg.colorletras);
        if (cfg?.slogan) setSlogan(cfg.slogan);
        if (cfg?.boton_border) setButtonFocusBorder(cfg.boton_border);
        if (cfg?.boton_border_width) setButtonFocusWidth(parseInt(cfg.boton_border_width,10) || 3);
      } catch (e) {
        console.log('Config remota no disponible, usando default');
      }

      try {
        const update = await Updates.checkForUpdateAsync();
        if (update.isAvailable) {
          await Updates.fetchUpdateAsync();
          await Updates.reloadAsync();
        }
      } catch (e) { }

      checkStoreVersion();
    }

    init();

    // Request notification permission on first app start (one-time prompt)
    (async () => {
      try {
        const asked = await AsyncStorage.getItem('NOTIF_PROMPTED_V1');
        if (!asked) {
          const perm = await Notifications.getPermissionsAsync();
          if (perm.status !== 'granted') {
            // prompt the user on first run
            await Notifications.requestPermissionsAsync();
          }
          await AsyncStorage.setItem('NOTIF_PROMPTED_V1', '1');
        }
      } catch (e) {
        console.log('error requesting notif perm on start', e);
      }
    })();

    return () => {
      isMounted = false;
    };
  }, []);

  // Notification handlers: foreground display via an Alert/modal and response handling
  useEffect(() => {
    // Configure handler so system notifications don't auto-show while app is foreground
    Notifications.setNotificationHandler({
      handleNotification: async () => ({ shouldShowAlert: false, shouldPlaySound: true, shouldSetBadge: false }),
    });

    const receivedSub = Notifications.addNotificationReceivedListener((notification) => {
      // Received in foreground. We avoid showing an in-app Alert here so the system
      // notification UI can display the notification (configured via setNotificationHandler).
      try {
        console.log('Notification received (foreground):', notification.request.content);
      } catch (e) {
        console.log('notification received handler error', e);
      }
    });

    const responseSub = Notifications.addNotificationResponseReceivedListener((response) => {
      try {
        const data = response.notification.request.content.data || {};
        if (data.url) {
          Linking.openURL(data.url).catch(() => {});
        } else if (data.type === 'programacion') {
          // Navigate to the player (Live) when a "programacion" notification is tapped.
          // If the notification provides a stream URL, use it; otherwise use the current streamUrl.
          try {
            if (data.stream) setStreamUrl(data.stream);
          } catch (e) {}
          setShowHome(false);
        }
      } catch (e) {
        console.log('notification response handler error', e);
      }
    });

    return () => {
      try { receivedSub && receivedSub.remove && receivedSub.remove(); } catch (e) {}
      try { responseSub && responseSub.remove && responseSub.remove(); } catch (e) {}
    };
  }, []);

  // ───────────── AppState handling ─────────────
  // The PlayerScreen will manage playback lifecycle when active. No global player here.
  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      appState.current = nextState;
    });

    return () => subscription.remove();
  }, []);

  // ───────────── Render ─────────────
  return (
    <SafeAreaProvider>
      {showHome ? (
        <HomeScreen
          onStart={() => setShowHome(false)}
          logoUrl={logoUrl}
          bgColor={bgColor}
          buttonBg={buttonBg}
          textColor={textColor}
          slogan={slogan}
          buttonFocusBorder={buttonFocusBorder}
          buttonFocusWidth={buttonFocusWidth}
        />
      ) : (
        <PlayerScreen streamUrl={streamUrl} onBack={() => setShowHome(true)} bgColor={bgColor} buttonBg={buttonBg} textColor={textColor} slogan={slogan} buttonFocusBorder={buttonFocusBorder} buttonFocusWidth={buttonFocusWidth} />
      )}
    </SafeAreaProvider>
  );
}

// ───────────── Styles ─────────────
const styles = StyleSheet.create({
  tvContainer: {
    flex: 1,
    backgroundColor: '#000',
  },
  tvVideo: {
    width: '100%',
    height: '100%',
    backgroundColor: '#000',
  },
  mobileContainer: {
    flex: 1,
    backgroundColor: '#000',
  },
  mobileVideo: {
    width: '100%',
    height: '100%',
    backgroundColor: '#000',
  },
});
