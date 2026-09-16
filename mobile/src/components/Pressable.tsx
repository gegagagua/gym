import { forwardRef } from 'react';
import { Pressable as RNPressable, type PressableProps, type ViewStyle } from 'react-native';
import * as Haptics from 'expo-haptics';
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from 'react-native-reanimated';
import { springSnappy } from '@/theme';

const AnimatedPressable = Animated.createAnimatedComponent(RNPressable);

export interface TapProps extends Omit<PressableProps, 'style'> {
  style?: ViewStyle | ViewStyle[];
  scaleTo?: number;
  haptic?: false | 'light' | 'medium' | 'heavy' | 'success' | 'warning';
}

const hapticFor = (h: Exclude<TapProps['haptic'], false | undefined>) => {
  switch (h) {
    case 'success':
      return () => Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    case 'warning':
      return () => Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    case 'heavy':
      return () => Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Heavy);
    case 'medium':
      return () => Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    default:
      return () => Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
  }
};

/**
 * ერთადერთი სენსორული ზედაპირი აპში. ყოველი შეხება იძლევა
 * ტაქტილურ პასუხს — ვარჯიშის დროს ტელეფონს ხშირად არ უყურებ.
 */
export const Tap = forwardRef<any, TapProps>(function Tap(
  { style, scaleTo = 0.965, haptic = 'light', onPressIn, onPressOut, disabled, ...rest },
  ref,
) {
  const scale = useSharedValue(1);
  const animated = useAnimatedStyle(() => ({ transform: [{ scale: scale.value }] }));

  return (
    <AnimatedPressable
      ref={ref}
      disabled={disabled}
      style={[style as ViewStyle, animated, disabled && { opacity: 0.45 }]}
      onPressIn={(e) => {
        scale.value = withSpring(scaleTo, springSnappy);
        if (haptic !== false) hapticFor(haptic)();
        onPressIn?.(e);
      }}
      onPressOut={(e) => {
        scale.value = withSpring(1, springSnappy);
        onPressOut?.(e);
      }}
      {...rest}
    />
  );
});
