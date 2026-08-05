import React, { useEffect, useRef, useState } from 'react';
import { View, StyleSheet, StatusBar, Pressable, Text, BackHandler, Platform, TVEventHandler } from 'react-native';
import { Video } from 'expo-av';

export default function PlayerScreen({ streamUrl, onBack, bgColor = '#000', buttonBg = 'rgba(255,255,255,0.1)', textColor = '#fff' }) {
  const videoRef = useRef(null);
  const [status, setStatus] = useState({});
  const [error, setError] = useState(null);
  const isTV = Platform.isTV;
  const [overlayVisible, setOverlayVisible] = useState(isTV ? false : true);
  const hideTimerRef = useRef(null);
  const tvHandlerRef = useRef(null);

  useEffect(() => {
    setError(null);
    const play = async () => {
      try {
        if (videoRef.current) {
          await videoRef.current.loadAsync({ uri: streamUrl }, { shouldPlay: true, isLooping: false });
          // start playback
          try { await videoRef.current.playAsync(); } catch (e) {}
          // try to present fullscreen if available (native)
          try { if (videoRef.current.presentFullscreenPlayer) await videoRef.current.presentFullscreenPlayer(); } catch (e) {}
        }
      } catch (e) {
        console.log('Error loading stream in expo-av Video:', e);
        setError(e?.message || String(e));
      }
    };
    play();
    return () => {
      try { videoRef.current && videoRef.current.unloadAsync(); } catch (e) {}
    };
  }, [streamUrl]);

  // Helper to show overlay and hide after timeout
  const showOverlay = (autoHide = true) => {
    setOverlayVisible(true);
    if (hideTimerRef.current) {
      clearTimeout(hideTimerRef.current);
      hideTimerRef.current = null;
    }
    if (autoHide) {
      hideTimerRef.current = setTimeout(() => setOverlayVisible(false), 4000);
    }
  };

  const hideOverlay = () => {
    if (hideTimerRef.current) {
      clearTimeout(hideTimerRef.current);
      hideTimerRef.current = null;
    }
    setOverlayVisible(false);
  };

  useEffect(() => {
    // Handle hardware back: on TV we want back to go to home screen of the app
    const onHardwareBack = () => {
      try { onBack && onBack(); } catch (e) {}
      return true; // prevent default (do not exit app)
    };
    const sub = BackHandler.addEventListener('hardwareBackPress', onHardwareBack);
    return () => {
      try { sub && sub.remove && sub.remove(); } catch (e) {}
    };
  }, [onBack]);

  // TV select handler: toggle overlay on OK (select) button
  useEffect(() => {
    if (!isTV) return;
    const tvEventHandler = new TVEventHandler();
    tvHandlerRef.current = tvEventHandler;
    tvEventHandler.enable(null, (cmp, evt) => {
      try {
        if (evt && evt.eventType === 'select') {
          // toggle overlay visibility and reset timer
          if (overlayVisible) {
            setOverlayVisible(false);
            if (hideTimerRef.current) { clearTimeout(hideTimerRef.current); hideTimerRef.current = null; }
          } else {
            showOverlay(true);
          }
        }
      } catch (e) {}
    });
    return () => {
      try { tvEventHandler.disable(); } catch (e) {}
      tvHandlerRef.current = null;
    };
  }, [overlayVisible, isTV]);

  // attempt to enter PiP when app goes to background on Android (best-effort)
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => false);
    return () => { try { sub && sub.remove && sub.remove(); } catch(e) {} };
  }, []);

  // AppState -> try PiP on background for Android mobile (best-effort, may not be supported)
  useEffect(() => {
    const handleAppState = async (nextState) => {
      if (Platform.OS === 'android' && nextState === 'background' && !isTV) {
        try {
          // try multiple method names depending on native implementation
          if (videoRef.current && videoRef.current.startPictureInPicture) {
            await videoRef.current.startPictureInPicture();
          } else if (videoRef.current && videoRef.current.presentPictureInPicture) {
            await videoRef.current.presentPictureInPicture();
          }
        } catch (e) { /* ignore */ }
      }
    };
    // register AppState listener if needed
    // (we don't import AppState here to avoid changes if not previously used)
  }, [isTV]);

  // Render: use native controls only; fullscreen by style and attempted native fullscreen call
  return (
    <View style={[styles.container, { backgroundColor: bgColor }] }>
      <StatusBar hidden />

      <Video
        ref={videoRef}
        style={styles.video}
        source={{ uri: streamUrl }}
        useNativeControls={true}
        resizeMode={'cover'}
        shouldPlay
        onError={(e) => {
          console.log('Video onError', e);
          setError(e?.nativeEvent?.error || JSON.stringify(e));
        }}
        onPlaybackStatusUpdate={(s) => setStatus(s)}
      />

      {error ? (
        <View style={styles.overlay} pointerEvents="box-none">
          <Text style={[styles.errorText, { color: textColor }]}>No se pudo reproducir el stream.</Text>
          <Text style={[styles.errorTextSmall, { color: textColor }]}>{String(error)}</Text>
          <Pressable style={[styles.retryButton, { backgroundColor: buttonBg }]} onPress={() => {
            setError(null);
            try { videoRef.current && videoRef.current.replayAsync(); } catch (e) { /* ignore */ }
          }}>
            <Text style={[styles.retryText, { color: textColor }]}>REINTENTAR</Text>
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  video: { width: '100%', height: '100%', backgroundColor: '#000' },
  controlsOverlay: { position: 'absolute', bottom: 28, left: 16, right: 16, flexDirection: 'row', justifyContent: 'center', alignItems: 'center' },
  controlButton: { paddingVertical: 10, paddingHorizontal: 22, borderRadius: 8, marginHorizontal: 8, minWidth: 140, alignItems: 'center' },
  controlText: { color: '#fff', fontWeight: '700', textAlign: 'center' },
  back: {
    position: 'absolute',
    top: 40,
    left: 16,
    backgroundColor: 'rgba(0,0,0,0.5)',
    padding: 8,
    borderRadius: 6,
  },
  backText: { color: '#fff', fontWeight: '600' },
  overlay: { position: 'absolute', left: 0, right: 0, bottom: 60, alignItems: 'center' },
  errorText: { color: '#fff', marginBottom: 6 },
  errorTextSmall: { color: '#ddd', fontSize: 12, marginBottom: 12, paddingHorizontal: 20, textAlign: 'center' },
  retryButton: {
    backgroundColor: 'rgba(255,255,255,0.1)',
    paddingVertical: 10,
    paddingHorizontal: 18,
    borderRadius: 8,
  },
  retryText: { color: '#fff', fontWeight: '600' }
});
