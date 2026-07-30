import React from 'react';
import { View, Text, Image, Pressable, StyleSheet, Linking } from 'react-native';

export default function HomeScreen({ onStart, logoUrl, bgColor = '#000', buttonBg = 'rgba(255,255,255,0.08)', textColor = '#fff' }) {
  const source = logoUrl ? { uri: logoUrl } : require('../assets/logo.png');

  const containerStyle = [styles.container, { backgroundColor: bgColor }];
  const buttonStyle = [styles.button, { backgroundColor: buttonBg }];
  const textStyle = [styles.buttonText, { color: textColor }];
  const subtitleStyle = [styles.subtitle, { color: textColor }];

  return (
    <View style={containerStyle}>
      <Image source={source} style={styles.logo} resizeMode="contain" />
      <Text style={subtitleStyle}>Canal de streaming en vivo desde Bragado. Programación local, noticias y entrevistas.</Text>

      <Pressable style={buttonStyle} onPress={onStart} accessibilityLabel="Ver en vivo">
        <Text style={textStyle}>VER EN VIVO</Text>
      </Pressable>

      <Pressable style={buttonStyle} onPress={() => Linking.openURL('https://bragadotv.com.ar')} accessibilityLabel="Web oficial">
        <Text style={textStyle}>WEB OFICIAL</Text>
      </Pressable>

      <Pressable style={buttonStyle} onPress={() => Linking.openURL('tel:+5492342480567') } accessibilityLabel="Llamar">
        <Text style={textStyle}>LLAMAR</Text>
      </Pressable>
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
});
