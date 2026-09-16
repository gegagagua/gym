import { useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Screen, Text, Button, Reveal } from '@/components';
import { Option, OnboardingProgress } from '@/features/onboarding';
import { colors, space } from '@/theme';
import { useOnboardingDraft } from '@/features/onboarding/draft';

const EQUIPMENT = ['none', 'bar', 'yard', 'gym'] as const;

export default function EquipmentScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const draft = useOnboardingDraft();
  const [selected, setSelected] = useState<string[]>(draft.equipment);

  const toggle = (value: string) =>
    setSelected((current) =>
      current.includes(value) ? current.filter((v) => v !== value) : [...current, value],
    );

  return (
    <Screen
      ambient={colors.info}
      footer={
        <Button
          title={t('common.continue')}
          disabled={selected.length === 0}
          onPress={() => {
            draft.set({ equipment: selected });
            router.push('/(onboarding)/body');
          }}
        />
      }
    >
      <OnboardingProgress step={3} total={5} onBack={() => router.back()} />

      <Reveal from="top" distance={10} style={{ gap: space.xs, marginBottom: space.xl }}>
        <Text variant="title">{t('onboarding.equipmentTitle')}</Text>
        <Text variant="bodySm" tone="muted">
          {t('onboarding.equipmentSub')}
        </Text>
      </Reveal>

      <View style={{ gap: space.sm }}>
        {EQUIPMENT.map((value, index) => (
          <Reveal key={value} index={index + 1}>
            <Option
              title={t(`onboarding.eq_${value}`)}
              selected={selected.includes(value)}
              tint={colors.info}
              onPress={() => toggle(value)}
            />
          </Reveal>
        ))}
      </View>
    </Screen>
  );
}
