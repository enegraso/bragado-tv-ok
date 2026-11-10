import React, { useState, useRef, useEffect } from 'react';
import { useWindowDimensions } from 'react-native';
import { View, Text, Image, Pressable, StyleSheet, Linking, Modal, ScrollView, Animated, Easing, Platform, BackHandler, ActivityIndicator } from 'react-native';
import { WebView } from 'react-native-webview';
import { LinearGradient } from 'expo-linear-gradient';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import SettingsScreen from '../app/screens/SettingsScreen';

function darkenColor(hex, factor = 0.7) {
  try {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgb(${Math.round(r * factor)}, ${Math.round(g * factor)}, ${Math.round(b * factor)})`;
  } catch (e) {
    return hex;
  }
}

function lightenColor(hex, factor = 1.3) {
  try {
    const r = Math.min(255, parseInt(hex.slice(1, 3), 16) * factor);
    const g = Math.min(255, parseInt(hex.slice(3, 5), 16) * factor);
    const b = Math.min(255, parseInt(hex.slice(5, 7), 16) * factor);
    return `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
  } catch (e) {
    return hex;
  }
}

function isValidHex(color) {
  return /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(color);
}

function withHttps(value) {
  if (!value) return null;
  const v = String(value).trim();
  if (!v) return null;
  if (/^[a-zA-Z][a-zA-Z\d+.-]*:/.test(v)) return v;
  return `https://${v}`;
}

function whatsappUrl(value) {
  if (!value) return null;
  const digits = String(value).replace(/\D/g, '');
  if (!digits) return null;
  return `https://wa.me/${digits}`;
}

function mailUrl(value) {
  if (!value) return null;
  const v = String(value).trim();
  if (!v) return null;
  return v.startsWith('mailto:') ? v : `mailto:${v}`;
}

