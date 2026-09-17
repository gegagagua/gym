import { useEffect, useMemo, useRef, useState } from 'react';
import { View, ScrollView, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { Text, Card, Button, Tap, Glow, Grain, Reveal, Ring, Squeeze, Bar, MediaLoop, Skeleton, Fade } from '@/components';
import { colors, gutter, radius, space, border, zoneTheme } from '@/theme';
import { exercises as exercisesApi } from '@/api/endpoints';
import { resolveLoop } from '@/lib/media';
import { estimateSetXp } from '@/lib/xp';
import { usePlayer, type LoggedSet, type PlayerExercise } from '@/store/player';
import { PelvisDiagram } from '@/features/kegel/PelvisDiagram';
import {
  ROUTINES,
  buildTimeline,
  contractedSeconds,
  routineMinutes,
  type Routine,
} from '@/features/kegel/routines';
import type { Exercise } from '@/api/types';

const TINT = zoneTheme.pelvic_floor;
const TICK_MS = 100;

/**
 * კეგელის გაიდ-პლეიერი. ვიდეოს ნაცვლად რიტმი: წრე და სქემა „შეკუმშვის"
 * სამიზნეს მიყვება, ფაზის ცვლაზე ვიბრაცია — ეკრანზე ყურება არ ჭირდება.
 * თუ სავარჯიშოს საკუთარი ვიდეო ჩაემატება (exercise_media), ის ჩნდება
 * სქემის ადგილას. „ტკივილი" სესიას მაშინვე ჩერავს (სპეც. 18).
 */
export default function KegelScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const completeGuided = usePlayer((s) => s.completeGuided);

  const library = useQuery({
    queryKey: ['exercises', 'zone', 'pelvic_floor'],
    queryFn: () => exercisesApi.list({ zone: 'pelvic_floor' }),
    staleTime: 10 * 60_000,
  });
  const bySlug = useMemo(() => {
    const map = new Map<string, Exercise>();
    library.data?.data.forEach((exercise) => map.set(exercise.slug, exercise));
    return map;
  }, [library.data]);

  const [routine, setRoutine] = useState<Routine | null>(null);
  const [elapsed, setElapsed] = useState(0);
  const [paused, setPaused] = useState(false);
  const startedAt = useRef<string | null>(null);
  const blockTimes = useRef<Record<number, { start: string; end?: string }>>({});

  const timeline = useMemo(() => (routine ? buildTimeline(routine) : null), [routine]);
  const stepIndex = useMemo(() => {
    if (!timeline) return -1;
    const index = timeline.steps.findIndex((s) => elapsed < s.startsAt + s.seconds);
    return index === -1 ? timeline.steps.length : index;
  }, [timeline, elapsed]);
  const step = timeline?.steps[stepIndex] ?? null;
  const finished = Boolean(timeline && stepIndex >= timeline.steps.length);

  // ---- ტაიმერი ----
  useEffect(() => {
    if (!routine || paused || finished) return;
    const id = setInterval(() => setElapsed((value) => value + TICK_MS / 1000), TICK_MS);
    return () => clearInterval(id);
  }, [routine, paused, finished]);

  // ---- ფაზის ცვლა: ვიბრაცია + ბლოკის დროის აღრიცხვა ----
  useEffect(() => {
    if (!step) return;
    const now = new Date().toISOString();
    const times = blockTimes.current;
    if (!times[step.blockIndex]) times[step.blockIndex] = { start: now };
    if (step.rep !== null) times[step.blockIndex].end = now;

    if (step.level > 0 && (step.cue === 'contract' || step.cue === 'floor1')) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => undefined);
    } else if (step.cue === 'release') {
      Haptics.selectionAsync().catch(() => undefined);
    }
  }, [stepIndex]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (finished) save(false);
  }, [finished]); // eslint-disable-line react-hooks/exhaustive-deps

  const begin = (next: Routine) => {
    blockTimes.current = {};
    startedAt.current = new Date().toISOString();
    setElapsed(0);
    setPaused(false);
    setRoutine(next);
  };

  /** შესრულებული ბლოკები → სეტები. XP-ის რიცხვი ესტიმაციაა, ნამდვილს სერვერი ითვლის */
  const save = (painStop: boolean) => {
    if (!routine || !timeline || !startedAt.current) return;

    const exercises = new Map<number, PlayerExercise>();
    const logged: LoggedSet[] = [];
    const setNo: Record<number, number> = {};

    routine.blocks.forEach((block, blockIndex) => {
      const exercise = bySlug.get(block.slug);
      const times = blockTimes.current[blockIndex];
      if (!exercise || !times?.end) return;

      const blockDone = elapsed >= (timeline.steps.find((s) => s.blockIndex === blockIndex + 1)?.startsAt ?? timeline.total);
      // ნახევრად შესრულებული ბლოკი (ტკივილი/ვადამდე) — მხოლოდ გასული ნაწილი
      const share = blockDone ? 1 : partialShare(timeline, blockIndex, elapsed);
      if (share <= 0) return;

      const reps = Math.max(1, Math.round(block.repeat * share));
      const seconds = Math.round(contractedSeconds(block) * share);

      exercises.set(exercise.id, {
        exerciseId: exercise.id,
        name: exercise.name,
        slug: exercise.slug,
        unit: exercise.unit,
        difficultyCoef: exercise.difficulty_coef,
        force: exercise.force,
        loop: resolveLoop(exercise.media),
        sets: (exercises.get(exercise.id)?.sets ?? 0) + 1,
        targetReps: exercise.unit === 'reps' ? block.repeat : null,
        targetSeconds: exercise.unit === 'seconds' ? contractedSeconds(block) : null,
        restSeconds: block.restAfter,
        tempo: 'normal',
      });

      setNo[exercise.id] = (setNo[exercise.id] ?? 0) + 1;
      logged.push({
        exerciseId: exercise.id,
        setNo: setNo[exercise.id],
        reps: exercise.unit === 'reps' ? reps : null,
        seconds: exercise.unit === 'seconds' ? seconds : null,
        addedWeightKg: 0,
        tempo: 'normal',
        restAfterMs: block.restAfter * 1000,
        startedAt: times.start,
        completedAt: times.end,
        estimatedXp: estimateSetXp({
          difficultyCoef: exercise.difficulty_coef,
          unit: exercise.unit,
          reps,
          seconds,
        }),
      });
    });

    if (logged.length === 0) {
      setRoutine(null);
      return;
    }

    completeGuided({
      exercises: [...exercises.values()],
      logged,
      startedAt: startedAt.current,
      source: 'kegel',
      painStop,
    });
    router.replace('/summary');
  };

  const onPain = () => {
    setPaused(true);
    Alert.alert(t('player.painTitle'), t('kegel.painBody'), [
      { text: t('common.cancel'), style: 'cancel', onPress: () => setPaused(false) },
      { text: t('player.endSession'), style: 'destructive', onPress: () => save(true) },
    ]);
  };

  const onStop = () => {
    setPaused(true);
    Alert.alert(t('kegel.stopTitle'), t('kegel.stopBody'), [
      { text: t('common.cancel'), style: 'cancel', onPress: () => setPaused(false) },
      { text: t('player.endSession'), style: 'destructive', onPress: () => save(false) },
    ]);
  };

  // ---------------------------------------------------------------- intro
  if (!routine || !timeline) {
    const intro = bySlug.get('kegel-basic-hold');
    const instructions = Array.isArray(intro?.instructions) ? intro.instructions : [];
    const missing = !library.isLoading && bySlug.size === 0;

    return (
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <Glow color={TINT} size={460} opacity={0.22} style={{ top: -240, left: -140 }} />
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
            <Text variant="overline" style={{ color: TINT }}>
              {t('library.zone_pelvic_floor')}
            </Text>
            <Text variant="title">{t('kegel.title')}</Text>
            <Text variant="bodySm" tone="secondary">
              {t('kegel.sub')}
            </Text>
          </Reveal>

          <Reveal index={2} zoom>
            <Card accent={TINT}>
              <View style={{ alignItems: 'center' }}>
                <IntroDiagram />
              </View>
              <Text variant="subheading" style={{ marginTop: space.sm }}>
                {t('kegel.howToFind')}
              </Text>
              <View style={{ gap: space.xs, marginTop: space.xs }}>
                {library.isLoading ? (
                  <Skeleton height={60} />
                ) : (
                  (instructions.length ? instructions : [t('kegel.findFallback')]).map((line, index) => (
                    <Text key={index} variant="bodySm" tone="secondary">
                      {index + 1}. {line}
                    </Text>
                  ))
                )}
              </View>
            </Card>
          </Reveal>

          <Reveal index={3} style={{ gap: space.sm }}>
            {ROUTINES.map((item) => (
              <Tap key={item.key} onPress={() => begin(item)} scaleTo={0.985} haptic="medium" disabled={missing}>
                <View
                  style={{
                    padding: space.base,
                    borderRadius: radius.md,
                    borderWidth: border.hair,
                    borderColor: colors.border,
                    backgroundColor: colors.surface,
                    flexDirection: 'row',
                    alignItems: 'center',
                    gap: space.md,
                    opacity: missing ? 0.5 : 1,
                  }}
                >
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="subheading">{t(`kegel.level_${item.key}`)}</Text>
                    <Text variant="caption" tone="muted">
                      {t(`kegel.level_${item.key}_hint`)}
                    </Text>
                  </View>
                  <Text variant="numeric" style={{ color: TINT }}>
                    ~{routineMinutes(item)} {t('common.min')}
                  </Text>
                </View>
              </Tap>
            ))}
            {missing ? (
              <Text variant="caption" tone="muted" center>
                {t('kegel.unavailable')}
              </Text>
            ) : null}
          </Reveal>

          <Reveal index={4}>
            <Text variant="caption" tone="muted">
              {t('kegel.safety')}
            </Text>
          </Reveal>
        </ScrollView>
      </View>
    );
  }

  // ---------------------------------------------------------------- guided
  const block = step ? routine.blocks[step.blockIndex] : null;
  const exercise = block ? bySlug.get(block.slug) : undefined;
  const loop = resolveLoop(exercise?.media);
  const stepProgress = step ? Math.min(1, (elapsed - step.startsAt) / step.seconds) : 1;
  const remaining = step ? Math.max(0, Math.ceil(step.startsAt + step.seconds - elapsed)) : 0;
  const isRest = step?.rep === null;

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={TINT} size={520} opacity={step && step.level > 0 ? 0.34 : 0.16} style={{ top: -200, left: -160 }} />
      <Grain />

      <View
        style={{
          flex: 1,
          paddingTop: insets.top + space.sm,
          paddingBottom: insets.bottom + space.base,
          paddingHorizontal: gutter,
          gap: space.base,
        }}
      >
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Tap onPress={onStop}>
            <Text variant="label" tone="muted">
              ✕ {t('player.endSession')}
            </Text>
          </Tap>
          <Text variant="numeric" tone="muted">
            {formatClock(elapsed)} / {formatClock(timeline.total)}
          </Text>
        </View>

        <Bar progress={elapsed / timeline.total} height={6} from={TINT} to={TINT} />

        <View style={{ gap: 2 }}>
          <Text variant="overline" style={{ color: TINT }}>
            {t('kegel.block', { current: (step?.blockIndex ?? 0) + 1, total: routine.blocks.length })}
          </Text>
          <Text variant="heading" numberOfLines={1}>
            {isRest ? t('kegel.rest') : (exercise?.name ?? block?.slug)}
          </Text>
          {!isRest && step?.rep && block ? (
            <Text variant="caption" tone="muted">
              {t('kegel.rep', { current: step.rep, total: block.repeat })}
            </Text>
          ) : null}
        </View>

        {/* ---- „ვიდეოს" სლოტი: საკუთარი კადრი, თუ არ არის — სქემა ---- */}
        <View style={{ alignItems: 'center', justifyContent: 'center', flex: 1, gap: space.lg }}>
          {loop && !isRest ? (
            <MediaLoop source={loop} tint={TINT} style={{ width: '100%' }} />
          ) : (
            <PelvisDiagram level={step?.level ?? 0} ms={(step?.seconds ?? 1) * 1000} />
          )}

          <Ring progress={stepProgress} size={170} stroke={8} from={TINT} to={TINT}>
            <Squeeze level={step?.level ?? 0} ms={(step?.seconds ?? 1) * 1000} min={0.7}>
              <View
                style={{
                  width: 118,
                  height: 118,
                  borderRadius: 59,
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: `${TINT}${step && step.level > 0 ? '44' : '18'}`,
                  borderWidth: border.thin,
                  borderColor: TINT,
                }}
              >
                <Text variant="display" style={{ color: colors.text }}>
                  {remaining}
                </Text>
              </View>
            </Squeeze>
          </Ring>

          <Fade visible key={`${stepIndex}`}>
            <Text variant="title" center>
              {step ? t(`kegel.cue_${step.cue}`) : t('common.done')}
            </Text>
            <Text variant="bodySm" tone="muted" center style={{ marginTop: space.xs }}>
              {step ? t(`kegel.cue_${step.cue}_hint`) : ''}
            </Text>
          </Fade>
        </View>

        <View style={{ flexDirection: 'row', gap: space.sm }}>
          <View style={{ flex: 1 }}>
            <Button title={t('player.pain')} variant="danger" onPress={onPain} />
          </View>
          <View style={{ flex: 1 }}>
            <Button
              title={paused ? t('kegel.resume') : t('kegel.pause')}
              variant="secondary"
              onPress={() => setPaused((value) => !value)}
            />
          </View>
        </View>
      </View>
    </View>
  );
}

/** ინტროს სქემა სუნთქავს — მომხმარებელი ხედავს, რა ხდება შეკუმშვისას */
function IntroDiagram() {
  const [level, setLevel] = useState(0);

  useEffect(() => {
    const id = setInterval(() => setLevel((value) => (value > 0 ? 0 : 1)), 2200);
    return () => clearInterval(id);
  }, []);

  return <PelvisDiagram level={level} ms={1800} size={200} />;
}

function partialShare(timeline: ReturnType<typeof buildTimeline>, blockIndex: number, elapsed: number) {
  const steps = timeline.steps.filter((s) => s.blockIndex === blockIndex && s.rep !== null);
  if (steps.length === 0) return 0;
  const start = steps[0].startsAt;
  const end = steps[steps.length - 1].startsAt + steps[steps.length - 1].seconds;
  return Math.max(0, Math.min(1, (elapsed - start) / (end - start)));
}

function formatClock(seconds: number) {
  const s = Math.floor(seconds);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
