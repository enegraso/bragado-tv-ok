import React, { useEffect, useRef, useState } from 'react';
import { View, StyleSheet, StatusBar, Platform, AppState, Alert, Linking } from 'react-native';
import { VideoView, useVideoPlayer } from 'expo-video';
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
const CONFIG_URL = 'https://km210.com/btbconfig.txt';
const DEFAULT_STREAM_URL = 'https://srv1053170.hstgr.cloud/hls/bragadotv.m3u8';



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

export default function App() {
  const isTV = Platform.isTV;
  const appState = useRef(AppState.currentState);
  const [streamUrl, setStreamUrl] = useState(DEFAULT_STREAM_URL);

  // ───────────── Video Player ─────────────
  const player = useVideoPlayer(streamUrl, (p) => {
    p.loop = false;
    p.play();
  });

  // ───────────── Init: remote config + OTA ─────────────
  useEffect(() => {
    let isMounted = true;


    async function loadRemoteConfig() {
      try {
        const res = await fetch(CONFIG_URL);
        const text = await res.text();

        const lines = text.split(/\r?\n/);
        const map = {};

        lines.forEach((line) => {
          const clean = line.trim();
          if (!clean || clean.startsWith('#')) return;
          const parts = clean.split('=');
          if (parts.length === 2) {
            map[parts[0].trim()] = parts[1].trim();
          }
        });

        if (map.STREAM_URL && isMounted) {
          setStreamUrl(map.STREAM_URL);
        }
      } catch (e) {
        console.log('Config remota no disponible, usando default');
      }
    }

    async function checkForUpdates() {
      try {
        const update = await Updates.checkForUpdateAsync();
        if (update.isAvailable) {
          await Updates.fetchUpdateAsync();
          await Updates.reloadAsync();
        }
      } catch (e) { }
    }

    loadRemoteConfig();
    checkForUpdates();
    checkStoreVersion();

    return () => {
      isMounted = false;
    };
  }, []);

  // ───────────── AppState handling ─────────────
  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      // Volver al foreground → reiniciar live
      if (appState.current !== 'active' && nextState === 'active') {
        try {
          player.replaceAsync(streamUrl);
          player.play();
        } catch (e) { }
      }

      // Background → PiP (solo Android Mobile)
      if (
        Platform.OS === 'android' &&
        !Platform.isTV &&
        nextState === 'background'
      ) {
        try {
          player.enterPictureInPicture();
        } catch (e) { }
      }

      appState.current = nextState;
    });

    return () => subscription.remove();
  }, [player, streamUrl]);

  // ───────────── Render ─────────────
  return (
    <View style={isTV ? styles.tvContainer : styles.mobileContainer}>
      <StatusBar hidden />
      <VideoView
        style={isTV ? styles.tvVideo : styles.mobileVideo}
        player={player}
        fullscreenOptions={{ enabled: true }}
      />
    </View>
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