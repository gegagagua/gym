import { useEffect, type ReactNode } from 'react';
import { View } from 'react-native';
import Animated, {
  interpolateColor,
  useAnimatedStyle,
  useSharedValue,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { colors, duration, easing, radius, space, border, spring, springBouncy } from '@/theme';
import { Tap, Text } from '@/components';

interface OptionProps {
  title: string;
  description?: string;
  selected: boolean;
  onPress: () => void;
  tint?: string;
  trailing?: ReactNode;
}

/**
 * ონბორდინგის არჩევანის რიგი. მონიშვნა ფერით და ბორდერით ხდება,
 * არა ჩექბოქსით — თითი დიდია, სამიზნე ზონა მთელი რიგია.
 *
 * არჩევა მყისიერად არ „ინთება“: ბორდერი და ფონი ერთ მოძრაობაში
 * გადადის, წერტილი კი ხტება — დადასტურება იგრძნობა, არა მხოლოდ ჩანს.
 */
export function Option({ title, description, selected, onPress, tint = colors.accent, trailing }: OptionProps) {
  const on = useSharedValue(selected ? 1 : 0);

  useEffect(() => {
    on.value = withTiming(selected ? 1 : 0, { duration: duration.base, easing: easing.out });
  }, [selected, on]);

  const surface = useAnimatedStyle(() => ({
    backgroundColor: interpolateColor(on.value, [0, 1], [colors.surface, `${tint}14`]),
    borderColor: interpolateColor(on.value, [0, 1], [colors.border, tint]),
    borderWidth: border.hair + on.value * (border.thick - border.hair),
  }));

  return (
    <Tap onPress={onPress} scaleTo={0.985} haptic={selected ? 'light' : 'medium'}>
      <Animated.View
        style={[
          {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space.md,
            paddingHorizontal: space.base,
            paddingVertical: space.base,
            borderRadius: radius.md,
          },
          surface,
        ]}
      >
        <Dot selected={selected} tint={tint} />

        <View style={{ flex: 1, gap: 2 }}>
          <Text variant="subheading" style={{ color: selected ? colors.text : colors.textSecondary }}>
            {title}
          </Text>
          {description ? (
            <Text variant="bodySm" tone="muted">
              {description}
            </Text>
          ) : null}
        </View>

        {trailing}
      </Animated.View>
    </Tap>
  );
}

function Dot({ selected, tint }: { selected: boolean; tint: string }) {
  const scale = useSharedValue(selected ? 1 : 0);

  useEffect(() => {
    scale.value = withSpring(selected ? 1 : 0, selected ? springBouncy : spring);
  }, [selected, scale]);

  const inner = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }], opacity: scale.value }));

  return (
    <View
      style={{
        width: 18,
        height: 18,
        borderRadius: 9,
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1.5,
        borderColor: selected ? tint : colors.textDisabled,
      }}
    >
      <Animated.View
        style={[{ width: 10, height: 10, borderRadius: 5, backgroundColor: tint }, inner]}
      />
    </View>
  );
}
