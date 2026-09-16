import { memo, useEffect, useRef, useState, type ReactNode } from 'react';
import { View, type StyleProp, type ViewStyle } from 'react-native';
import Animated, {
  cancelAnimation,
  Extrapolation,
  interpolate,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from 'react-native-reanimated';
import { LinearGradient } from 'expo-linear-gradient';
import { colors, duration, easing, radius, spring, springBouncy, stagger as staggerStep } from '@/theme';

/* ------------------------------------------------------------------ *
 *  შემოსვლა
 * ------------------------------------------------------------------ */

export interface RevealProps {
  children: ReactNode;
  /** კასკადში პოზიცია — შეყოვნება ავტომატურად ითვლება */
  index?: number;
  /** დამატებითი შეყოვნება მილიწამებში */
  delay?: number;
  /** საიდან შემოდის: ქვემოდან (default), ზემოდან, გვერდიდან, ან ადგილზე */
  from?: 'bottom' | 'top' | 'left' | 'right' | 'none';
  distance?: number;
  /** მასშტაბიც იზრდება — მხოლოდ მთავარი ბლოკებისთვის */
  zoom?: boolean;
  style?: StyleProp<ViewStyle>;
}

/**
 * ბლოკის შემოსვლა. ერთი კასკადი ერთ ეკრანზე: ბლოკები `index`-ის
 * მიხედვით რიგრიგობით ჩნდება, რაც თვალს კითხვის თანმიმდევრობას აძლევს.
 * უმოძრაო ვარიანტისთვის `from="none"`.
 */
export const Reveal = memo(function Reveal({
  children,
  index = 0,
  delay = 0,
  from = 'bottom',
  distance = 14,
  zoom = false,
  style,
}: RevealProps) {
  const progress = useSharedValue(0);
  const wait = delay + index * staggerStep;

  useEffect(() => {
    progress.value = withDelay(wait, withTiming(1, { duration: duration.slow, easing: easing.out }));

    return () => cancelAnimation(progress);
  }, [progress, wait]);

  const animated = useAnimatedStyle(() => {
    const shift = interpolate(progress.value, [0, 1], [distance, 0], Extrapolation.CLAMP);

    return {
      opacity: progress.value,
      transform: [
        ...(from === 'bottom' ? [{ translateY: shift }] : []),
        ...(from === 'top' ? [{ translateY: -shift }] : []),
        ...(from === 'left' ? [{ translateX: -shift }] : []),
        ...(from === 'right' ? [{ translateX: shift }] : []),
        ...(zoom ? [{ scale: interpolate(progress.value, [0, 1], [0.94, 1], Extrapolation.CLAMP) }] : []),
      ],
    };
  });

  return <Animated.View style={[style, animated]}>{children}</Animated.View>;
});

/* ------------------------------------------------------------------ *
 *  ციფრის ათვლა
 * ------------------------------------------------------------------ */

interface CountUpProps {
  value: number;
  /** რენდერი — ტიპოგრაფია გამომძახებელს რჩება */
  render: (display: number) => ReactNode;
  duration?: number;
  delay?: number;
  decimals?: number;
}

/**
 * ციფრის ათვლა 0-დან (ან წინა მნიშვნელობიდან) მიმდინარემდე.
 *
 * მიზანმიმართულად JS-ის მარყუჟია და არა worklet: ერთი ფოთოლი-`Text`-ის
 * გადახატვა იაფია, ხოლო `TextInput`-ის `text`-ის ანიმაცია პლატფორმებს
 * შორის არასტაბილურია. XP-ის რიცხვი აპში 2-3 ადგილას თუ ითვლება.
 */
export const CountUp = memo(function CountUp({
  value,
  render,
  duration: ms = 900,
  delay = 0,
  decimals = 0,
}: CountUpProps) {
  const [display, setDisplay] = useState(0);
  const from = useRef(0);
  const frame = useRef<number | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const start = from.current;
    const delta = value - start;

    if (delta === 0) {
      setDisplay(value);
      return;
    }

    const run = () => {
      const began = Date.now();

      const step = () => {
        const t = Math.min(1, (Date.now() - began) / ms);
        // expo-out — იგივე მრუდი, რაც შემოსვლის ანიმაციებს
        const eased = 1 - Math.pow(1 - t, 4);
        const next = start + delta * eased;

        setDisplay(decimals > 0 ? Number(next.toFixed(decimals)) : Math.round(next));

        if (t < 1) {
          frame.current = requestAnimationFrame(step);
        } else {
          from.current = value;
        }
      };

      step();
    };

    timer.current = setTimeout(run, delay);

    return () => {
      if (frame.current) cancelAnimationFrame(frame.current);
      if (timer.current) clearTimeout(timer.current);
      from.current = value;
    };
  }, [value, ms, delay, decimals]);

  return <>{render(display)}</>;
});

/* ------------------------------------------------------------------ *
 *  განმეორებადი მოძრაობა
 * ------------------------------------------------------------------ */

/** სუნთქვა — ცოცხალი სტატუსი: check-in, მიმდინარე hold, streak */
export function Pulse({
  children,
  active = true,
  to = 1.06,
  period = 1600,
  style,
}: {
  children: ReactNode;
  active?: boolean;
  to?: number;
  period?: number;
  style?: StyleProp<ViewStyle>;
}) {
  const scale = useSharedValue(1);

  useEffect(() => {
    if (!active) {
      cancelAnimation(scale);
      scale.value = withTiming(1, { duration: duration.fast });
      return;
    }

    scale.value = withRepeat(
      withSequence(
        withTiming(to, { duration: period / 2, easing: easing.inOut }),
        withTiming(1, { duration: period / 2, easing: easing.inOut }),
      ),
      -1,
      false,
    );

    return () => cancelAnimation(scale);
  }, [active, to, period, scale]);

  const animated = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }] }));

  return <Animated.View style={[style, animated]}>{children}</Animated.View>;
}

