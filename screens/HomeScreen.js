import React, { useState } from 'react';
import { View, Text, Image, Pressable, StyleSheet, Linking, Modal, ScrollView } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import SettingsScreen from '../app/screens/SettingsScreen';

export default function HomeScreen({ onStart, logoUrl, bgColor = '#000', buttonBg = 'rgba(255,255,255,0.08)', textColor = '#fff', slogan = 'Canal de streaming en vivo desde Bragado. Programación local, noticias y entrevistas.', buttonFocusBorder = '#fff', buttonFocusWidth = 3 }) {
  const source = logoUrl ? { uri: logoUrl } : require('../assets/logo.png');

  const containerStyle = [styles.container, { backgroundColor: bgColor }];
  const buttonStyle = [styles.button, { backgroundColor: buttonBg }];
  const textStyle = [styles.buttonText, { color: textColor }];
  const subtitleStyle = [styles.subtitle, { color: textColor }];

  const [showSettings, setShowSettings] = useState(false);
  const insets = useSafeAreaInsets();

  return (
    <View style={[containerStyle, { paddingTop: insets.top || 12 }]}> 
      <Pressable
        style={[styles.gear, { top: (insets.top || 10) + 8 }]}
        onPress={() => setShowSettings(true)}
        accessibilityLabel="Abrir configuración"
      >
        <Text style={{color:textColor}}>⚙️</Text>
      </Pressable>

      <ScrollView contentContainerStyle={[styles.scrollContainer, { paddingBottom: (insets.bottom || 12) + 24 }] }>
        <Image source={source} style={styles.logo} resizeMode="contain" onLongPress={() => setShowSettings(true)} />
        <Text style={subtitleStyle}>{slogan}</Text>

        <FocusableButton label="VER EN VIVO" onPress={onStart} buttonStyle={buttonStyle} textStyle={textStyle} focusBorderColor={buttonFocusBorder} focusBorderWidth={buttonFocusWidth} />

        <FocusableButton label="WEB OFICIAL" onPress={() => Linking.openURL('https://bragadotv.com.ar')} buttonStyle={buttonStyle} textStyle={textStyle} focusBorderColor={buttonFocusBorder} focusBorderWidth={buttonFocusWidth} />

        <FocusableButton label="LLAMAR" onPress={() => Linking.openURL('tel:+5492342480567')} buttonStyle={buttonStyle} textStyle={textStyle} focusBorderColor={buttonFocusBorder} focusBorderWidth={buttonFocusWidth} />
      </ScrollView>

      <Modal visible={showSettings} animationType="slide" onRequestClose={() => setShowSettings(false)}>
        <SettingsScreen onClose={() => setShowSettings(false)} />
      </Modal>
    </View>
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

function FocusableButton({ label, onPress, buttonStyle, textStyle, focusBorderColor, focusBorderWidth }) {
  const [focused, setFocused] = useState(false);
  return (
    <Pressable
      focusable={true}
      onFocus={() => setFocused(true)}
      onBlur={() => setFocused(false)}
      onPress={onPress}
      style={[buttonStyle, focused ? { borderWidth: focusBorderWidth, borderColor: focusBorderColor } : null]}
    >
      <Text style={textStyle}>{label}</Text>
    </Pressable>
  );
}
