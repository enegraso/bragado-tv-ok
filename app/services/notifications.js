import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';

// tokens endpoint hosted under /admin on the site
const SERVER_REGISTER_URL = 'https://tvbragado.com.ar/admin/register_token.php';

export async function getExpoPushTokenAsync() {
  if (!Device.isDevice) return null;
  const { status: existingStatus } = await Notifications.getPermissionsAsync();
  let finalStatus = existingStatus;
  if (existingStatus !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync();
    finalStatus = status;
  }
  if (finalStatus !== 'granted') return null;
  const tokenData = await Notifications.getExpoPushTokenAsync({
    projectId: 'e72fcaf9-08bb-4061-ad2e-b63685caad89'
  });
  return tokenData.data;
}

export async function registerTokenOnServer(token, deviceId, portal = true, programacion = true) {
  try {
    await fetch(SERVER_REGISTER_URL, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ token, deviceId, portal, programacion, action: 'register' })
    });
    return true;
  } catch (e) {
    console.log('registerTokenOnServer error', e);
    return false;
  }
}

export async function unregisterTokenOnServer(token, deviceId) {
  try {
    await fetch(SERVER_REGISTER_URL, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ token, deviceId, action: 'unregister' })
    });
    return true;
  } catch (e) {
    console.log('unregisterTokenOnServer error', e);
    return false;
  }
}