/** ნათების სუნთქვა — ფონის ატმოსფერო ნელა „ამოისუნთქავს“ */
export function Breathe({
  children,
  from = 0.75,
  to = 1,
  period = 5200,
  style,
}: {
  children: ReactNode;
  from?: number;
  to?: number;
  period?: number;
  style?: StyleProp<ViewStyle>;
}) {
  const value = useSharedValue(from);

  useEffect(() => {
    value.value = withRepeat(
      withSequence(
        withTiming(to, { duration: period / 2, easing: easing.inOut }),
        withTiming(from, { duration: period / 2, easing: easing.inOut }),
      ),
      -1,
      false,
    );

    return () => cancelAnimation(value);
  }, [from, to, period, value]);

  const animated = useAnimatedStyle(() => ({ opacity: value.value }));

  return (
    <Animated.View pointerEvents="none" style={[style, animated]}>
      {children}
    </Animated.View>
  );
}

/** მიღწევის „პოპი“ — მნიშვნელობა შეიცვალა და ეს ღირს შენიშვნად */
export function Pop({
  children,
  trigger,
  style,
}: {
  children: ReactNode;
  /** ცვლილება იწვევს ანიმაციას */
  trigger: unknown;
  style?: StyleProp<ViewStyle>;
}) {
  const scale = useSharedValue(1);
  const first = useRef(true);

  useEffect(() => {
    if (first.current) {
      first.current = false;
      return;
    }

    scale.value = withSequence(withTiming(1.14, { duration: 110, easing: easing.out }), withSpring(1, springBouncy));
  }, [trigger, scale]);

  const animated = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }] }));

  return <Animated.View style={[style, animated]}>{children}</Animated.View>;
}

/* ------------------------------------------------------------------ *
 *  ჩატვირთვა
 * ------------------------------------------------------------------ */

const fill: ViewStyle = { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 };

/**
 * ჩატვირთვის ჩონჩხი. ცარიელი ეკრანი ან spinner მომხმარებელს
 * ინტერფეისის სტრუქტურას არ აჩვენებს — აქ ის უკვე იქ არის და ივსება.
 */
export function Skeleton({
  height = 16,
  width = '100%',
  round = radius.sm,
  style,
}: {
  height?: number;
  width?: number | `${number}%`;
  round?: number;
  style?: StyleProp<ViewStyle>;
}) {
  const shift = useSharedValue(-1);

  useEffect(() => {
    shift.value = withRepeat(withTiming(1, { duration: 1400, easing: easing.inOut }), -1, false);

    return () => cancelAnimation(shift);
  }, [shift]);

  const animated = useAnimatedStyle(() => ({
    transform: [{ translateX: `${shift.value * 100}%` }],
  }));

  return (
    <View
      style={[
        { height, width, borderRadius: round, backgroundColor: colors.surface, overflow: 'hidden' },
        style,
      ]}
    >
      <Animated.View style={[fill, animated]}>
        <LinearGradient
          colors={['transparent', colors.surfaceHi, 'transparent']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 0 }}
          style={{ flex: 1 }}
        />
      </Animated.View>
    </View>
  );
}

/** რამდენიმე ჩონჩხი-რიგი — სიების ჩატვირთვისთვის */
export function SkeletonRows({ rows = 4, height = 74, gap = 8 }: { rows?: number; height?: number; gap?: number }) {
  return (
    <View style={{ gap }}>
      {Array.from({ length: rows }, (_, index) => (
        <Reveal key={index} index={index} distance={8}>
          <Skeleton height={height} round={radius.md} />
        </Reveal>
      ))}
    </View>
  );
}

/* ------------------------------------------------------------------ *
 *  გამოჩენა/გაქრობა ადგილზე
 * ------------------------------------------------------------------ */

/** მდგომარეობის შეცვლა ერთ ადგილას — ძველი ქრება, ახალი ჩნდება */
export function Fade({
  children,
  visible,
  style,
}: {
  children: ReactNode;
  visible: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const value = useSharedValue(visible ? 1 : 0);

  useEffect(() => {
    value.value = withTiming(visible ? 1 : 0, {
      duration: visible ? duration.base : duration.fast,
      easing: visible ? easing.out : easing.in,
    });
  }, [visible, value]);

  const animated = useAnimatedStyle(() => ({ opacity: value.value }));

  return (
    <Animated.View pointerEvents={visible ? 'auto' : 'none'} style={[style, animated]}>
      {children}
    </Animated.View>
  );
}

/** ქვემოდან ამოსული ზოლი — კალათა, მოქმედების პანელი */
export function SlideUp({
  children,
  visible,
  style,
}: {
  children: ReactNode;
  visible: boolean;
  style?: StyleProp<ViewStyle>;
}) {
  const value = useSharedValue(visible ? 1 : 0);

  useEffect(() => {
    value.value = visible
      ? withSpring(1, spring)
      : withTiming(0, { duration: duration.fast, easing: easing.in });
  }, [visible, value]);

  const animated = useAnimatedStyle(() => ({
    opacity: value.value,
    transform: [
      { translateY: interpolate(value.value, [0, 1], [40, 0], Extrapolation.CLAMP) },
      { scale: interpolate(value.value, [0, 1], [0.97, 1], Extrapolation.CLAMP) },
    ],
  }));

  return (
    <Animated.View pointerEvents={visible ? 'auto' : 'none'} style={[style, animated]}>
      {children}
    </Animated.View>
  );
}
