import { useEffect } from 'react';
import { View } from 'react-native';
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from 'react-native-reanimated';
import { LinearGradient } from 'expo-linear-gradient';
import { colors, radius, spring } from '@/theme';

interface BarProps {
  /** 0..1 */
  progress: number;
  height?: number;
  from?: string;
  to?: string;
  track?: string;
  /** ჭერის ნიშნული — დღიური XP ლიმიტისთვის */
  marker?: number;
}

export function Bar({
  progress,
  height = 8,
  from = colors.accent,
  to = colors.streak,
  track = colors.surfaceHi,
  marker,
}: BarProps) {
  const value = useSharedValue(0);

  useEffect(() => {
    value.value = withSpring(Math.min(1, Math.max(0, progress)), spring);
  }, [progress, value]);

  const style = useAnimatedStyle(() => ({ width: `${value.value * 100}%` }));

  return (
    <View
      style={{
        height,
        borderRadius: radius.pill,
        backgroundColor: track,
        overflow: 'hidden',
        justifyContent: 'center',
      }}
    >
      <Animated.View style={[{ height: '100%' }, style]}>
        <LinearGradient
          colors={[from, to]}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={{ flex: 1, borderRadius: radius.pill }}
        />
      </Animated.View>

      {marker !== undefined && marker > 0 && marker < 1 ? (
        <View
          style={{
            position: 'absolute',
            left: `${marker * 100}%`,
            width: 2,
            height: '100%',
            backgroundColor: colors.bg,
            opacity: 0.9,
          }}
        />
      ) : null}
    </View>
  );
}
