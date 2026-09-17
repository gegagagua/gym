import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Screen,
  Text,
  Card,
  Button,
  Bar,
  StatTile,
  SectionHeader,
  Tap,
  TierBadge,
  Reveal,
  CountUp,
  Skeleton,
  Pulse,
} from '@/components';
import { StreakCard } from '@/features/home/StreakCard';
import { MuscleMap } from '@/features/home/MuscleMap';
import { colors, radius, space, border, zoneTheme } from '@/theme';
import { me as meApi, programs as programsApi, plan as planApi } from '@/api/endpoints';
import { usePremium } from '@/lib/premium';
import { LOCATION_ICON } from '@/features/plan/labels';
import { formatXp } from '@/lib/xp';
import { pendingCount, flushQueue } from '@/db/sync';
import { usePlayer, toPlayerExercises } from '@/store/player';
import { useAuth } from '@/store/auth';

function greetingKey() {
  const hour = new Date().getHours();
  if (hour < 11) return 'home.greetingMorning';
  if (hour < 18) return 'home.greetingDay';
  return 'home.greetingEvening';
}

export default function TodayScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();
  const user = useAuth((s) => s.user);
  const startSession = usePlayer((s) => s.start);
  const [refreshing, setRefreshing] = useState(false);

  const stats = useQuery({ queryKey: ['stats', 'week'], queryFn: () => meApi.stats('week') });
  const today = useQuery({ queryKey: ['program', 'current'], queryFn: () => programsApi.current() });
  const pending = useQuery({ queryKey: ['pending'], queryFn: pendingCount, refetchInterval: 30_000 });
  const checkin = useQuery({ queryKey: ['checkin'], queryFn: () => meApi.checkin() });
  const { isPremium } = usePremium();
  // პლანერი premium-ია — უ-premium-ოდ 402-ს ნუ ვიწვევთ
  const calendar = useQuery({ queryKey: ['plan'], queryFn: () => planApi.get(), enabled: isPremium });
  const planToday = calendar.data?.plan?.days.find((day) => day.date === calendar.data?.plan?.today) ?? null;

  const refresh = useCallback(async () => {
    setRefreshing(true);
    await flushQueue().catch(() => undefined);
    await queryClient.invalidateQueries();
    setRefreshing(false);
  }, [queryClient]);

  const plan = today.data?.today;
  const isRest = plan?.type === 'rest';
  const xpToday = stats.data?.xp_today ?? 0;
  const cap = stats.data?.daily_cap ?? 1800;

  const beginWorkout = () => {
    const exercises = toPlayerExercises(plan?.exercises ?? []);
    if (exercises.length === 0) return;

    startSession({
      exercises,
      programDayId: plan?.id ?? null,
      spotCheckinId: checkin.data?.checkin?.id ?? null,
      source: plan?.type === 'test' ? 'test' : 'program',
      bodyweightKg: user?.profile?.weight_kg ?? 75,
    });

    router.push('/player');
  };

  const beginPlanDay = () => {
    const exercises = toPlayerExercises(planToday?.exercises ?? []);
    if (!planToday || exercises.length === 0) return;

    startSession({
      exercises,
      planDayId: planToday.id,
      source: 'plan',
      spotCheckinId: checkin.data?.checkin?.id ?? null,
      bodyweightKg: user?.profile?.weight_kg ?? 75,
    });

    router.push('/player');
  };

  return (
    <Screen ambient={colors.accent} refreshing={refreshing} onRefresh={refresh}>
      <Reveal from="top" distance={10} style={{ gap: space.xs, marginBottom: space.lg }}>
        <Text variant="overline" tone="muted">
          {t(greetingKey())}
        </Text>
        <Text variant="title">{user?.display_name ?? user?.username ?? 'Athlete'}</Text>
      </Reveal>

      {/* ---- დღიური XP + ჭერი ---- */}
      <Reveal index={1} zoom>
      <Card accent={colors.accent}>
        <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between' }}>
          <View>
            <Text variant="overline" tone="muted">
              {t('home.xpToday')}
            </Text>
            <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: space.xs }}>
              {stats.isLoading ? (
                <Skeleton height={38} width={110} />
              ) : (
                <CountUp
                  value={xpToday}
                  delay={220}
                  render={(shown) => (
                    <Text variant="display" style={{ color: colors.accent }}>
                      {formatXp(shown)}
                    </Text>
                  )}
                />
              )}
              <Text variant="label" tone="muted">
                / {cap}
              </Text>
            </View>
          </View>

          {/* აქტიური check-in ცოცხალი მდგომარეობაა — ნიშანი სუნთქავს */}
          {checkin.data?.checkin ? (
            <Pulse to={1.05} period={2400}>
              <TierBadge tier={2} />
            </Pulse>
          ) : null}
        </View>

        <View style={{ marginTop: space.base }}>
          <Bar progress={xpToday / cap} height={10} />
        </View>

        <Text variant="caption" tone="muted" style={{ marginTop: space.sm }}>
          {t('home.xpCap', { cap })}
        </Text>
      </Card>
      </Reveal>

      {/* ---- დღევანდელი ვარჯიში ---- */}
      <Reveal index={2}>
        <SectionHeader title={isRest ? t('home.restDay') : t('home.todayWorkout')} />
      </Reveal>

      {today.isLoading ? (
        <Skeleton height={196} round={20} />
      ) : isRest ? (
        <Reveal index={3} zoom>
        <Card>
          <Text variant="heading">{t('home.restDay')}</Text>
          <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
            {t('home.restDayHint')}
          </Text>
          <View style={{ marginTop: space.base }}>
            <Button title={t('home.freestyle')} variant="secondary" onPress={() => router.push('/(tabs)/library')} />
          </View>
        </Card>
        </Reveal>
      ) : plan ? (
        <Reveal index={3} zoom>
        <Card accent={colors.accent}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Text variant="overline" tone="accent">
              W{plan.week_no} · D{plan.day_no}
            </Text>
            {plan.est_minutes ? (
              <Text variant="caption" tone="muted">
                ~{plan.est_minutes} {t('common.min')}
              </Text>
            ) : null}
          </View>

          <View style={{ gap: space.sm, marginTop: space.md }}>
            {plan.exercises.map((item, index) => (
              <Reveal
                key={`${item.exercise_id}-${index}`}
                index={4 + index}
                from="left"
                distance={10}
                style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}
              >
                <View
                  style={{
                    width: 26,
                    height: 26,
                    borderRadius: radius.xs,
                    alignItems: 'center',
                    justifyContent: 'center',
                    backgroundColor: colors.surfaceHi,
                    borderWidth: border.hair,
                    borderColor: colors.border,
                  }}
                >
                  <Text variant="caption" tone="muted">
                    {index + 1}
                  </Text>
                </View>

                <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
                  {item.exercise?.name ?? item.exercise?.slug}
                </Text>

                <Text variant="numeric" tone="secondary" style={{ fontSize: 14 }}>
                  {item.sets}×{item.target_reps ?? `${item.target_seconds}${t('common.sec')}`}
                </Text>
              </Reveal>
            ))}
          </View>

          <View style={{ marginTop: space.lg }}>
            <Button title={t('home.start')} onPress={beginWorkout} />
          </View>
        </Card>
        </Reveal>
      ) : (
        /* აქტიური პროგრამა არ არის — არჩევა მთავარი ქმედებაა, თავისუფალი ვარჯიში სათადარიგო */
        <Reveal index={3} zoom>
        <Card accent={colors.accent}>
          <Text variant="heading">{t('programs.choose')}</Text>
          <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
            {t('programs.sub')}
          </Text>

          <View style={{ gap: space.sm, marginTop: space.base }}>
            <Button title={t('programs.title')} onPress={() => router.push('/programs')} />
            <Button
              title={t('home.freestyle')}
              variant="secondary"
              onPress={() => router.push('/(tabs)/library')}
            />
          </View>
        </Card>
        </Reveal>
      )}

      {/* ---- სინქის რიგი ---- */}
      {(pending.data ?? 0) > 0 ? (
        <Tap onPress={refresh} style={{ marginTop: space.md }}>
          <Card style={{ borderColor: `${colors.warning}44` }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm }}>
              <Pulse to={1.35} period={1400}>
                <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: colors.warning }} />
              </Pulse>
              <Text variant="bodySm" style={{ color: colors.warning }}>
                {t('home.pendingSync', { count: pending.data })}
              </Text>
            </View>
          </Card>
        </Tap>
      ) : null}

      {/* ---- კალენდარი (premium) ---- */}
      <Reveal index={5}>
        <SectionHeader
          title={t('plan.title')}
          action={
            <Tap onPress={() => router.push('/plan')} hitSlop={10}>
              <Text variant="label" tone="accent">
                {t('plan.open')} →
              </Text>
            </Tap>
          }
        />
      </Reveal>

      <Reveal index={6}>
        {isPremium && calendar.isLoading ? (
          <Skeleton height={120} round={20} />
        ) : isPremium && calendar.data?.plan && planToday ? (
          <Card accent={planToday.completed_at ? colors.success : colors.accent}>
            <Text variant="overline" tone="muted">
              {t('plan.todayByPlan')}
            </Text>
            {planToday.type === 'rest' ? (
              <Text variant="heading" style={{ marginTop: space.xs }}>
                {t('home.restDay')}
              </Text>
            ) : (
              <>
                <Text variant="heading" style={{ marginTop: space.xs }}>
                  {t(`plan.split_${planToday.split}`)}
                  {planToday.location ? ` · ${LOCATION_ICON[planToday.location]} ${t(`plan.loc_${planToday.location}`)}` : ''}
                </Text>
                <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.xs, flexWrap: 'wrap' }}>
                  {planToday.focus.map((zone) => (
                    <Text key={zone} variant="caption" style={{ color: zoneTheme[zone] }}>
                      ● {t(`library.zone_${zone}`)}
                    </Text>
                  ))}
                  {planToday.est_minutes ? (
                    <Text variant="caption" tone="muted">
                      ~{planToday.est_minutes} {t('common.min')}
                    </Text>
                  ) : null}
                </View>
                <View style={{ marginTop: space.base }}>
                  {planToday.completed_at ? (
                    <Text variant="label" tone="success">
                      ✓ {t('plan.completed')}
                    </Text>
                  ) : (
                    <Button title={t('home.start')} onPress={beginPlanDay} />
                  )}
                </View>
              </>
            )}
          </Card>
        ) : (
          <Tap onPress={() => router.push(isPremium ? '/plan/setup' : '/plan')} scaleTo={0.985}>
            <Card accent={colors.rank}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text variant="subheading">{isPremium ? t('plan.emptyTitle') : t('plan.teaserTitle')}</Text>
                  <Text variant="caption" tone="muted">
                    {isPremium ? t('plan.emptySub') : t('plan.teaserSub')}
                  </Text>
                </View>
                {isPremium ? null : (
                  <View
                    style={{
                      paddingHorizontal: space.sm,
                      paddingVertical: 3,
                      borderRadius: radius.xs,
                      backgroundColor: `${colors.rank}26`,
                    }}
                  >
                    <Text variant="caption" tone="rank">
                      Premium
                    </Text>
                  </View>
                )}
              </View>
            </Card>
          </Tap>
        )}
      </Reveal>

      {/* ---- კეგელი ---- */}
      <Reveal index={7}>
        <Tap onPress={() => router.push('/kegel')} scaleTo={0.985} style={{ marginTop: space.md }}>
          <Card accent={zoneTheme.pelvic_floor}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
              <View style={{ flex: 1, gap: 2 }}>
                <Text variant="subheading">{t('kegel.title')}</Text>
                <Text variant="caption" tone="muted">
                  {t('kegel.bannerHint')}
                </Text>
              </View>
              <Text variant="heading" style={{ color: zoneTheme.pelvic_floor }}>
                ▶
              </Text>
            </View>
          </Card>
        </Tap>
      </Reveal>

      {/* ---- Streak ---- */}
      <Reveal index={8}>
        <SectionHeader title={t('home.weekProgress')} />
      </Reveal>

      <Reveal index={9}>
        <StreakCard
          days={stats.data?.streak_days ?? 0}
          longest={stats.data?.longest_streak ?? 0}
          coveredToday={xpToday > 0}
        />
      </Reveal>

      <Reveal index={10}>
        <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
          <StatTile label={t('profile.sessions')} value={stats.data?.sessions ?? 0} />
          <StatTile label={t('player.duration')} value={stats.data?.minutes ?? 0} unit={t('common.min')} />
          <StatTile
            label={t('common.reps')}
            value={stats.data?.reps ?? 0}
            tint={colors.accent}
          />
        </View>
      </Reveal>

      {/* ---- კუნთების რუკა ---- */}
      {stats.data?.muscle_load && Object.keys(stats.data.muscle_load).length > 0 ? (
        <Reveal index={11}>
          <SectionHeader title={t('home.muscleMap')} />
          <Card>
            <MuscleMap load={stats.data.muscle_load} />
          </Card>
        </Reveal>
      ) : null}
    </Screen>
  );
}
