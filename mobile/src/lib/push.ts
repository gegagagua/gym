import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import { api } from '@/api/client';
import i18n from '@/i18n';

/**
 * Push-ის რეგისტრაცია (სპეც. M6 / 15).
 *
 * ლიმიტი — მაქს. 2 push დღეში — სერვერზეა (`PushService`), არა აქ.
 * კლიენტის საქმეა მხოლოდ ტოკენის მიწოდება და ლოკალური შეხსენებები.
 */
export async function registerForPush(deviceUuid: string, appVersion?: string): Promise<string | null> {
  if (!Device.isDevice) return null; // სიმულატორზე push ტოკენი არ არსებობს

  const existing = await Notifications.getPermissionsAsync();
  let status = existing.status;

  if (status !== 'granted') {
    status = (await Notifications.requestPermissionsAsync()).status;
  }

  if (status !== 'granted') return null;

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'Kalisteni',
      importance: Notifications.AndroidImportance.DEFAULT,
      lightColor: '#D7FF3E',
    });
  }

  const projectId =
    Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;

  const token = (await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined)).data;

  await api('/me', {
    method: 'PATCH',
    body: {
      device: {
        push_token: token,
        platform: Platform.OS,
        app_version: appVersion,
        locale: i18n.language,
      },
      device_uuid: deviceUuid,
    },
  }).catch(() => undefined);

  return token;
}

/**
 * ვარჯიშის ლოკალური შეხსენება. ლოკალურია განზრახ — სერვერს არ სჭირდება
 * მომხმარებლის განრიგის ცოდნა, და ოფლაინშიც მუშაობს.
 */
export async function scheduleWorkoutReminder(hour: number, minute = 0, weekdays?: number[]) {
  await Notifications.cancelAllScheduledNotificationsAsync();

  const content = {
    title: i18n.t('home.todayWorkout'),
    body: i18n.t('home.start'),
    sound: 'default' as const,
  };

  if (!weekdays || weekdays.length === 0) {
    await Notifications.scheduleNotificationAsync({
      content,
      trigger: {
        type: Notifications.SchedulableTriggerInputTypes.DAILY,
        hour,
        minute,
      },
    });
    return;
  }

  for (const weekday of weekdays) {
    await Notifications.scheduleNotificationAsync({
      content,
      trigger: {
        type: Notifications.SchedulableTriggerInputTypes.WEEKLY,
        weekday,
        hour,
        minute,
      },
    });
  }
}
