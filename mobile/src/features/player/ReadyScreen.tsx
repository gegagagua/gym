import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withSequence,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { Text, Glow, Tap, Reveal, MediaLoop } from '@/components';
import { duration, easing, gutter, space, springBouncy } from '@/theme';
import type { PlayerExercise } from '@/store/player';

const COUNT_FROM = 3;

/**
 * ვარჯიშის დაწყებამდე 3-2-1.
 *
 * ეს ნაბიჯი არ არის დეკორაცია: პირველი სეტი ხშირად ტელეფონის
 * ჯიბეში ჩადებისას იწყება და ათვლის გარეშე პირველი გამეორებები
 * უკვე დაწყებულ ტაიმერში ხვდება. ეკრანზე შეხება ათვლას ტოვებს.
 */
export function ReadyScreen({
  exercise,
  tint,
  onDone,
}: {
  exercise: PlayerExercise;
  tint: string;
  onDone: () => void;
}) {
  const { t } = useTranslation();
  const [count, setCount] = useState(COUNT_FROM);

  const scale = useSharedValue(0.6);
  const opacity = useSharedValue(0);

  useEffect(() => {
    const timer = setInterval(() => setCount((value) => value - 1), 800);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    if (count < 0) {
      onDone();
      return;
    }

    Haptics.impactAsync(count === 0 ? Haptics.ImpactFeedbackStyle.Heavy : Haptics.ImpactFeedbackStyle.Light).catch(
      () => undefined,
    );

    scale.value = 0.6;
    opacity.value = 0;
    scale.value = withSpring(1, springBouncy);
    opacity.value = withSequence(
      withTiming(1, { duration: duration.instant, easing: easing.out }),
      withTiming(0.15, { duration: 700, easing: easing.in }),
    );
  }, [count, onDone, scale, opacity]);

  const animated = useAnimatedStyle(() => ({ opacity: opacity.value, transform: [{ scale: scale.value }] }));

  const goal =
    exercise.unit === 'seconds'
      ? `${exercise.targetSeconds ?? 30} ${t('common.sec')}`
      : `${exercise.targetReps ?? 10} ${t('common.reps')}`;

  return (
    <Tap
      onPress={onDone}
      haptic={false}
      scaleTo={1}
      style={{ flex: 1, alignItems: 'center', justifyContent: 'center', paddingHorizontal: gutter, gap: space.xl }}
    >
      <Glow color={tint} size={480} opacity={0.34} />

      <Reveal from="top" distance={10}>
        <Text variant="overline" tone="muted">
          {t('player.getReady')}
        </Text>
      </Reveal>

      <Reveal index={1} zoom style={{ width: '100%' }}>
        <MediaLoop
          source={exercise.loop}
          tint={tint}
          aspectRatio={16 / 10}
          initials={exercise.name}
          emptyLabel={t('library.noLoop')}
        />
      </Reveal>

      <Reveal index={2} style={{ alignItems: 'center' }}>
        <View style={{ alignItems: 'center', gap: space.xs }}>
          <Text variant="title" center numberOfLines={2}>
            {exercise.name}
          </Text>
          <Text variant="bodySm" tone="muted">
            {exercise.sets} × {goal}
          </Text>
        </View>
      </Reveal>

      <View style={{ height: 120, alignItems: 'center', justifyContent: 'center' }}>
        <Animated.View style={animated}>
          <Text variant="hero" style={{ color: tint, fontSize: 108 }}>
            {count > 0 ? count : '↑'}
          </Text>
        </Animated.View>
      </View>
    </Tap>
  );
}
