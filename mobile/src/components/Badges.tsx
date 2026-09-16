import { useEffect } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import Animated, {
  cancelAnimation,
  Extrapolation,
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import { colors, divisionTheme, easing, radius, space, tierTheme, border, type DivisionId } from '@/theme';
import { Text } from './Text';

/** ვერიფიკაციის დონე T0..T3 — რიცხვი ჩუმია, ფერი ატარებს მნიშვნელობას */
export function TierBadge({ tier }: { tier: 0 | 1 | 2 | 3 }) {
  const { t } = useTranslation();
  const theme = tierTheme[tier];

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        gap: space.xs,
        paddingHorizontal: space.sm,
        paddingVertical: 3,
        borderRadius: radius.xs,
        backgroundColor: `${theme.tint}1A`,
        borderWidth: border.hair,
        borderColor: `${theme.tint}44`,
      }}
    >
      <View style={{ width: 5, height: 5, borderRadius: 3, backgroundColor: theme.tint }} />
      <Text variant="caption" style={{ color: theme.tint }}>
        {t(`verification.t${tier}`)}
      </Text>
    </View>
  );
}

export function DivisionBadge({ division, size = 'md' }: { division: DivisionId; size?: 'sm' | 'md' }) {
  const { t } = useTranslation();
  const theme = divisionTheme[division];
  const dot = size === 'sm' ? 6 : 8;

  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        gap: space.sm,
        paddingHorizontal: size === 'sm' ? space.sm : space.md,
        paddingVertical: size === 'sm' ? 4 : space.xs + 2,
        borderRadius: radius.pill,
        backgroundColor: theme.glow,
        borderWidth: border.hair,
        borderColor: `${theme.tint}55`,
      }}
    >
      <View style={{ width: dot, height: dot, borderRadius: dot, backgroundColor: theme.tint }} />
      <Text variant={size === 'sm' ? 'caption' : 'label'} style={{ color: theme.tint }}>
        {t(`league.division_${division}`)}
      </Text>
    </View>
  );
}

/**
 * ცოცხალი წერტილი — „ახლა ვარჯიშობს" ინდიკატორი მოედნის ბარათზე.
 * წერტილიდან რადარივით გადის ტალღა: სტატიკური წერტილი „ახლას" ვერ ამბობს.
 */
export function LivePulse({ count, label }: { count: number; label: string }) {
  const wave = useSharedValue(0);

  useEffect(() => {
    if (count <= 0) {
      cancelAnimation(wave);
      return;
    }

    wave.value = withRepeat(withTiming(1, { duration: 2000, easing: easing.out }), -1, false);

    return () => cancelAnimation(wave);
  }, [count, wave]);

  const ripple = useAnimatedStyle(() => ({
    opacity: interpolate(wave.value, [0, 1], [0.5, 0], Extrapolation.CLAMP),
    transform: [{ scale: interpolate(wave.value, [0, 1], [1, 3.4], Extrapolation.CLAMP) }],
  }));

  if (count <= 0) return null;

  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.xs }}>
      <View style={{ width: 7, height: 7, alignItems: 'center', justifyContent: 'center' }}>
        <Animated.View
          pointerEvents="none"
          style={[
            { position: 'absolute', width: 7, height: 7, borderRadius: 4, backgroundColor: colors.success },
            ripple,
          ]}
        />
        <View style={{ width: 7, height: 7, borderRadius: 4, backgroundColor: colors.success }} />
      </View>

      <Text variant="caption" style={{ color: colors.success }}>
        {label}
      </Text>
    </View>
  );
}
