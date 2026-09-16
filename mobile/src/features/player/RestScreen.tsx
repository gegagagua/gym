import { useEffect } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';
import Animated, {
  cancelAnimation,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withTiming,
} from 'react-native-reanimated';
import { Ring, Text, Button, Glow, Reveal, Pop } from '@/components';
import { colors, duration, easing, space } from '@/theme';
import { formatClock } from '@/lib/xp';

interface RestScreenProps {
  remaining: number;
  total: number;
  nextName: string;
  nextSet: number;
  nextTotal: number;
  onSkip: () => void;
  onTick: () => void;
  hapticCues: boolean;
}

const ALERT_AT = 3;

/**
 * დასვენება. ეკრანი სუნთქავს იმ რიტმით, რომლითაც სუნთქვა უნდა —
 * 4 წმ შიგნით, 4 წმ გარეთ. ბოლო სამ წამზე ატმოსფერო ლაიმისფერზე
 * გადადის და პულსი ჩქარდება: „მოემზადე“ ტაქტილურადაც და ვიზუალურადაც.
 */
export function RestScreen({
  remaining,
  total,
  nextName,
  nextSet,
  nextTotal,
  onSkip,
  onTick,
  hapticCues,
}: RestScreenProps) {
  const { t } = useTranslation();
  const alert = remaining > 0 && remaining <= ALERT_AT;
  const tint = alert ? colors.accent : colors.info;

  const breath = useSharedValue(1);
  const flash = useSharedValue(0);

  useEffect(() => {
    const timer = setInterval(onTick, 1000);
    return () => clearInterval(timer);
  }, [onTick]);

  useEffect(() => {
    // 3-2-1 ტაქტილური ათვლა — ტელეფონი ჯიბეშია, ხმა ხშირად გამორთულია
    if (hapticCues && alert) {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => undefined);
    }
  }, [remaining, hapticCues, alert]);

  // სუნთქვის რიტმი: მშვიდი 8 წმ ციკლი, ბოლო წამებზე — ორჯერ სწრაფი
  useEffect(() => {
    const period = alert ? 900 : 8000;

    breath.value = withRepeat(
      withSequence(
        withTiming(alert ? 1.06 : 1.04, { duration: period / 2, easing: easing.inOut }),
        withTiming(1, { duration: period / 2, easing: easing.inOut }),
      ),
      -1,
      false,
    );

    return () => cancelAnimation(breath);
  }, [alert, breath]);

  // ყოველ წამზე რგოლის უკან სუსტი ალი გაივლის
  useEffect(() => {
    flash.value = withSequence(
      withTiming(1, { duration: duration.instant, easing: easing.out }),
      withTiming(0, { duration: duration.lazy, easing: easing.in }),
    );
  }, [remaining, flash]);

  const breathing = useAnimatedStyle(() => ({ transform: [{ scale: breath.value }] }));
  const flashing = useAnimatedStyle(() => ({ opacity: 0.28 + flash.value * 0.34 }));

  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: space.xl }}>
      <Animated.View style={[{ position: 'absolute' }, flashing]}>
        <Glow color={tint} size={420} opacity={1} />
      </Animated.View>

      <Reveal from="top" distance={10}>
        <Text variant="overline" style={{ color: alert ? colors.accent : colors.textMuted }}>
          {alert ? t('player.getReady') : t('player.rest')}
        </Text>
      </Reveal>

      <Animated.View style={breathing}>
        <Ring
          progress={total > 0 ? 1 - remaining / total : 1}
          size={250}
          stroke={14}
          from={colors.info}
          to={colors.accent}
        >
          <Pop trigger={remaining}>
            <Text variant="timer" style={{ fontSize: 62, color: alert ? colors.accent : colors.text }}>
              {formatClock(remaining)}
            </Text>
          </Pop>
        </Ring>
      </Animated.View>

      <Reveal index={1} style={{ alignItems: 'center' }}>
        <View style={{ alignItems: 'center', gap: space.xs }}>
          <Text variant="overline" tone="muted">
            {t('player.nextExercise')}
          </Text>
          <Text variant="heading" center>
            {nextName}
          </Text>
          <Text variant="caption" tone="muted">
            {t('player.set', { current: nextSet, total: nextTotal })}
          </Text>
        </View>
      </Reveal>

      <Reveal index={2}>
        <Button title={t('player.skipRest')} variant="secondary" full={false} onPress={onSkip} />
      </Reveal>
    </View>
  );
}
