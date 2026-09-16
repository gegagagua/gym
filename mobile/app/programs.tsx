import { useState } from 'react';
import { View, Alert, ScrollView } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Text, Card, Button, Chip, Tap, Glow, Grain, Reveal, Skeleton } from '@/components';
import { colors, gutter, space } from '@/theme';
import { programs as programsApi } from '@/api/endpoints';
import type { Program } from '@/api/types';

const TRACKS = ['home', 'bar', 'skills'] as const;

/**
 * პროგრამების კატალოგი.
 *
 * ონბორდინგზე რეკომენდებული პროგრამა თავისით ერთვება, მაგრამ „გამოტოვება“
 * და პროგრამის დასრულება მომხმარებელს უპროგრამოდ ტოვებდა — გადართვის
 * ერთადერთი გზა ეს ეკრანია.
 */
export default function ProgramsScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const [track, setTrack] = useState<(typeof TRACKS)[number] | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const list = useQuery({
    queryKey: ['programs', track],
    queryFn: () => programsApi.list(track ? { track } : {}),
    staleTime: 10 * 60_000,
  });

  const current = useQuery({ queryKey: ['program', 'current'], queryFn: () => programsApi.current() });
  const activeId = current.data?.enrollment?.program_id ?? null;

  const enroll = async (program: Program) => {
    setBusyId(program.id);

    try {
      await programsApi.enroll(program.id);
      await queryClient.invalidateQueries({ queryKey: ['program'] });
      router.replace('/(tabs)');
    } catch {
      Alert.alert(t('programs.title'), t('common.error'));
    } finally {
      setBusyId(null);
    }
  };

  // მიმდინარე პროგრამის შეცვლა პროგრესს ნულავს — ეს გაფრთხილების ღირსია
  const confirm = (program: Program) => {
    if (!activeId || activeId === program.id) return enroll(program);

    Alert.alert(t('programs.replaceTitle'), t('programs.replaceWarn'), [
      { text: t('common.cancel'), style: 'cancel' },
      { text: t('programs.enroll'), style: 'destructive', onPress: () => enroll(program) },
    ]);
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={colors.accent} size={460} opacity={0.24} style={{ top: -250, right: -120 }} />
      <Grain />

      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + space.sm,
          paddingBottom: insets.bottom + space.huge,
          paddingHorizontal: gutter,
          gap: space.base,
        }}
        showsVerticalScrollIndicator={false}
      >
        <Reveal from="top" distance={8} style={{ alignSelf: 'flex-start' }}>
          <Tap onPress={() => router.back()}>
            <Text variant="label" tone="muted">
              ← {t('common.back')}
            </Text>
          </Tap>
        </Reveal>

        <Reveal index={1} style={{ gap: space.xs }}>
          <Text variant="title">{t('programs.title')}</Text>
          <Text variant="bodySm" tone="muted">
            {t('programs.sub')}
          </Text>
        </Reveal>

        <Reveal index={2} style={{ flexDirection: 'row', gap: space.sm, flexWrap: 'wrap' }}>
          <Chip label={t('common.all')} selected={track === null} onPress={() => setTrack(null)} compact />
          {TRACKS.map((key) => (
            <Chip
              key={key}
              label={t(`programs.track_${key}`)}
              selected={track === key}
              onPress={() => setTrack(key)}
              compact
            />
          ))}
        </Reveal>

        {list.isLoading ? (
          <View style={{ gap: space.base }}>
            {[0, 1, 2].map((index) => (
              <Reveal key={index} index={3 + index}>
                <Skeleton height={190} round={20} />
              </Reveal>
            ))}
          </View>
        ) : null}

        {list.data?.data.length === 0 && !list.isLoading ? (
          <Text variant="bodySm" tone="muted" center style={{ marginTop: space.xxl }}>
            {t('library.empty')}
          </Text>
        ) : null}

        {list.data?.data.map((program, index) => {
          const active = activeId === program.id;

          return (
            <Reveal key={program.id} index={3 + Math.min(index, 8)} zoom>
            <Card accent={active ? colors.accent : undefined}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm }}>
                <Text variant="heading" style={{ flex: 1 }}>
                  {program.title}
                </Text>
                {active ? (
                  <Text variant="caption" style={{ color: colors.accent }}>
                    {t('programs.active')}
                  </Text>
                ) : null}
              </View>

              {program.description ? (
                <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
                  {program.description}
                </Text>
              ) : null}

              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm, marginTop: space.md }}>
                <Chip label={t(`programs.track_${program.track}`)} compact />
                <Chip label={`${t('common.level')} ${program.level}`} compact />
                <Chip
                  label={t('programs.schedule', {
                    weeks: program.duration_weeks,
                    days: program.days_per_week,
                  })}
                  compact
                />
                {program.equipment.map((tag) => (
                  <Chip key={tag} label={t(`map.eq_${tag}`, { defaultValue: tag })} compact />
                ))}
              </View>

              <View style={{ marginTop: space.base }}>
                <Button
                  title={active ? t('programs.continue') : t('programs.enroll')}
                  variant={active ? 'secondary' : 'primary'}
                  loading={busyId === program.id}
                  onPress={() => (active ? router.replace('/(tabs)') : confirm(program))}
                />
              </View>
            </Card>
            </Reveal>
          );
        })}
      </ScrollView>
    </View>
  );
}
