import { useEffect, useRef, useState } from 'react';
import { View, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSequence,
  withTiming,
} from 'react-native-reanimated';
import { Text, Button, Bar, Tap, Glow, Grain, MediaLoop, Reveal, CountUp, Pop } from '@/components';
import { RepPad } from '@/features/player/RepPad';
import { HoldTimer } from '@/features/player/HoldTimer';
import { RestScreen } from '@/features/player/RestScreen';
import { ReadyScreen } from '@/features/player/ReadyScreen';
import { colors, duration as motionDuration, easing, forceTheme, gutter, radius, space } from '@/theme';
import { usePlayer } from '@/store/player';
import { useSettings } from '@/store/settings';
import { formatXp } from '@/lib/xp';

export default function PlayerScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const hapticCues = useSettings((s) => s.hapticCues);

  const {
    exercises,
    index,
    setNo,
    phase,
    restRemaining,
    logged,
    beginWork,
    logSet,
    tickRest,
    skipRest,
    reportPain,
    endEarly,
    estimatedXp,
    totalSets,
    completedSets,
  } = usePlayer();

  const exercise = exercises[index] ?? null;
  const [value, setValue] = useState(exercise?.targetReps ?? 10);

  // სეტის ჩაწერა — მოკლე ალი ეკრანის თავზე და XP-ის მოგებული ულუფა
  const flash = useSharedValue(0);
  const [gained, setGained] = useState<{ xp: number; at: number } | null>(null);
  const lastLogged = useRef(0);

  useEffect(() => {
    if (logged.length <= lastLogged.current) {
      lastLogged.current = logged.length;
      return;
    }

    lastLogged.current = logged.length;
    const entry = logged[logged.length - 1];

    setGained({ xp: Math.round(entry.estimatedXp), at: Date.now() });
    flash.value = withSequence(
      withTiming(1, { duration: motionDuration.instant, easing: easing.out }),
      withTiming(0, { duration: motionDuration.lazy, easing: easing.in }),
    );
  }, [logged, flash]);

  const flashStyle = useAnimatedStyle(() => ({ opacity: flash.value * 0.6 }));

  useEffect(() => {
    if (!exercise) return;
    setValue(exercise.unit === 'reps' ? (exercise.targetReps ?? 10) : (exercise.targetSeconds ?? 30));
  }, [exercise?.exerciseId, setNo, exercise]);

  useEffect(() => {
    if (phase === 'summary') router.replace('/summary');
  }, [phase, router]);

  if (!exercise) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, alignItems: 'center', justifyContent: 'center' }}>
        <Button title={t('common.close')} variant="ghost" full={false} onPress={() => router.back()} />
      </View>
    );
  }

  const tint = forceTheme[exercise.force as keyof typeof forceTheme] ?? colors.accent;
  const progress = totalSets() > 0 ? completedSets() / totalSets() : 0;

  const confirmPain = () =>
    Alert.alert(t('player.painTitle'), t('player.painBody'), [
      { text: t('common.cancel'), style: 'cancel' },
      { text: t('player.endSession'), style: 'destructive', onPress: reportPain },
    ]);

  if (phase === 'ready') {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, paddingTop: insets.top, paddingBottom: insets.bottom }}>
        <Grain />
        <ReadyScreen exercise={exercise} tint={tint} onDone={beginWork} />
      </View>
    );
  }

  if (phase === 'rest') {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg, paddingTop: insets.top, paddingBottom: insets.bottom }}>
        <Grain />
        <RestScreen
          remaining={restRemaining}
          total={exercises[index]?.restSeconds ?? 60}
          nextName={exercise.name}
          nextSet={setNo}
          nextTotal={exercise.sets}
          onSkip={skipRest}
          onTick={tickRest}
          hapticCues={hapticCues}
        />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={tint} size={480} opacity={0.26} style={{ top: -270, left: -120 }} />
      <Grain />

      {/* სეტის ჩაწერის ალი — ერთი კადრით დასტური, ტექსტის გარეშე */}
      <Animated.View pointerEvents="none" style={[{ position: 'absolute', top: 0, left: 0, right: 0 }, flashStyle]}>
        <Glow color={colors.accent} size={520} opacity={1} style={{ top: -320, left: -100 }} />
      </Animated.View>

      {/* ---- ზედა ზოლი: პროგრესი და XP ---- */}
      <View style={{ paddingTop: insets.top + space.sm, paddingHorizontal: gutter, gap: space.sm }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Tap onPress={() => router.back()} hitSlop={16}>
            <Text variant="label" tone="muted">
              ✕
            </Text>
          </Tap>

          <Text variant="overline" tone="muted">
            {index + 1} / {exercises.length}
          </Text>

          <View style={{ alignItems: 'flex-end' }}>
            <Pop trigger={logged.length}>
              <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 3 }}>
                <CountUp
                  value={estimatedXp()}
                  duration={520}
                  render={(shown) => (
                    <Text variant="numeric" style={{ color: colors.accent }}>
                      {formatXp(shown)}
                    </Text>
                  )}
                />
                <Text variant="caption" tone="muted">
                  XP
                </Text>
              </View>
            </Pop>

            {gained ? <GainedXp key={gained.at} xp={gained.xp} /> : null}
          </View>
        </View>

        <Bar progress={progress} height={4} />
      </View>

      {/* ---- ლუპი ---- */}
      <View style={{ paddingHorizontal: gutter, marginTop: space.base }}>
        <Reveal key={`loop-${exercise.exerciseId}`} zoom distance={10}>
          <MediaLoop
            source={exercise.loop}
            tint={tint}
            aspectRatio={16 / 10}
            initials={exercise.name}
            emptyLabel={t('library.noLoop')}
          />
        </Reveal>
      </View>

      {/* ---- სავარჯიშო + სეტი ---- */}
      <Reveal key={`name-${exercise.exerciseId}`} index={1} style={{ paddingHorizontal: gutter, marginTop: space.lg, gap: space.xs }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Text variant="title" style={{ flex: 1 }} numberOfLines={1}>
            {exercise.name}
          </Text>

          {exercise.tempo === 'slow' ? (
            <View
              style={{
                paddingHorizontal: space.sm,
                paddingVertical: 3,
                borderRadius: radius.xs,
                backgroundColor: `${colors.rank}1F`,
              }}
            >
              <Text variant="caption" style={{ color: colors.rank }}>
                3-1-3 ×1.2
              </Text>
            </View>
          ) : null}
        </View>

        <Text variant="label" tone="muted">
          {t('player.set', { current: setNo, total: exercise.sets })}
        </Text>
      </Reveal>

      {/* ---- შეყვანა ---- */}
      <View style={{ flex: 1, justifyContent: 'center', paddingHorizontal: gutter }}>
        {exercise.unit === 'seconds' ? (
          <HoldTimer
            target={exercise.targetSeconds ?? 30}
            onDone={(seconds) => logSet({ seconds })}
          />
        ) : (
          <RepPad value={value} target={exercise.targetReps} unit={exercise.unit} onChange={setValue} />
        )}
      </View>

      {/* ---- ქმედებები ---- */}
      <View
        style={{
          paddingHorizontal: gutter,
          paddingBottom: insets.bottom + space.md,
          gap: space.sm,
        }}
      >
        {exercise.unit === 'reps' ? (
          <Reveal index={2}>
            <Button title={t('player.logSet')} onPress={() => logSet({ reps: value })} haptic="success" />
          </Reveal>
        ) : null}

        <View style={{ flexDirection: 'row', gap: space.sm }}>
          <Button
            title={t('player.pain')}
            variant="danger"
            size="sm"
            style={{ flex: 1 }}
            onPress={confirmPain}
          />
          <Button
            title={t('player.finish')}
            variant="secondary"
            size="sm"
            style={{ flex: 1 }}
            onPress={() => (logged.length > 0 ? endEarly() : router.back())}
          />
        </View>
      </View>
    </View>
  );
}

/** ჩაწერილი სეტის XP — ერთხელ ამოცურდება და ქრება */
function GainedXp({ xp }: { xp: number }) {
  const rise = useSharedValue(0);

  useEffect(() => {
    rise.value = withTiming(1, { duration: 1100, easing: easing.out });
  }, [rise]);

  const animated = useAnimatedStyle(() => ({
    opacity: rise.value < 0.2 ? rise.value * 5 : 1 - (rise.value - 0.2) / 0.8,
    transform: [{ translateY: -18 * rise.value }],
  }));

  return (
    <Animated.View pointerEvents="none" style={[{ position: 'absolute', top: 16, right: 0 }, animated]}>
      <Text variant="caption" style={{ color: colors.accent }}>
        +{xp}
      </Text>
    </Animated.View>
  );
}
