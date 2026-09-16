import { useEffect, useRef } from 'react';
import { View } from 'react-native';
import Animated, {
  cancelAnimation,
  useAnimatedStyle,
  useSharedValue,
  withSequence,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { colors, duration, easing, radius, space, border, springBouncy } from '@/theme';
import { Tap, Text, Fade, Pop } from '@/components';

interface RepPadProps {
  value: number;
  target: number | null;
  unit: 'reps' | 'seconds';
  onChange: (value: number) => void;
}

/**
 * სეტის ჩაწერის პადი. მიზანი — ერთი შეხებით დაფიქსირება,
 * როცა ხელები დაღლილია: დიდი ციფრი ცენტრში, ორი დიდი ღილაკი
 * გვერდებზე და სამიზნის „მალსახმობი".
 *
 * ღილაკის დაჭერა ციფრს „ხტუნავს“ და მიმართულებას აჩვენებს —
 * ვარჯიშის შუაში, ოფლიან ეკრანზე, დადასტურება მოძრაობით უფრო
 * სწრაფად იკითხება, ვიდრე თვით ციფრით.
 */
export function RepPad({ value, target, unit, onChange }: RepPadProps) {
  const step = unit === 'seconds' ? 5 : 1;
  const reached = target !== null && value >= target;

  const repeat = useRef<ReturnType<typeof setInterval> | null>(null);
  const glow = useSharedValue(0);

  useEffect(() => () => (repeat.current ? clearInterval(repeat.current) : undefined), []);

  // სამიზნეს მიღწევა — ერთჯერადი ნათება ციფრის უკან
  useEffect(() => {
    if (!reached) return;

    glow.value = withSequence(
      withTiming(1, { duration: duration.fast, easing: easing.out }),
      withTiming(0.4, { duration: duration.lazy, easing: easing.inOut }),
    );

    return () => cancelAnimation(glow);
  }, [reached, glow]);

  const halo = useAnimatedStyle(() => ({ opacity: glow.value * 0.5, transform: [{ scale: 1 + glow.value * 0.1 }] }));

  const hold = (delta: number) => {
    // დაჭერის შენარჩუნება — 20 გამეორება ერთ-ერთზე დაწკაპუნებით არავის უნდა
    repeat.current = setInterval(() => onChange(Math.max(0, value + delta)), 90);
  };

  const release = () => {
    if (repeat.current) clearInterval(repeat.current);
    repeat.current = null;
  };

  return (
    <View style={{ gap: space.base }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
        <StepControl label="−" onPress={() => onChange(Math.max(0, value - step))} onHold={() => hold(-step)} onRelease={release} />

        <View style={{ alignItems: 'center' }}>
          <View style={{ alignItems: 'center', justifyContent: 'center' }}>
            <Animated.View
              pointerEvents="none"
              style={[
                {
                  position: 'absolute',
                  width: 150,
                  height: 150,
                  borderRadius: 75,
                  backgroundColor: colors.accentGlow,
                },
                halo,
              ]}
            />

            <Pop trigger={value}>
              <Text
                variant="hero"
                style={{ color: reached ? colors.accent : colors.text, fontSize: 76 }}
              >
                {value}
              </Text>
            </Pop>
          </View>

          {target ? (
            <Text variant="caption" style={{ color: reached ? colors.accent : colors.textMuted }}>
              / {target}
            </Text>
          ) : null}
        </View>

        <StepControl label="+" onPress={() => onChange(value + step)} onHold={() => hold(step)} onRelease={release} />
      </View>

      {/* სამიზნეზე დაბრუნება ერთი შეხებით — ყველაზე ხშირი შემთხვევა */}
      <Fade visible={!!target && value !== target} style={{ alignSelf: 'center' }}>
        <Tap onPress={() => target && onChange(target)} haptic="light">
          <View
            style={{
              paddingHorizontal: space.base,
              paddingVertical: space.xs + 2,
              borderRadius: radius.pill,
              backgroundColor: colors.surfaceHi,
              borderWidth: border.hair,
              borderColor: colors.border,
            }}
          >
            <Text variant="caption" tone="secondary">
              {target}
            </Text>
          </View>
        </Tap>
      </Fade>
    </View>
  );
}

function StepControl({
  label,
  onPress,
  onHold,
  onRelease,
}: {
  label: string;
  onPress: () => void;
  onHold: () => void;
  onRelease: () => void;
}) {
  const tilt = useSharedValue(0);

  const animated = useAnimatedStyle(() => ({
    transform: [{ scale: 1 + tilt.value * 0.06 }],
    borderColor: tilt.value > 0 ? colors.borderStrong : colors.border,
  }));

  return (
    <Tap
      onPress={() => {
        tilt.value = withSequence(withTiming(1, { duration: 80 }), withSpring(0, springBouncy));
        onPress();
      }}
      onLongPress={onHold}
      onPressOut={onRelease}
      delayLongPress={280}
      haptic="medium"
      scaleTo={0.92}
    >
      <Animated.View
        style={[
          {
            width: 72,
            height: 72,
            borderRadius: radius.lg,
            alignItems: 'center',
            justifyContent: 'center',
            backgroundColor: colors.surfaceHi,
            borderWidth: border.thin,
            borderColor: colors.border,
          },
          animated,
        ]}
      >
        <Text variant="display" tone="secondary" style={{ fontSize: 30 }}>
          {label}
        </Text>
      </Animated.View>
    </Tap>
  );
}
