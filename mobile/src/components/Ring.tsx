import { useEffect, useId } from 'react';
import { View } from 'react-native';
import Svg, { Circle, Defs, LinearGradient, Stop } from 'react-native-svg';
import Animated, {
  useAnimatedProps,
  useDerivedValue,
  useSharedValue,
  withSpring,
} from 'react-native-reanimated';
import { colors, spring } from '@/theme';

const AnimatedCircle = Animated.createAnimatedComponent(Circle);

interface RingProps {
  /** 0..1 */
  progress: number;
  size?: number;
  stroke?: number;
  from?: string;
  to?: string;
  track?: string;
  children?: React.ReactNode;
}

/**
 * გრადიენტული პროგრეს-რგოლი. ორ ფერს შორის გადასვლა აჩვენებს
 * „რამდენად ცხელია" პროგრესი — მთლიანად შევსებული რგოლი ლაიმიდან
 * ცეცხლისფერში გადადის.
 */
export function Ring({
  progress,
  size = 132,
  stroke = 10,
  from = colors.accent,
  to = colors.streak,
  track = colors.surfaceHi,
  children,
}: RingProps) {
  const gradientId = `ring${useId().replace(/[^a-zA-Z0-9]/g, '')}`;
  const radius = (size - stroke) / 2;
  const circumference = 2 * Math.PI * radius;
  const value = useSharedValue(0);

  useEffect(() => {
    value.value = withSpring(Math.min(1, Math.max(0, progress)), spring);
  }, [progress, value]);

  const dashOffset = useDerivedValue(() => circumference * (1 - value.value));
  const animatedProps = useAnimatedProps(() => ({ strokeDashoffset: dashOffset.value }));

  return (
    <View style={{ width: size, height: size, alignItems: 'center', justifyContent: 'center' }}>
      <Svg width={size} height={size} style={{ position: 'absolute', transform: [{ rotate: '-90deg' }] }}>
        <Defs>
          <LinearGradient id={gradientId} x1="0" y1="0" x2="1" y2="1">
            <Stop offset="0%" stopColor={from} />
            <Stop offset="100%" stopColor={to} />
          </LinearGradient>
        </Defs>
        <Circle cx={size / 2} cy={size / 2} r={radius} stroke={track} strokeWidth={stroke} fill="none" />
        <AnimatedCircle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke={`url(#${gradientId})`}
          strokeWidth={stroke}
          strokeLinecap="round"
          fill="none"
          strokeDasharray={circumference}
          animatedProps={animatedProps}
        />
      </Svg>
      {children}
    </View>
  );
}
