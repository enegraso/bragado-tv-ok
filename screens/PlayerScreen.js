import React, { useEffect, useRef, useState } from 'react';
import { View, StyleSheet, StatusBar, Pressable, Text, BackHandler } from 'react-native';
import { Video } from 'expo-av';

export default function PlayerScreen({ streamUrl, onBack, bgColor = '#000', buttonBg = 'rgba(255,255,255,0.1)', textColor = '#fff' }) {
  const videoRef = useRef(null);
  const [status, setStatus] = useState({});
  const [error, setError] = useState(null);

  useEffect(() => {
    setError(null);
    const play = async () => {
      try {
        if (videoRef.current) {
          await videoRef.current.loadAsync({ uri: streamUrl }, { shouldPlay: true, isLooping: false });
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

  useEffect(() => {
    const onHardwareBack = () => {
      try { onBack && onBack(); } catch (e) {}
      return true; // prevent default (do not exit app)
    };
    const sub = BackHandler.addEventListener('hardwareBackPress', onHardwareBack);
    return () => {
      // BackHandler.removeEventListener is deprecated / removed in newer RN.
      // The returned subscription provides a .remove() method.
      try { sub && sub.remove && sub.remove(); } catch (e) {}
    };
  }, [onBack]);

  return (
    <View style={[styles.container, { backgroundColor: bgColor }] }>
      <StatusBar hidden />

      <Video
        ref={videoRef}
        style={styles.video}
        source={{ uri: streamUrl }}
        useNativeControls={false}
        resizeMode="contain"
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

      <Pressable style={[styles.back, { backgroundColor: buttonBg }]} onPress={onBack} accessibilityLabel="Volver">
        <Text style={[styles.backText, { color: textColor }]}>VOLVER</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  video: { width: '100%', height: '100%', backgroundColor: '#000' },
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
