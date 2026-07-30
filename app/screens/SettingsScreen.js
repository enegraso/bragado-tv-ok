import React, { useEffect, useState } from 'react';
import { View, Text, Switch, Button, StyleSheet, Alert } from 'react-native';
import * as Device from 'expo-device';
import { getExpoPushTokenAsync, registerTokenOnServer, unregisterTokenOnServer } from '../services/notifications';
import * as Application from 'expo-application';

export default function SettingsScreen({ onClose }) {
  const [portal, setPortal] = useState(true);
  const [programacion, setProgramacion] = useState(true);
  const [token, setToken] = useState(null);

  useEffect(() => {
    (async () => {
      const t = await getExpoPushTokenAsync();
      if (t) setToken(t);
    })();
  }, []);

  const register = async () => {
    const deviceId = Device.osInternalBuildId || Application.androidId || 'unknown';
    const ok = await registerTokenOnServer(token, deviceId, portal, programacion);
    Alert.alert(ok ? 'Registrado' : 'Error', ok ? 'Token registrado en servidor' : 'Fallo al registrar');
  };

  const unregister = async () => {
    const deviceId = Device.osInternalBuildId || Application.androidId || 'unknown';
    const ok = await unregisterTokenOnServer(token, deviceId);
    Alert.alert(ok ? 'Desregistrado' : 'Error', ok ? 'Token eliminado del servidor' : 'Fallo al desregistrar');
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Notificaciones</Text>
      <View style={styles.row}><Text>Portal (noticias)</Text><Switch value={portal} onValueChange={setPortal} /></View>
      <View style={styles.row}><Text>Programación</Text><Switch value={programacion} onValueChange={setProgramacion} /></View>
      <View style={{marginTop:20}}>
        <Button title="Registrar token en servidor" onPress={register} />
        <View style={{height:8}} />
        <Button title="Desregistrar token" onPress={unregister} />
        <View style={{height:8}} />
        <Button title="Cerrar" onPress={onClose} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding:20 },
  title: { fontSize:18, fontWeight:'700', marginBottom:12 },
  row: { flexDirection:'row', justifyContent:'space-between', alignItems:'center', marginVertical:8 }
});
