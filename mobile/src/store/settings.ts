import { create } from 'zustand';
import AsyncStorage from '@react-native-async-storage/async-storage';

const KEY = 'kalisteni.settings';

interface SettingsState {
  soundCues: boolean;
  hapticCues: boolean;
  keepAwake: boolean;
  autoRest: boolean;
  hydrated: boolean;

  hydrate: () => Promise<void>;
  toggle: (key: 'soundCues' | 'hapticCues' | 'keepAwake' | 'autoRest') => void;
}

export const useSettings = create<SettingsState>((set, get) => ({
  // აუდიო-სიგნალები ჩართულია ნაგულისხმევად — ვარჯიშის დროს
  // ეკრანს იშვიათად უყურებ (სპეც. M5)
  soundCues: true,
  hapticCues: true,
  keepAwake: true,
  autoRest: true,
  hydrated: false,

  async hydrate() {
    const raw = await AsyncStorage.getItem(KEY);
    set({ ...(raw ? JSON.parse(raw) : {}), hydrated: true });
  },

  toggle(key) {
    const next = { ...get(), [key]: !get()[key] };
    set({ [key]: next[key] } as Partial<SettingsState>);

    AsyncStorage.setItem(
      KEY,
      JSON.stringify({
        soundCues: next.soundCues,
        hapticCues: next.hapticCues,
        keepAwake: next.keepAwake,
        autoRest: next.autoRest,
      }),
    ).catch(() => undefined);
  },
}));
