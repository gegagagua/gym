import { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Screen, Text, Button, Card, Reveal } from '@/components';
import { OnboardingProgress, Stepper } from '@/features/onboarding';
import { colors, space } from '@/theme';
import { useOnboardingDraft } from '@/features/onboarding/draft';
import { useAuth } from '@/store/auth';
import { me as meApi } from '@/api/endpoints';

export default function LevelTestScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const draft = useOnboardingDraft();
  const { continueAsGuest, user } = useAuth();

  const [pushup, setPushup] = useState(draft.pushup);
  const [pullup, setPullup] = useState(draft.pullup);
  const [plank, setPlank] = useState(draft.plankSec);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    draft.set({ pushup, pullup, plankSec: plank });

    try {
      // ანგარიში ონბორდინგის ბოლოს იქმნება — მომხმარებელმა ჯერ
      // ღირებულება უნდა დაინახოს (სპეც. 5.1)
      if (!user) await continueAsGuest();

      await meApi.updateProfile({
        goal: draft.goal ?? undefined,
        equipment: draft.equipment.length ? draft.equipment : undefined,
        birth_year: draft.birthYear ?? undefined,
        gender: draft.gender ?? undefined,
        height_cm: draft.heightCm ?? undefined,
        weight_kg: draft.weightKg ?? undefined,
        disclaimer_accepted: true,
      });

      const result = await meApi.levelTest({ pushup, pullup, plank_sec: plank });

      draft.set({
        level: result.level,
        recommendedProgramId: result.recommended_program?.id ?? null,
      });

      router.push('/(onboarding)/result');
    } catch {
      // ქსელი არ არის — ლოკალურად ვაგრძელებთ, სინქი მოგვიანებით მოხდება
      draft.set({ level: null });
      router.push('/(onboarding)/result');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      ambient={colors.streak}
      footer={<Button title={t('onboarding.testStart')} loading={busy} onPress={submit} />}
    >
      <OnboardingProgress step={5} total={5} onBack={() => router.back()} />

      <Reveal from="top" distance={10} style={{ gap: space.xs, marginBottom: space.lg }}>
        <Text variant="title">{t('onboarding.testTitle')}</Text>
        <Text variant="bodySm" tone="muted">
          {t('onboarding.testSub')}
        </Text>
      </Reveal>

      <View style={{ gap: space.md }}>
        <Reveal index={1}>
        <Card accent={colors.accent}>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('onboarding.testPushup')}
          </Text>
          <Stepper value={pushup} onChange={setPushup} max={300} tint={colors.accent} />
        </Card>
        </Reveal>

        <Reveal index={2}>
        <Card accent={colors.rank}>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('onboarding.testPullup')}
          </Text>
          <Stepper value={pullup} onChange={setPullup} max={100} tint={colors.rank} />
        </Card>
        </Reveal>

        <Reveal index={3}>
        <Card accent={colors.info}>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('onboarding.testPlank')}
          </Text>
          <Stepper value={plank} onChange={setPlank} step={5} max={900} unit={t('common.sec')} tint={colors.info} />
        </Card>
        </Reveal>

        <Reveal index={4}>
          <Text variant="caption" tone="muted" style={{ marginTop: space.sm }}>
            {t('onboarding.disclaimer')}
          </Text>
        </Reveal>
      </View>
    </Screen>
  );
}