export default function HomeScreen({ onStart, logoUrl, bgColor = '#000', buttonBg = 'rgba(255,255,255,0.08)', textColor = '#fff', slogan = 'Canal de streaming en vivo desde Bragado. Programación local, noticias y entrevistas.', whatsapp, facebook, instagram, twitter, tiktok, youtube, mail, webUrl, ownerLogo, ownerSlogan, buttonFocusBorder = '#fff', buttonFocusWidth = 3 }) {
  const { width } = useWindowDimensions();
  const source = logoUrl ? { uri: logoUrl } : require('../assets/logo.png');

  const gradientColors = isValidHex(bgColor)
    ? [darkenColor(bgColor, 0.6), bgColor, lightenColor(bgColor, 1.1)]
    : [bgColor, bgColor, bgColor];

  const containerStyle = [styles.container];
  const buttonStyle = [styles.button, { backgroundColor: buttonBg }];
  const textStyle = [styles.buttonText, { color: textColor }];
  const subtitleStyle = [styles.subtitle, { color: textColor }];

  const [showSettings, setShowSettings] = useState(false);
  const [showWeb, setShowWeb] = useState(false);
  const webviewRef = useRef(null);
  const [canGoBack, setCanGoBack] = useState(false);
  const [webProgress, setWebProgress] = useState(0);
  const [webLoading, setWebLoading] = useState(false);
  const [gearFocused, setGearFocused] = useState(false);
  const gearAnim = useRef(new Animated.Value(0)).current;
  const insets = useSafeAreaInsets();
  // compute consistent offsets for top-right buttons
  const topBase = (insets.top || 12) + 12; // base distance from top
  const topGap = 56; // gap between stacked buttons
  const rightOffset = 20; // distance from right edge

  // Handle Android hardware back when Web modal is open: navigate webview history first
  useEffect(() => {
    if (Platform.OS !== 'android') return;
    if (!showWeb) return;
    const onBack = () => {
      if (canGoBack && webviewRef.current) {
        try { webviewRef.current.goBack(); } catch (e) {}
        return true; // handled
      }
      setShowWeb(false);
      return true;
    };
    const sub = BackHandler.addEventListener('hardwareBackPress', onBack);
    return () => sub.remove();
  }, [showWeb, canGoBack]);

  return (
    <LinearGradient colors={gradientColors} style={[containerStyle, { paddingTop: (insets.top || 12) + 40 }]}> 
      <Pressable
        style={[styles.gear, { top: topBase, right: rightOffset }]}
        focusable={true}
        accessible={true}
        onFocus={() => { setGearFocused(true); Animated.timing(gearAnim, { toValue: 1, duration: 180, easing: Easing.out(Easing.ease), useNativeDriver: true }).start(); }}
        onBlur={() => { setGearFocused(false); Animated.timing(gearAnim, { toValue: 0, duration: 120, easing: Easing.in(Easing.ease), useNativeDriver: true }).start(); }}
        onPress={() => setShowSettings(true)}
        accessibilityLabel="Abrir configuración"
      >
        <Animated.View style={[{ transform: [{ scale: gearAnim.interpolate({ inputRange: [0,1], outputRange: [1, 1.06] }) }] }, gearFocused ? { borderWidth: buttonFocusWidth, borderColor: buttonFocusBorder, borderRadius: 8, padding: 6 } : { padding: 6 }] }>
          <Text style={{color:textColor,fontSize:20}}>⚙️</Text>
        </Animated.View>
      </Pressable>

      {/* Top buttons only on mobile */}
      {!Platform.isTV && (
        <>
          <Pressable
            style={[styles.topButton, { top: topBase + topGap, right: rightOffset }]}
            focusable
            onPress={() => Linking.openURL(Platform.OS === 'android' ? 'https://play.google.com/store/apps/details?id=com.bragado.tvok' : 'https://apps.apple.com/app/id6747498493')}
            accessibilityLabel="Calificar la app"
          >
            <Animated.View style={[styles.topButtonInner]}>
              <Text style={{color:textColor, fontSize:20}}>⭐</Text>
            </Animated.View>
          </Pressable>

          <Pressable
            style={[styles.topButton, { top: topBase + topGap * 2, right: rightOffset }]}
            focusable
            onPress={() => BackHandler.exitApp()}
            accessibilityLabel="Salir de la aplicación"
          >
            <Animated.View style={[styles.topButtonInner]}>
              <Text style={{color:textColor, fontSize:20}}>🚪</Text>
            </Animated.View>
          </Pressable>
        </>
      )}

      <ScrollView contentContainerStyle={[styles.scrollContainer, { paddingBottom: (insets.bottom || 12) + 24 }] }>
        <Image source={source} style={styles.logo} resizeMode="contain" onLongPress={() => setShowSettings(true)} />
        <Text style={subtitleStyle}>{slogan}</Text>

        <FocusableButton label="VER EN VIVO" onPress={onStart} buttonStyle={[buttonStyle, styles.buttonLarge]} textStyle={textStyle} focusBorderColor={buttonFocusBorder} focusBorderWidth={buttonFocusWidth} />

        <FocusableButton label="WEB OFICIAL" onPress={() => setShowWeb(true)} buttonStyle={[buttonStyle, styles.buttonLarge]} textStyle={textStyle} focusBorderColor={buttonFocusBorder} focusBorderWidth={buttonFocusWidth} />

        {/* Social buttons on mobile, close/exit on TV */}
        {Platform.isTV ? (
          <FocusableButton label="CERRAR" onPress={() => BackHandler.exitApp()} buttonStyle={[buttonStyle, styles.buttonLarge]} textStyle={textStyle} focusBorderColor={buttonFocusBorder} focusBorderWidth={buttonFocusWidth} />
        ) : (
          <View style={styles.socialRow}>
            {whatsappUrl(whatsapp) ? <SocialIcon icon={require('../assets/social-whatsapp.webp')} url={whatsappUrl(whatsapp)} bg={buttonBg} /> : null}
            {withHttps(facebook) ? <SocialIcon icon={require('../assets/social-facebook.webp')} url={withHttps(facebook)} bg={buttonBg} /> : null}
            {withHttps(instagram) ? <SocialIcon icon={require('../assets/social-instagram.webp')} url={withHttps(instagram)} bg={buttonBg} /> : null}
            {withHttps(twitter) ? <SocialIcon icon={require('../assets/social-twitter.webp')} url={withHttps(twitter)} bg={buttonBg} /> : null}
            {withHttps(tiktok) ? <SocialIcon icon={require('../assets/social-tiktok.webp')} url={withHttps(tiktok)} bg={buttonBg} /> : null}
            {withHttps(youtube) ? <SocialIcon icon={require('../assets/social-youtube.webp')} url={withHttps(youtube)} bg={buttonBg} /> : null}
            {mailUrl(mail) ? <SocialIcon icon={require('../assets/social-mail.webp')} url={mailUrl(mail)} bg={buttonBg} /> : null}
          </View>
        )}

        {/* Owner logo (logo2) - visible below socials on mobile, and under the CERRAR button on TV */}
        {ownerLogo ? (
          <>
            {/* Responsive owner logo size: larger on TV, scaled on mobile */}
            {(() => {
              const logoWidth = Platform.isTV ? 300 : Math.min(260, Math.floor(width * 0.6));
              // use a slightly taller aspect so logo is more visible on mobile
              const logoHeight = Math.round(logoWidth * 0.5);
              return (
                <Image source={{ uri: ownerLogo }} style={[styles.ownerLogo, { width: logoWidth, height: logoHeight, marginTop: Platform.isTV ? 22 : 40 }]} resizeMode="contain" />
              );
            })()}
            {ownerSlogan ? <Text style={[styles.ownerSlogan, { color: textColor }]}>{ownerSlogan}</Text> : null}
          </>
        ) : null}
      </ScrollView>

      <Modal visible={showSettings} animationType="slide" onRequestClose={() => setShowSettings(false)}>
        <SettingsScreen onClose={() => setShowSettings(false)} />
      </Modal>
      {/* Web Modal */}
      <Modal visible={showWeb} animationType="slide" onRequestClose={() => setShowWeb(false)}>
        <View style={{ flex: 1 }}>
          <View style={{ height: 56, backgroundColor: '#111', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 12 }}>
            <Pressable onPress={() => setShowWeb(false)} style={{ padding: 8 }}>
              <Text style={{ color: '#fff', fontSize: 16 }}>Cerrar</Text>
            </Pressable>
            <Text style={{ color: '#fff', fontWeight: '600' }} numberOfLines={1} ellipsizeMode="middle">{webUrl || 'Web Oficial'}</Text>
            <View style={{ width: 48 }} />
          </View>

          {/* Progress bar */}
          <View style={{ height: 3, backgroundColor: '#222' }}>
            <View style={{ height: 3, backgroundColor: '#4a90d9', width: `${Math.min(100, Math.round(webProgress * 100))}%` }} />
          </View>

          <WebView
            ref={webviewRef}
            source={{ uri: withHttps(webUrl) || 'https://bragadotv.com.ar' }}
            style={{ flex: 1 }}
            onNavigationStateChange={(nav) => setCanGoBack(Boolean(nav.canGoBack))}
            onLoadStart={() => { setWebLoading(true); setWebProgress(0); }}
            onLoadEnd={() => { setWebLoading(false); setWebProgress(1); }}
            onLoadProgress={({ nativeEvent }) => { setWebProgress(nativeEvent.progress); }}
            startInLoadingState
          />

          {webLoading && (
            <View style={{ position: 'absolute', left: 0, right: 0, top: 56 + 3, bottom: 0, justifyContent: 'center', alignItems: 'center' }} pointerEvents="none">
              <ActivityIndicator size="large" color="#4a90d9" />
            </View>
          )}
        </View>
      </Modal>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  logo: {
    width: 180,
    height: 180,
    marginBottom: 28,
  },
  subtitle: {
    color: '#ddd',
    textAlign: 'center',
    marginBottom: 28,
  },
  button: {
    backgroundColor: 'rgba(255,255,255,0.08)',
    paddingVertical: 14,
    paddingHorizontal: 26,
    borderRadius: 8,
    minWidth: 220,
    alignItems: 'center',
    marginVertical: 8,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  }
  ,
  gear: {
    position: 'absolute',
    right: 10,
    padding: 8,
    zIndex: 10,
    backgroundColor: 'transparent'
  },
  scrollContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24
  }
});

