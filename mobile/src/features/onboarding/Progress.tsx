import { useEffect } from 'react';
import { View } from 'react-native';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { colors, duration, easing, radius, space, spring } from '@/theme';
import { Tap, Text } from '@/components';

/**
 * ონბორდინგის ნაბიჯების ზოლი — ერთი შეხედვით ჩანს რამდენი დარჩა.
 *
 * მიმდინარე სეგმენტი მარცხნიდან ივსება და არა უბრალოდ ფერს იცვლის:
 * შევსება მიმართულებას აძლევს — „აქეთ მიდიხარ“.
 */
export function OnboardingProgress({
  step,
  total,
  onBack,
}: {
  step: number;
  total: number;
  /** უკან დაბრუნება — არჩევანის შეცვლა ნაბიჯის დაწყებიდანვე უნდა შეიძლებოდეს */
  onBack?: () => void;
}) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm, marginBottom: space.xl }}>
      {onBack ? (
        <Tap onPress={onBack} hitSlop={14} style={{ paddingRight: space.xs }}>
          <Text variant="label" tone="muted">
            ←
          </Text>
        </Tap>
      ) : null}

      <View style={{ flex: 1, flexDirection: 'row', gap: space.xs }}>
        {Array.from({ length: total }, (_, index) => (
          <Segment key={index} index={index} done={index < step - 1} active={index === step - 1} />
        ))}
      </View>
    </View>
  );
}

function Segment({ index, done, active }: { index: number; done: boolean; active: boolean }) {
  const fill = useSharedValue(done ? 1 : 0);
  const height = useSharedValue(active ? 1 : 0);

  useEffect(() => {
    // გავლილი სეგმენტები მაშინვე სავსეა, მიმდინარე — ივსება
    fill.value = done
      ? withTiming(1, { duration: duration.instant })
      : active
        ? withDelay(index * 40 + 120, withTiming(1, { duration: duration.slow, easing: easing.out }))
        : withTiming(0, { duration: duration.fast });

    height.value = withSpring(active ? 1 : 0, spring);
  }, [done, active, index, fill, height]);

  const bar = useAnimatedStyle(() => ({ width: `${fill.value * 100}%` }));
  const track = useAnimatedStyle(() => ({ height: 3 + height.value * 2 }));

  return (
    <Animated.View
      style={[
        { flex: 1, borderRadius: radius.pill, backgroundColor: colors.surfaceHi, overflow: 'hidden' },
        track,
      ]}
    >
      <Animated.View
        style={[
          { height: '100%', borderRadius: radius.pill, backgroundColor: active ? colors.accent : colors.accentDim },
          bar,
        ]}
      />
    </Animated.View>
  );
}
