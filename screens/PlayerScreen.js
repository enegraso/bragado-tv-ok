import React, { useEffect, useRef, useState, useCallback } from 'react';
import { View, StyleSheet, StatusBar, Pressable, Text, BackHandler, Platform } from 'react-native';
import { Video } from 'expo-av';
import * as KeepAwake from 'expo-keep-awake';

let TVEventHandler = null;
try {
  TVEventHandler = require('react-native').TVEventHandler;
} catch (e) {}

export default function PlayerScreen({ streamUrl, onBack, bgColor = '#000', buttonBg = 'rgba(255,255,255,0.1)', textColor = '#fff' }) {
  const isTV = Platform.isTV;
  const [ready, setReady] = useState(false);
  const [error, setError] = useState(null);
  const [overlayVisible, setOverlayVisible] = useState(false);
  const hideTimerRef = useRef(null);
  const tvHandlerRef = useRef(null);

  useEffect(() => {
    KeepAwake.activateKeepAwakeAsync().catch(() => {});
    return () => {
      KeepAwake.deactivateKeepAwake().catch(() => {});
    };
  }, []);

  useEffect(() => {
    const onHardwareBack = () => {
      if (onBack) {
        try { onBack(); } catch (e) {}
        return true;
      }
      return false;
    };
    const sub = BackHandler.addEventListener('hardwareBackPress', onHardwareBack);
    return () => sub?.remove();
  }, [onBack]);

  useEffect(() => {
    if (!isTV || !TVEventHandler) return;
    const handler = new TVEventHandler();
    tvHandlerRef.current = handler;
    handler.enable(null, (cmp, evt) => {
      try {
        if (evt?.eventType === 'select') {
          if (overlayVisible) {
            setOverlayVisible(false);
            if (hideTimerRef.current) {
              clearTimeout(hideTimerRef.current);
              hideTimerRef.current = null;
            }
          } else {
            setOverlayVisible(true);
            hideTimerRef.current = setTimeout(() => setOverlayVisible(false), 4000);
          }
        }
      } catch (e) {}
    });
    return () => {
      handler.disable();
      tvHandlerRef.current = null;
    };
  }, [overlayVisible, isTV]);

  useEffect(() => {
    return () => {
      if (hideTimerRef.current) clearTimeout(hideTimerRef.current);
    };
  }, []);

  useEffect(() => {
    setReady(false);
    setError(null);
  }, [streamUrl]);

  const handleRetry = useCallback(async () => {
    try {
      setError(null);
      setReady(false);
      const vid = require('expo-av').Video;
    } catch (e) {}
  }, []);

  return (
    <View style={[styles.container, { backgroundColor: bgColor }]}>
      <StatusBar hidden />

      <Video
        style={styles.video}
        source={{ uri: streamUrl }}
        useNativeControls={false}
        resizeMode="contain"
        shouldPlay
        onPlaybackStatusUpdate={(s) => {
          if (s?.isLoaded) setReady(true);
          if (s?.error) setError(String(s.error));
        }}
        onError={(e) => {
          console.log('Video onError', e);
          setError(e?.nativeEvent?.error || JSON.stringify(e));
        }}
      />

      {error ? (
        <View style={styles.overlay} pointerEvents="box-none">
          <Text style={[styles.errorText, { color: textColor }]}>No se pudo reproducir el stream.</Text>
          <Text style={[styles.errorTextSmall, { color: textColor }]}>{error}</Text>
          <Pressable
            style={[styles.retryButton, { backgroundColor: buttonBg }]}
            onPress={() => {
              setError(null);
              setReady(false);
            }}
          >
            <Text style={[styles.retryText, { color: textColor }]}>REINTENTAR</Text>
          </Pressable>
        </View>
      ) : !ready ? (
        <View style={styles.loadingOverlay} pointerEvents="none">
          <View style={[styles.loadingBox, { backgroundColor: buttonBg }]}>
            <Text style={[styles.loadingText, { color: textColor }]}>Cargando stream...</Text>
          </View>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
  },
  video: {
    width: '100%',
    height: '100%',
    backgroundColor: '#000',
  },
  overlay: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 60,
    alignItems: 'center',
  },
  errorText: {
    color: '#fff',
    marginBottom: 6,
  },
  errorTextSmall: {
    color: '#ddd',
    fontSize: 12,
    marginBottom: 12,
    paddingHorizontal: 20,
    textAlign: 'center',
  },
  retryButton: {
    backgroundColor: 'rgba(255,255,255,0.1)',
    paddingVertical: 10,
    paddingHorizontal: 18,
    borderRadius: 8,
  },
  retryText: {
    color: '#fff',
    fontWeight: '600',
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingBox: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
  },
  loadingText: {
    fontSize: 14,
    fontWeight: '600',
  },
});
