import * as Application from 'expo-application';
import { Platform } from 'react-native';
import Constants from 'expo-constants';

const VERSION_URL = 'https://tvbragado.com.ar/admin/version.json';

function parseVersion(v) {
  return v.split('.').map(Number);
}

function isNewer(remote, current) {
  const r = parseVersion(remote);
  const c = parseVersion(current);
  for (let i = 0; i < Math.max(r.length, c.length); i++) {
    const a = r[i] || 0;
    const b = c[i] || 0;
    if (a > b) return true;
    if (a < b) return false;
  }
  return false;
}

export async function checkForApkUpdate() {
  if (Platform.OS !== 'android') return null;
  // If the app was installed from Google Play, prefer Play updates and
  // don't prompt the user to install a sideloaded APK. We determine this
  // by attempting to read the Play Install Referrer — this API is only
  // available for apps installed via Google Play. If the call succeeds,
  // we assume the app came from Play and skip the APK flow.
  try {
    if (Application.getInstallReferrerAsync) {
      await Application.getInstallReferrerAsync();
      // No error -> installed via Play (or Play referrer API available). Skip APK update.
      return null;
    }
  } catch (e) {
    // If the API is unavailable or throws, we treat the app as not installed
    // via Play and continue to check our version.json for APK updates.
  }
  try {
    const res = await fetch(VERSION_URL, { cache: 'no-store' });
    const remote = await res.json();
    const currentVersion = Constants.expoConfig?.version || Application.nativeApplicationVersion || '1.0.0';
    if (remote.version && isNewer(remote.version, currentVersion)) {
      return {
        version: remote.version,
        apkUrl: remote.apkUrl,
        changelog: remote.changelog || '',
      };
    }
  } catch (e) {
    console.log('APK update check failed:', e);
  }
  return null;
}
