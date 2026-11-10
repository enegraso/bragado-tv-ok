import React, { useEffect, useState } from 'react';
import { View, Text, Switch, Button, StyleSheet, Alert, BackHandler, Linking, ScrollView } from 'react-native';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { getExpoPushTokenAsync, registerTokenOnServer, unregisterTokenOnServer } from '../services/notifications';
import * as Application from 'expo-application';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function SettingsScreen({ onClose }) {
  const [portal, setPortal] = useState(true);
  const [programacion, setProgramacion] = useState(true);
  const [token, setToken] = useState(null);
  const [registered, setRegistered] = useState(false);
  const [permissionStatus, setPermissionStatus] = useState(null);
  const insets = useSafeAreaInsets();

  useEffect(() => {
    let mounted = true;
    (async () => {
      const storedPortal = await AsyncStorage.getItem('notify_portal');
      const storedProg = await AsyncStorage.getItem('notify_programacion');
      if (storedPortal !== null) setPortal(storedPortal === '1');
      if (storedProg !== null) setProgramacion(storedProg === '1');

      const t = await getExpoPushTokenAsync();
      if (mounted && t) setToken(t);
      // If token exists and at least one pref enabled, auto-register
      try {
        const deviceId = Device.osInternalBuildId || Application.androidId || 'unknown';
        if (t && (portal || programacion)) {
          const ok = await registerTokenOnServer(t, deviceId, portal, programacion);
          setRegistered(!!ok);
        } else if (t && !portal && !programacion) {
          await unregisterTokenOnServer(t, deviceId);
          setRegistered(false);
        }
      } catch (e) {
        console.log('auto register error', e);
      }
    })();

    const onHardwareBack = () => {
      try { onClose && onClose(); } catch (e) {}
      return true;
    };
    const sub = BackHandler.addEventListener('hardwareBackPress', onHardwareBack);
    return () => { mounted = false; sub.remove(); };
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const p = await Notifications.getPermissionsAsync();
        setPermissionStatus(p.status);
      } catch (e) { console.log('perm check', e); }
    })();
  }, []);

  // When preferences or token change, auto register/unregister accordingly
  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        await AsyncStorage.setItem('notify_portal', portal ? '1' : '0');
        await AsyncStorage.setItem('notify_programacion', programacion ? '1' : '0');
        if (!token) return;
        const deviceId = Device.osInternalBuildId || Application.androidId || 'unknown';
        if (portal || programacion) {
          const ok = await registerTokenOnServer(token, deviceId, portal, programacion);
          if (mounted) setRegistered(!!ok);
        } else {
          await unregisterTokenOnServer(token, deviceId);
          if (mounted) setRegistered(false);
        }
      } catch (e) {
        console.log('pref change register error', e);
      }
    })();
    return () => { mounted = false; };
  }, [portal, programacion, token]);

  return (
    <ScrollView contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 20, paddingBottom: insets.bottom + 24 }}>
      <Text style={styles.title}>Notificaciones</Text>
      <View style={styles.row}><Text>Portal (noticias)</Text><Switch value={portal} onValueChange={setPortal} /></View>
      <View style={styles.row}><Text>Programación</Text><Switch value={programacion} onValueChange={setProgramacion} /></View>
      <View style={{marginTop:12}}>
        <Text style={{fontSize:12,color:'#666'}}>Expo Push Token (visible si disponible):</Text>
        <Text style={{fontSize:12,marginTop:6}} selectable>{token || '(no disponible en Expo Go / emulador)'}</Text>
        <Text style={{fontSize:12,color:'#666',marginTop:8}}>Estado: {registered ? 'Registrado' : 'No registrado'}</Text>
        <Text style={{fontSize:12,color:'#666',marginTop:8}}>Versión: {Application.nativeApplicationVersion || 'desconocida'}</Text>
      </View>
      {permissionStatus !== 'granted' ? (
        <View style={{marginTop:12}}>
          <Button title="Solicitar permiso de notificaciones" onPress={async () => {
            try {
              const res = await Notifications.requestPermissionsAsync();
              setPermissionStatus(res.status);
              if (res.status !== 'granted') {
                Alert.alert('Permiso rechazado', 'Puede activar las notificaciones desde la configuración del sistema.', [
                  { text: 'Abrir ajustes', onPress: () => Linking.openSettings() },
                  { text: 'Cerrar', style: 'cancel' }
                ]);
              } else {
                Alert.alert('Permiso otorgado', 'Ahora la app puede recibir notificaciones.');
              }
            } catch (e) {
              Alert.alert('Error', String(e));
            }
          }} />
        </View>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding:20 },
  title: { fontSize:18, fontWeight:'700', marginBottom:12 },
  row: { flexDirection:'row', justifyContent:'space-between', alignItems:'center', marginVertical:8 }
});
