import { useEffect, useMemo, useState } from 'react';
import { View, ScrollView, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Text, Card, Button, Chip, Tap, Glow, Grain, Reveal, Skeleton, Bar, Fade } from '@/components';
import { colors, gutter, radius, space, border, zoneTheme } from '@/theme';
import { plan as planApi, me as meApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { usePlayer, toPlayerExercises } from '@/store/player';
import { useAuth } from '@/store/auth';
import { usePremium } from '@/lib/premium';
import { LOCATION_ICON, WEEKDAYS, parseDay } from '@/features/plan/labels';
import type { PlanDay } from '@/api/types';

/**
 * კალენდარი — პლანის კვირები 7-სვეტიან ბადეში. დღეს ავტომატურად
 * მონიშნულია; ბადის ქვეშ მონიშნული დღის სავარჯიშოები და „დაწყება".
 */
export default function PlanScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const user = useAuth((s) => s.user);
  const startSession = usePlayer((s) => s.start);
  const { isPremium } = usePremium();

  const query = useQuery({
    queryKey: ['plan'],
    queryFn: () => planApi.get(),
    enabled: isPremium,
    retry: (count, error) => !(error instanceof ApiError && error.needsPremium) && count < 1,
  });
  const checkin = useQuery({ queryKey: ['checkin'], queryFn: () => meApi.checkin() });

  const plan = query.data?.plan ?? null;
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const weeks = useMemo(() => {
    if (!plan) return [];
    const rows: (PlanDay | null)[][] = [];
    // ბადე ორშაბათიდან იწყება — პირველი რიგის ცარიელი უჯრები starts_on-ამდე
    const lead = plan.days.length ? plan.days[0].weekday - 1 : 0;
    const cells: (PlanDay | null)[] = [...Array(lead).fill(null), ...plan.days];
    for (let i = 0; i < cells.length; i += 7) rows.push(cells.slice(i, i + 7));
    return rows;
  }, [plan]);

  useEffect(() => {
    if (!plan || selectedId) return;
    const today = plan.days.find((day) => day.date === plan.today) ?? plan.days[0];
    setSelectedId(today?.id ?? null);
  }, [plan, selectedId]);

  const selected = plan?.days.find((day) => day.id === selectedId) ?? null;

  const start = (day: PlanDay) => {
    const exercises = toPlayerExercises(day.exercises);
    if (exercises.length === 0) return;

    startSession({
      exercises,
      planDayId: day.id,
      source: 'plan',
      spotCheckinId: checkin.data?.checkin?.id ?? null,
      bodyweightKg: user?.profile?.weight_kg ?? 75,
    });
    router.push('/player');
  };

  const cancelPlan = () => {
    Alert.alert(t('plan.cancelTitle'), t('plan.cancelWarn'), [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('plan.cancelConfirm'),
        style: 'destructive',
        onPress: async () => {
          await planApi.cancel().catch(() => undefined);
          queryClient.setQueryData(['plan'], { plan: null });
        },
      },
    ]);
  };

  const needsPremium = !isPremium || (query.error instanceof ApiError && query.error.needsPremium);

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={colors.accent} size={460} opacity={0.22} style={{ top: -250, right: -120 }} />
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
        <Reveal from="top" distance={8} style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <Tap onPress={() => router.back()}>
            <Text variant="label" tone="muted">
              ← {t('common.back')}
            </Text>
          </Tap>
          {plan ? (
            <Tap onPress={() => router.push('/plan/setup')}>
              <Text variant="label" tone="accent">
                {t('plan.edit')}
              </Text>
            </Tap>
          ) : null}
        </Reveal>

        <Reveal index={1} style={{ gap: space.xs }}>
          <Text variant="title">{t('plan.title')}</Text>
          {plan ? (
            <Text variant="bodySm" tone="muted">
              {t(`plan.int_${plan.intensity}`)} · {t('plan.weeks', { count: plan.weeks })} ·{' '}
              {parseDay(plan.starts_on).toLocaleDateString()} – {parseDay(plan.ends_on).toLocaleDateString()}
            </Text>
          ) : null}
        </Reveal>

        {needsPremium ? (
          <Reveal index={2} zoom>
            <Card accent={colors.rank}>
              <Text variant="heading">{t('plan.lockedTitle')}</Text>
              <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
                {t('plan.lockedSub')}
              </Text>
              <View style={{ gap: space.sm, marginTop: space.base }}>
                <Button title={t('plan.tryConfigure')} onPress={() => router.push('/plan/setup')} />
                <Button
                  title={t('paywall.subscribe')}
                  variant="secondary"
                  onPress={() => router.push({ pathname: '/paywall', params: { next: '/plan/setup' } })}
                />
              </View>
            </Card>
          </Reveal>
        ) : query.isLoading ? (
          <Reveal index={2}>
            <Skeleton height={260} round={20} />
          </Reveal>
        ) : !plan ? (
          <Reveal index={2} zoom>
            <Card accent={colors.accent}>
              <Text variant="heading">{t('plan.emptyTitle')}</Text>
              <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
                {t('plan.emptySub')}
              </Text>
              <View style={{ marginTop: space.base }}>
                <Button title={t('plan.create')} onPress={() => router.push('/plan/setup')} />
              </View>
            </Card>
          </Reveal>
        ) : (
          <>
            {/* ---- პროგრესი ---- */}
            <Reveal index={2}>
              <Card>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' }}>
                  <Text variant="overline" tone="muted">
                    {t('plan.progress')}
                  </Text>
                  <Text variant="numeric" tone="accent">
                    {plan.stats.completed}/{plan.stats.workouts}
                  </Text>
                </View>
                <View style={{ marginTop: space.sm }}>
                  <Bar progress={plan.stats.workouts ? plan.stats.completed / plan.stats.workouts : 0} height={8} />
                </View>
              </Card>
            </Reveal>

            {/* ---- კალენდარის ბადე ---- */}
            <Reveal index={3}>
              <Card>
                <View style={{ flexDirection: 'row', marginBottom: space.sm }}>
                  {WEEKDAYS.map((weekday) => (
                    <Text key={weekday} variant="caption" tone="muted" center style={{ flex: 1 }}>
                      {t(`plan.wd${weekday}`)}
                    </Text>
                  ))}
                </View>

                <View style={{ gap: space.xs }}>
                  {weeks.map((row, rowIndex) => (
                    <View key={rowIndex} style={{ flexDirection: 'row', gap: space.xs }}>
                      {row.map((day, cellIndex) =>
                        day ? (
                          <DayCell
                            key={day.id}
                            day={day}
                            isToday={day.date === plan.today}
                            selected={day.id === selectedId}
                            onPress={() => setSelectedId(day.id)}
                          />
                        ) : (
                          <View key={`empty-${cellIndex}`} style={{ flex: 1 }} />
                        ),
                      )}
                    </View>
                  ))}
                </View>

                <View style={{ flexDirection: 'row', gap: space.base, marginTop: space.md, flexWrap: 'wrap' }}>
                  <Legend color={colors.accent} label={t('plan.legendWorkout')} />
                  <Legend color={colors.success} label={t('plan.legendDone')} />
                  <Legend color={colors.surfaceHi} label={t('plan.legendRest')} />
                </View>
              </Card>
            </Reveal>

            {/* ---- მონიშნული დღე ---- */}
            {selected ? (
              <Reveal index={4}>
                <Fade visible>
                  <SelectedDay day={selected} isToday={selected.date === plan.today} onStart={() => start(selected)} />
                </Fade>
              </Reveal>
            ) : null}

            <Reveal index={5}>
              <Button title={t('plan.cancel')} variant="ghost" onPress={cancelPlan} />
            </Reveal>
          </>
        )}
      </ScrollView>
    </View>
  );
}

