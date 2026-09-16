import { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Screen, Text, Button, Reveal } from '@/components';
import { Option, OnboardingProgress } from '@/features/onboarding';
import { colors, space } from '@/theme';
import { useOnboardingDraft } from '@/features/onboarding/draft';

const GOALS = ['strength', 'muscle', 'weight_loss', 'skills', 'health'] as const;

const TINTS: Record<(typeof GOALS)[number], string> = {
  strength: colors.accent,
  muscle: colors.rank,
  weight_loss: colors.streak,
  skills: colors.info,
  health: colors.success,
};

export default function GoalScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const draft = useOnboardingDraft();
  const [goal, setGoal] = useState(draft.goal);

  return (
    <Screen
      ambient={goal ? TINTS[goal as (typeof GOALS)[number]] : colors.accent}
      footer={
        <Button
          title={t('common.continue')}
          disabled={!goal}
          onPress={() => {
            draft.set({ goal });
            router.push('/(onboarding)/equipment');
          }}
        />
      }
    >
      <OnboardingProgress step={2} total={5} onBack={() => router.back()} />

      <Reveal from="top" distance={10} style={{ gap: space.xs, marginBottom: space.xl }}>
        <Text variant="title">{t('onboarding.goalTitle')}</Text>
        <Text variant="bodySm" tone="muted">
          {t('onboarding.goalSub')}
        </Text>
      </Reveal>

      <View style={{ gap: space.sm }}>
        {GOALS.map((value, index) => (
          <Reveal key={value} index={index + 1}>
            <Option
              title={t(`onboarding.goal_${value}`)}
              description={t(`onboarding.goal_${value}_desc`)}
              selected={goal === value}
              tint={TINTS[value]}
              onPress={() => setGoal(value)}
            />
          </Reveal>
        ))}
      </View>
    </Screen>
  );
}
