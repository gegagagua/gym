import { useEffect, useRef, useState } from 'react';
import { View } from 'react-native';
import * as Haptics from 'expo-haptics';
import Animated, {
  cancelAnimation,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSequence,
  withTiming,
} from 'react-native-reanimated';
import { Ring, Tap, Text, Pop, Reveal } from '@/components';
import { colors, duration, easing, space } from '@/theme';
import { formatClock } from '@/lib/xp';

interface HoldTimerProps {
  target: number;
  onDone: (seconds: number) => void;
}

/**
 * სტატიკური სავარჯიშოს ტაიმერი — plank, L-sit, front lever.
 *
 * გაშვებული ტაიმერი სუნთქავს: რგოლი ნელა ფართოვდება და იკუმშება,
 * რაც სუნთქვის რიტმს აძლევს — სტატიკაზე სუნთქვის შეკავება ყველაზე
 * ხშირი შეცდომაა. სამიზნის გადალახვისას რგოლი ლაიმისფერზე გადადის.
 */
export function HoldTimer({ target, onDone }: HoldTimerProps) {
  const [elapsed, setElapsed] = useState(0);
  const [running, setRunning] = useState(false);
  const interval = useRef<ReturnType<typeof setInterval> | null>(null);
  const celebrated = useRef(false);

  const breath = useSharedValue(1);
  const bloom = useSharedValue(0);

  const reached = target > 0 && elapsed >= target;

  useEffect(() => {
    if (!running) return;

    interval.current = setInterval(() => {
      setElapsed((current) => {
        const next = current + 1;
        // ბოლო 3 წამი — ტაქტილური ათვლა, ეკრანს არ იყურები
        if (target > 0 && target - next <= 3 && target - next >= 0) {
          Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => undefined);
        }
        return next;
      });
    }, 1000);

    return () => {
      if (interval.current) clearInterval(interval.current);
    };
  }, [running, target]);

  // სუნთქვის რიტმი მხოლოდ გაშვებულ ტაიმერზე — პაუზაზე ეკრანი ჩერდება
  useEffect(() => {
    if (!running) {
      cancelAnimation(breath);
      breath.value = withTiming(1, { duration: duration.base, easing: easing.out });
      return;
    }

    breath.value = withRepeat(
      withSequence(
        withTiming(1.035, { duration: 4000, easing: easing.inOut }),
        withTiming(0.99, { duration: 4000, easing: easing.inOut }),
      ),
      -1,
      false,
    );

    return () => cancelAnimation(breath);
  }, [running, breath]);

  // სამიზნის გადალახვა — ერთხელ ინთება და ტაქტილურად დასტურდება
  useEffect(() => {
    if (!reached || celebrated.current) return;

    celebrated.current = true;
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);
    bloom.value = withSequence(
      withTiming(1, { duration: duration.base, easing: easing.out }),
      withTiming(0.45, { duration: duration.lazy, easing: easing.inOut }),
    );
  }, [reached, bloom]);

  const breathing = useAnimatedStyle(() => ({ transform: [{ scale: breath.value }] }));
  const blooming = useAnimatedStyle(() => ({ opacity: bloom.value * 0.55, transform: [{ scale: 1 + bloom.value * 0.08 }] }));

  return (
    <View style={{ alignItems: 'center', gap: space.lg }}>
      <View style={{ alignItems: 'center', justifyContent: 'center' }}>
        <Animated.View
          pointerEvents="none"
          style={[
            { position: 'absolute', width: 230, height: 230, borderRadius: 115, backgroundColor: colors.accentGlow },
            blooming,
          ]}
        />

        <Animated.View style={breathing}>
          <Ring
            progress={target > 0 ? elapsed / target : 0}
            size={210}
            stroke={12}
            from={reached ? colors.accent : colors.info}
            to={reached ? colors.accent : colors.accent}
          >
            <Pop trigger={elapsed}>
              <Text variant="timer" style={{ fontSize: 52, color: reached ? colors.accent : colors.text }}>
                {formatClock(elapsed)}
              </Text>
            </Pop>
          </Ring>
        </Animated.View>
      </View>

      <Reveal index={1}>
        <View style={{ flexDirection: 'row', gap: space.md }}>
          <Tap
            onPress={() => setRunning((value) => !value)}
            haptic="medium"
            scaleTo={0.94}
            style={{
              paddingHorizontal: space.xxl,
              paddingVertical: space.base,
              borderRadius: 999,
              backgroundColor: running ? colors.surfaceHi : colors.accent,
            }}
          >
            <Text variant="subheading" style={{ color: running ? colors.text : colors.onAccent }}>
              {running ? '❙❙' : '▶'}
            </Text>
          </Tap>

          <Tap
            onPress={() => {
              setRunning(false);
              onDone(elapsed);
            }}
            haptic="success"
            scaleTo={0.94}
            disabled={elapsed === 0}
            style={{
              paddingHorizontal: space.xxl,
              paddingVertical: space.base,
              borderRadius: 999,
              backgroundColor: reached ? colors.accent : colors.surfaceHi,
            }}
          >
            <Text variant="subheading" style={{ color: reached ? colors.onAccent : colors.text }}>
              ✓
            </Text>
          </Tap>
        </View>
      </Reveal>
    </View>
  );
}
