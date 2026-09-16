import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { getLocales } from 'expo-localization';

import ka from './locales/ka.json';
import ru from './locales/ru.json';
import en from './locales/en.json';

export const SUPPORTED_LOCALES = ['ka', 'ru', 'en'] as const;
export type AppLocale = (typeof SUPPORTED_LOCALES)[number];

export const LOCALE_LABELS: Record<AppLocale, string> = {
  ka: 'ქართული',
  ru: 'Русский',
  en: 'English',
};

/** მოწყობილობის locale → ჩვენი სამიდან ერთი; fallback = en (სპეც. 14) */
export function detectLocale(): AppLocale {
  const tags = getLocales().map((l) => l.languageCode ?? '');
  const hit = tags.find((t) => (SUPPORTED_LOCALES as readonly string[]).includes(t));
  return (hit as AppLocale) ?? 'en';
}

i18n.use(initReactI18next).init({
  resources: {
    ka: { translation: ka },
    ru: { translation: ru },
    en: { translation: en },
  },
  lng: detectLocale(),
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
  returnNull: false,
});

export function setAppLocale(locale: AppLocale) {
  return i18n.changeLanguage(locale);
}

export default i18n;
