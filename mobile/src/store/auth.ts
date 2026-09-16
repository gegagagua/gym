import { create } from 'zustand';
import * as Crypto from 'expo-crypto';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { auth as authApi, me as meApi } from '@/api/endpoints';
import { setToken, getToken } from '@/api/client';
import type { MeResponse } from '@/api/types';
import { setAppLocale, type AppLocale } from '@/i18n';

type User = MeResponse['data'];

const DEVICE_KEY = 'kalisteni.device_uuid';
const ONBOARDED_KEY = 'kalisteni.onboarded';

interface AuthState {
  user: User | null;
  ready: boolean;
  onboarded: boolean;
  deviceUuid: string | null;

  bootstrap: () => Promise<void>;
  continueAsGuest: () => Promise<void>;
  signInWithOtp: (phone: string, code: string) => Promise<void>;
  upgradeGuest: (phone: string, code: string) => Promise<void>;
  refreshUser: () => Promise<void>;
  patchProfile: (patch: Record<string, unknown>) => Promise<void>;
  setLocale: (locale: AppLocale) => Promise<void>;
  completeOnboarding: () => Promise<void>;
  signOut: () => Promise<void>;
}

async function deviceUuid(): Promise<string> {
  const stored = await AsyncStorage.getItem(DEVICE_KEY);
  if (stored) return stored;

  const fresh = Crypto.randomUUID();
  await AsyncStorage.setItem(DEVICE_KEY, fresh);
  return fresh;
}

export const useAuth = create<AuthState>((set, get) => ({
  user: null,
  ready: false,
  onboarded: false,
  deviceUuid: null,

  async bootstrap() {
    const [uuid, onboarded, token] = await Promise.all([
      deviceUuid(),
      AsyncStorage.getItem(ONBOARDED_KEY),
      getToken(),
    ]);

    set({ deviceUuid: uuid, onboarded: onboarded === '1' });

    if (token) {
      try {
        const response = await meApi.get();
        set({ user: response.data });
        if (response.data.locale) await setAppLocale(response.data.locale);
      } catch {
        // ტოკენი გაუვარგისდა — ონბორდინგზე ვაბრუნებთ, ლოკალური მონაცემი რჩება
        await setToken(null);
      }
    }

    set({ ready: true });
  },

  async continueAsGuest() {
    const uuid = get().deviceUuid ?? (await deviceUuid());
    const response = await authApi.guest(uuid);

    await setToken(response.token);
    set({ user: response.user, deviceUuid: uuid });
  },

  async signInWithOtp(phone, code) {
    const uuid = get().deviceUuid ?? (await deviceUuid());
    const response = await authApi.verifyOtp(phone, code, uuid);

    await setToken(response.token);
    set({ user: response.user });
  },

  /** სტუმრის ანგარიშს ნომერს აბამს — XP და ისტორია ადგილზე რჩება (სპეც. 5.1) */
  async upgradeGuest(phone, code) {
    await authApi.upgrade(phone, code);
    await get().refreshUser();
  },

  async refreshUser() {
    const response = await meApi.get();
    set({ user: response.data });
  },

  async patchProfile(patch) {
    const response = await meApi.updateProfile(patch);
    set({ user: response.data });
  },

  async setLocale(locale) {
    await setAppLocale(locale);
    set((state) => (state.user ? { user: { ...state.user, locale } } : {}));

    if (await getToken()) {
      await meApi.update({ locale }).catch(() => undefined);
    }
  },

  async completeOnboarding() {
    await AsyncStorage.setItem(ONBOARDED_KEY, '1');
    set({ onboarded: true });
  },

  async signOut() {
    await authApi.logout().catch(() => undefined);
    await setToken(null);
    set({ user: null });
  },
}));
