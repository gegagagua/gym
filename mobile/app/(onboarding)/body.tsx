import { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Screen, Text, Button, Card, Chip, Reveal } from '@/components';
import { OnboardingProgress, Stepper } from '@/features/onboarding';
import { colors, space } from '@/theme';
import { useOnboardingDraft } from '@/features/onboarding/draft';

const CURRENT_YEAR = new Date().getFullYear();

export default function BodyScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const draft = useOnboardingDraft();

  const [age, setAge] = useState(draft.birthYear ? CURRENT_YEAR - draft.birthYear : 24);
  const [gender, setGender] = useState(draft.gender);
  const [height, setHeight] = useState(draft.heightCm ?? 178);
  const [weight, setWeight] = useState(draft.weightKg ?? 75);

  const next = (skip: boolean) => {
    draft.set(
      skip
        ? {}
        : {
            birthYear: CURRENT_YEAR - age,
            gender,
            heightCm: height,
            weightKg: weight,
          },
    );
    router.push('/(onboarding)/test');
  };

  return (
    <Screen
      ambient={colors.rank}
      footer={
        <View style={{ gap: space.sm }}>
          <Button title={t('common.continue')} onPress={() => next(false)} />
          <Button title={t('common.skip')} variant="ghost" size="sm" onPress={() => next(true)} />
        </View>
      }
    >
      <OnboardingProgress step={4} total={5} onBack={() => router.back()} />

      <Reveal from="top" distance={10} style={{ gap: space.xs, marginBottom: space.xl }}>
        <Text variant="title">{t('onboarding.bodyTitle')}</Text>
        <Text variant="bodySm" tone="muted">
          {t('onboarding.bodySub')}
        </Text>
      </Reveal>

      <View style={{ gap: space.md }}>
        <Reveal index={1}>
        <Card>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('common.level')} · {age}
          </Text>
          <Stepper value={age} onChange={setAge} min={10} max={90} tint={colors.rank} />
        </Card>
        </Reveal>

        <Reveal index={2}>
        <Card>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('onboarding.gender')}
          </Text>
          <View style={{ flexDirection: 'row', gap: space.sm }}>
            {(['male', 'female', 'other'] as const).map((value) => (
              <Chip
                key={value}
                label={t(`onboarding.gender_${value}`)}
                selected={gender === value}
                tint={colors.rank}
                onPress={() => setGender(value)}
              />
            ))}
          </View>
        </Card>
        </Reveal>

        <Reveal index={3}>
        <Card>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('onboarding.height')}
          </Text>
          <Stepper value={height} onChange={setHeight} min={120} max={230} unit={t('common.cm')} />
        </Card>
        </Reveal>

        <Reveal index={4}>
        <Card accent={colors.accent}>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('onboarding.weight')}
          </Text>
          <Stepper value={weight} onChange={setWeight} min={30} max={200} unit={t('common.kg')} />
          {/* წონა XP-ის weight_mod-ისთვისაა — ვხსნით, თორემ ველი ინტიმურია */}
          <Text variant="caption" tone="muted" style={{ marginTop: space.sm }}>
            {t('profile.private')}
          </Text>
        </Card>
        </Reveal>
      </View>
    </Screen>
  );
}
