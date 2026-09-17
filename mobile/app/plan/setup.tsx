import { useState } from 'react';
import { View, ScrollView, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Text, Card, Button, Chip, Tap, Glow, Grain, Reveal, Pop } from '@/components';
import { colors, gutter, radius, space, border } from '@/theme';
import { plan as planApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import { useAuth } from '@/store/auth';
import { usePremium } from '@/lib/premium';
import { LOCATIONS, LOCATION_ICON, WEEKDAYS, defaultLocation } from '@/features/plan/labels';
import type { PlanIntensity, PlanLocation } from '@/api/types';

const INTENSITIES: PlanIntensity[] = ['light', 'moderate', 'intense'];
const WEEK_OPTIONS = [4, 6, 8] as const;
/** სერვერის PlanGenerator::MAX_TRAINING_DAYS — კვირაში ≥ 1 დასვენება (სპეც. 18) */
const MAX_DAYS = 6;

/**
 * პლანერის კონფიგურაცია. premium-ის გარეშეც ბოლომდე კონფიგურირდება —
 * paywall „გენერაციის" ღილაკზე ჩნდება, როცა მომხმარებელი უკვე ხედავს,
 * რას იღებს.
 */
export default function PlanSetupScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const user = useAuth((s) => s.user);
  const { isPremium } = usePremium();

  const fallback = defaultLocation(user?.profile?.equipment);
  const [days, setDays] = useState<Record<number, PlanLocation>>({ 1: fallback, 3: fallback, 5: fallback });
  const [intensity, setIntensity] = useState<PlanIntensity>('moderate');
  const [weeks, setWeeks] = useState<number>(4);
  const [busy, setBusy] = useState(false);

  const selected = WEEKDAYS.filter((d) => days[d]);

  const toggleDay = (weekday: number) => {
    setDays((current) => {
      if (current[weekday]) {
        const { [weekday]: _removed, ...rest } = current;
        return rest;
      }
      if (Object.keys(current).length >= MAX_DAYS) return current;
      return { ...current, [weekday]: fallback };
    });
  };

  const generate = async () => {
    if (!isPremium) {
      router.push({ pathname: '/paywall', params: { next: '/plan/setup' } });
      return;
    }

    setBusy(true);
    try {
      const { plan } = await planApi.create({
        schedule: selected.map((weekday) => ({ weekday, location: days[weekday] })),
        intensity,
        weeks,
      });
      queryClient.setQueryData(['plan'], { plan });
      router.replace('/plan');
    } catch (error) {
      if (error instanceof ApiError && error.needsPremium) {
        router.push({ pathname: '/paywall', params: { next: '/plan/setup' } });
      } else {
        Alert.alert(t('plan.title'), t('common.error'));
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={colors.accent} size={440} opacity={0.2} style={{ top: -240, right: -120 }} />
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
          <Text variant="title">{t('plan.setupTitle')}</Text>
          <Text variant="bodySm" tone="muted">
            {t('plan.setupSub')}
          </Text>
        </Reveal>

        {/* ---- 1. დღეები ---- */}
        <Reveal index={2}>
          <Card>
            <Text variant="overline" tone="muted">
              1 · {t('plan.stepDays')}
            </Text>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: space.md }}>
              {WEEKDAYS.map((weekday) => {
                const on = Boolean(days[weekday]);
                return (
                  <Tap key={weekday} onPress={() => toggleDay(weekday)} haptic="light">
                    <Pop trigger={on}>
                      <View
                        style={{
                          width: 40,
                          height: 46,
                          borderRadius: radius.sm,
                          alignItems: 'center',
                          justifyContent: 'center',
                          backgroundColor: on ? colors.accent : colors.surfaceHi,
                          borderWidth: border.hair,
                          borderColor: on ? colors.accent : colors.border,
                        }}
                      >
                        <Text variant="label" style={{ color: on ? colors.onAccent : colors.textSecondary }}>
                          {t(`plan.wd${weekday}`)}
                        </Text>
                      </View>
                    </Pop>
                  </Tap>
                );
              })}
            </View>
            <Text variant="caption" tone="muted" style={{ marginTop: space.sm }}>
              {selected.length >= MAX_DAYS ? t('plan.restRequired') : t('plan.daysCount', { count: selected.length })}
            </Text>
          </Card>
        </Reveal>

        {/* ---- 2. ლოკაცია დღეების მიხედვით ---- */}
        <Reveal index={3}>
          <Card>
            <Text variant="overline" tone="muted">
              2 · {t('plan.stepLocation')}
            </Text>
            <View style={{ gap: space.md, marginTop: space.md }}>
              {selected.length === 0 ? (
                <Text variant="bodySm" tone="muted">
                  {t('plan.pickDaysFirst')}
                </Text>
              ) : (
                selected.map((weekday) => (
                  <View key={weekday} style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm }}>
                    <Text variant="label" style={{ width: 92 }} numberOfLines={1}>
                      {t(`plan.wdLong${weekday}`)}
                    </Text>
                    <View style={{ flex: 1, flexDirection: 'row', gap: space.xs, justifyContent: 'flex-end' }}>
                      {LOCATIONS.map((location) => (
                        <Chip
                          key={location}
                          compact
                          label={`${LOCATION_ICON[location]} ${t(`plan.loc_${location}`)}`}
                          selected={days[weekday] === location}
                          tint={location === 'gym' ? colors.rank : location === 'yard' ? colors.info : colors.accent}
                          onPress={() => setDays((current) => ({ ...current, [weekday]: location }))}
                        />
                      ))}
                    </View>
                  </View>
                ))
              )}
            </View>
          </Card>
        </Reveal>

        {/* ---- 3. ინტენსიობა ---- */}
        <Reveal index={4} style={{ gap: space.sm }}>
          <Text variant="overline" tone="muted">
            3 · {t('plan.stepIntensity')}
          </Text>
          {INTENSITIES.map((key) => {
            const on = intensity === key;
            const tint = key === 'intense' ? colors.streak : key === 'moderate' ? colors.accent : colors.info;
            return (
              <Tap key={key} onPress={() => setIntensity(key)} scaleTo={0.985} haptic="light">
                <View
                  style={{
                    padding: space.base,
                    borderRadius: radius.md,
                    borderWidth: on ? border.thin : border.hair,
                    borderColor: on ? tint : colors.border,
                    backgroundColor: on ? `${tint}14` : colors.surface,
                    gap: 2,
                  }}
                >
                  <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Text variant="subheading" style={on ? { color: tint } : undefined}>
                      {t(`plan.int_${key}`)}
                    </Text>
                    <Text variant="caption" tone="muted">
                      {t(`plan.int_${key}_volume`)}
                    </Text>
                  </View>
                  <Text variant="caption" tone="muted">
                    {t(`plan.int_${key}_hint`)}
                  </Text>
                </View>
              </Tap>
            );
          })}
        </Reveal>

        {/* ---- 4. ხანგრძლივობა ---- */}
        <Reveal index={5}>
          <Card>
            <Text variant="overline" tone="muted">
              4 · {t('plan.stepWeeks')}
            </Text>
            <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
              {WEEK_OPTIONS.map((value) => (
                <Chip
                  key={value}
                  label={t('plan.weeks', { count: value })}
                  selected={weeks === value}
                  onPress={() => setWeeks(value)}
                />
              ))}
            </View>
            <Text variant="caption" tone="muted" style={{ marginTop: space.sm }}>
              {t('plan.deloadHint')}
            </Text>
          </Card>
        </Reveal>

        <Reveal index={6} style={{ gap: space.sm }}>
          <Button
            title={isPremium ? t('plan.generate') : t('plan.generatePremium')}
            onPress={generate}
            loading={busy}
            disabled={selected.length === 0 || busy}
            haptic="success"
          />
          <Text variant="caption" tone="muted" center>
            {t('plan.disclaimer')}
          </Text>
        </Reveal>
      </ScrollView>
    </View>
  );
}