// Additional styles
Object.assign(styles, StyleSheet.create({
  buttonLarge: {
    borderRadius: 16,
    minWidth: 240,
    paddingVertical: 16,
    paddingHorizontal: 32,
  },
  socialRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    gap: 12,
    marginTop: 20,
  },
  socialBtn: {
    margin: 4,
  },
  socialIcon: {
    width: 52,
    height: 52,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  socialLogo: {
    width: 28,
    height: 28,
  },
  ownerLogo: {
    width: 160,
    height: 60,
    marginTop: 18,
    alignSelf: 'center',
  },
  ownerSlogan: {
    marginTop: 8,
    textAlign: 'center',
    fontSize: 13,
    color: '#aaa',
  },
  // TV-specific larger owner logo
  ownerLogoTV: {
    width: 300,
    height: 140,
    marginTop: 22,
    alignSelf: 'center',
  },
  ownerSloganTV: {
    marginTop: 10,
    textAlign: 'center',
    fontSize: 16,
    color: '#aaa',
  },
  topButton: {
    position: 'absolute',
    right: 16,
    zIndex: 10,
  },
  topButtonInner: {
    backgroundColor: 'rgba(0,0,0,0.25)',
    borderRadius: 14,
    paddingVertical: 12,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
}));

function FocusableButton({ label, onPress, buttonStyle, textStyle, focusBorderColor, focusBorderWidth }) {
  const [focused, setFocused] = useState(false);
  const anim = useRef(new Animated.Value(0)).current;
  const onFocus = () => { setFocused(true); Animated.timing(anim, { toValue: 1, duration: 180, easing: Easing.out(Easing.ease), useNativeDriver: true }).start(); };
  const onBlur = () => { setFocused(false); Animated.timing(anim, { toValue: 0, duration: 120, easing: Easing.in(Easing.ease), useNativeDriver: true }).start(); };
  return (
    <Pressable
      focusable={true}
      onFocus={onFocus}
      onBlur={onBlur}
      onPress={onPress}
    >
      <Animated.View style={[buttonStyle, { transform: [{ scale: anim.interpolate({ inputRange: [0,1], outputRange: [1, 1.04] }) }] }, focused ? { borderWidth: focusBorderWidth, borderColor: focusBorderColor } : null]}>
        <Text style={textStyle}>{label}</Text>
      </Animated.View>
    </Pressable>
  );
}

function SocialIcon({ icon, url, bg }) {
  const [focused, setFocused] = useState(false);
  const anim = useRef(new Animated.Value(0)).current;
  const onFocus = () => { setFocused(true); Animated.timing(anim, { toValue: 1, duration: 180, easing: Easing.out(Easing.ease), useNativeDriver: true }).start(); };
  const onBlur = () => { setFocused(false); Animated.timing(anim, { toValue: 0, duration: 120, easing: Easing.in(Easing.ease), useNativeDriver: true }).start(); };
  return (
    <Pressable focusable={true} onFocus={onFocus} onBlur={onBlur} onPress={() => Linking.openURL(url)} style={styles.socialBtn}>
      <Animated.View style={[styles.socialIcon, { backgroundColor: bg }, { transform: [{ scale: anim.interpolate({ inputRange: [0,1], outputRange: [1, 1.08] }) }] }, focused ? { borderWidth: 3, borderColor: '#fff' } : null]}>
        <Image source={icon} style={styles.socialLogo} resizeMode="contain" />
      </Animated.View>
    </Pressable>
  );
}