function DayCell({
  day,
  isToday,
  selected,
  onPress,
}: {
  day: PlanDay;
  isToday: boolean;
  selected: boolean;
  onPress: () => void;
}) {
  const done = Boolean(day.completed_at);
  const workout = day.type === 'workout';
  const tint = done ? colors.success : workout ? colors.accent : colors.textDisabled;

  return (
    <Tap onPress={onPress} scaleTo={0.94} haptic="light" style={{ flex: 1 }}>
      <View
        style={{
          aspectRatio: 0.82,
          borderRadius: radius.sm,
          alignItems: 'center',
          justifyContent: 'center',
          gap: 2,
          backgroundColor: selected ? `${tint}22` : workout ? colors.surfaceHi : 'transparent',
          borderWidth: selected || isToday ? border.thin : border.hair,
          borderColor: selected ? tint : isToday ? colors.textSecondary : colors.border,
        }}
      >
        <Text variant="label" style={{ color: workout || isToday ? colors.text : colors.textMuted }}>
          {parseDay(day.date).getDate()}
        </Text>
        <Text variant="caption" style={{ color: tint, fontSize: 10, lineHeight: 12 }}>
          {done ? '✓' : workout && day.location ? LOCATION_ICON[day.location] : '·'}
        </Text>
      </View>
    </Tap>
  );
}

