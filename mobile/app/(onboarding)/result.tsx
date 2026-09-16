import { useEffect } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { Screen, Text, Button, Card, Ring, Glow, Reveal, CountUp, Breathe } from '@/components';
import { colors, space } from '@/theme';
import { OnboardingProgress } from '@/features/onboarding';
import { useOnboardingDraft } from '@/features/onboarding/draft';
import { useAuth } from '@/store/auth';
import { programs as programsApi } from '@/api/endpoints';

export default function ResultScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const draft = useOnboardingDraft();
  const { completeOnboarding, refreshUser } = useAuth();

  const level = draft.level ?? 1;

  const { data: program } = useQuery({
    queryKey: ['program', draft.recommendedProgramId],
    queryFn: () => programsApi.get(draft.recommendedProgramId!),
    enabled: !!draft.recommendedProgramId,
  });

  useEffect(() => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
  }, []);

  const start = async () => {
    if (draft.recommendedProgramId) {
      await programsApi.enroll(draft.recommendedProgramId).catch(() => undefined);
    }
    await refreshUser().catch(() => undefined);
    await completeOnboarding();
    draft.reset();
    router.replace('/(tabs)');
  };

  return (
    <Screen
      scroll={false}
      ambient={colors.accent}
      footer={
        <View style={{ gap: space.sm }}>
          <Button title={t('onboarding.startProgram')} onPress={start} />
          <Button title={t('common.skip')} variant="ghost" size="sm" onPress={start} />
        </View>
      }
    >
      {/* ბოლო ნაბიჯი — ზოლი სრულად ივსება და ციკლი იკეტება */}
      <OnboardingProgress step={6} total={5} />

      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', gap: space.xl }}>
        <Breathe from={0.3} to={0.5} period={5200} style={{ position: 'absolute', top: 40 }}>
          <Glow color={colors.accent} size={340} opacity={1} />
        </Breathe>

        <Reveal from="top" distance={10}>
          <Text variant="overline" tone="muted">
            {t('onboarding.resultTitle')}
          </Text>
        </Reveal>

        {/* რგოლი ივსება და ციფრი 1-დან ითვლება — დონე „მოპოვებულია“ */}
        <Reveal index={1} zoom distance={0}>
          <Ring progress={level / 5} size={190} stroke={14}>
            <View style={{ alignItems: 'center' }}>
              <CountUp
                value={level}
                duration={900}
                delay={420}
                render={(shown) => (
                  <Text variant="hero" style={{ color: colors.accent }}>
                    {shown}
                  </Text>
                )}
              />
              <Text variant="caption" tone="muted">
                / 5
              </Text>
            </View>
          </Ring>
        </Reveal>

        {program ? (
          <Reveal index={2} zoom style={{ width: '100%' }}>
          <Card accent={colors.accent} style={{ width: '100%' }}>
            <Text variant="overline" tone="muted">
              {t('onboarding.resultSub')}
            </Text>
            <Text variant="heading" style={{ marginTop: space.sm }}>
              {program.data.title}
            </Text>
            <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
              {program.data.description}
            </Text>
            <Text variant="caption" tone="muted" style={{ marginTop: space.md }}>
              {program.data.duration_weeks} × {program.data.days_per_week} · {t('common.level')} {program.data.level}
            </Text>
          </Card>
          </Reveal>
        ) : null}
      </View>
    </Screen>
  );
}
