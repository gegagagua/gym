import { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Screen, Text, Button, Glow, Tap, Reveal, Breathe } from '@/components';
import { Option, OnboardingProgress } from '@/features/onboarding';
import { colors, space } from '@/theme';
import { LOCALE_LABELS, SUPPORTED_LOCALES, type AppLocale } from '@/i18n';
import { useAuth } from '@/store/auth';

export default function LanguageScreen() {
  const { t, i18n } = useTranslation();
  const router = useRouter();
  const setLocale = useAuth((s) => s.setLocale);
  const [selected, setSelected] = useState<AppLocale>(i18n.language as AppLocale);

  return (
    <Screen
      scroll={false}
      ambient={colors.accent}
      footer={
        <View style={{ gap: space.md }}>
          <Button
            title={t('common.continue')}
            onPress={async () => {
              await setLocale(selected);
              router.push('/(onboarding)/goal');
            }}
          />

          {/* დაბრუნებული მომხმარებელი — ონბორდინგი ხელახლა არ სჭირდება */}
          <Tap
            onPress={async () => {
              await setLocale(selected);
              router.push('/auth?mode=signin');
            }}
          >
            <Text variant="bodySm" tone="muted" center>
              {t('auth.haveAccount')} <Text variant="bodySm" style={{ color: colors.accent }}>{t('auth.signInTitle')}</Text>
            </Text>
          </Tap>
        </View>
      }
    >
      <OnboardingProgress step={1} total={5} />

      <View style={{ flex: 1, justifyContent: 'center', gap: space.xxl, paddingBottom: space.xxl }}>
        {/* ლოგო: ორი ნაწილი ერთმანეთის მიყოლებით ჯდება ადგილზე */}
        <View style={{ alignItems: 'center', gap: space.sm }}>
          <Breathe from={0.34} to={0.6} period={6000} style={{ position: 'absolute', top: -40 }}>
            <Glow color={colors.accent} size={260} opacity={1} />
          </Breathe>

          <Reveal from="top" distance={22} zoom>
            <Text variant="hero" style={{ color: colors.accent, letterSpacing: -3 }}>
              KAL
            </Text>
          </Reveal>

          <Reveal index={1} distance={22} zoom>
            <Text variant="hero" style={{ marginTop: -18, letterSpacing: -3 }}>
              ISTENI
            </Text>
          </Reveal>
        </View>

        <Reveal index={2} style={{ gap: space.sm }}>
          <Text variant="title" center>
            {t('onboarding.languageTitle')}
          </Text>
          <Text variant="bodySm" tone="muted" center>
            {t('onboarding.languageSub')}
          </Text>
        </Reveal>

        <View style={{ gap: space.sm }}>
          {SUPPORTED_LOCALES.map((locale, index) => (
            <Reveal key={locale} index={3 + index}>
              <Option
                title={LOCALE_LABELS[locale]}
                selected={selected === locale}
                onPress={() => setSelected(locale)}
              />
            </Reveal>
          ))}
        </View>
      </View>
    </Screen>
  );
}