function Legend({ color, label }: { color: string; label: string }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.xs }}>
      <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: color }} />
      <Text variant="caption" tone="muted">
        {label}
      </Text>
    </View>
  );
}

function SelectedDay({ day, isToday, onStart }: { day: PlanDay; isToday: boolean; onStart: () => void }) {
  const { t } = useTranslation();
  const date = parseDay(day.date);

  if (day.type === 'rest') {
    return (
      <Card>
        <Text variant="overline" tone="muted">
          {t(`plan.wdLong${day.weekday}`)} · {date.toLocaleDateString()}
        </Text>
        <Text variant="heading" style={{ marginTop: space.xs }}>
          {t('home.restDay')}
        </Text>
        <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
          {t('plan.restHint')}
        </Text>
      </Card>
    );
  }

  return (
    <Card accent={day.completed_at ? colors.success : colors.accent}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
        <Text variant="overline" tone="accent">
          {t(`plan.wdLong${day.weekday}`)} · {date.toLocaleDateString()}
        </Text>
        {day.est_minutes ? (
          <Text variant="caption" tone="muted">
            ~{day.est_minutes} {t('common.min')}
          </Text>
        ) : null}
      </View>

      <Text variant="heading" style={{ marginTop: space.xs }}>
        {t(`plan.split_${day.split}`)} · {day.location ? `${LOCATION_ICON[day.location]} ${t(`plan.loc_${day.location}`)}` : ''}
      </Text>

      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.xs, marginTop: space.sm }}>
        {day.is_deload ? <Chip compact label={t('plan.deload')} selected tint={colors.info} /> : null}
        {day.focus.map((zone) => (
          <Chip key={zone} compact label={t(`library.zone_${zone}`)} tint={zoneTheme[zone]} selected={false} />
        ))}
      </View>

      <View style={{ gap: space.sm, marginTop: space.md }}>
        {day.exercises.map((item, index) => (
          <View key={`${item.exercise_id}-${index}`} style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
            <View
              style={{
                width: 4,
                height: 22,
                borderRadius: 2,
                backgroundColor: item.exercise?.zone ? zoneTheme[item.exercise.zone] : colors.border,
              }}
            />
            <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
              {item.exercise?.name ?? item.exercise?.slug}
            </Text>
            <Text variant="numeric" tone="secondary" style={{ fontSize: 14 }}>
              {item.sets}×{item.target_reps ?? `${item.target_seconds}${t('common.sec')}`}
            </Text>
          </View>
        ))}
      </View>

      <View style={{ marginTop: space.lg }}>
        {day.completed_at ? (
          <Text variant="label" tone="success" center>
            ✓ {t('plan.completed')}
          </Text>
        ) : (
          <Button title={isToday ? t('home.start') : t('plan.startAnyway')} onPress={onStart} />
        )}
      </View>
    </Card>
  );
}
