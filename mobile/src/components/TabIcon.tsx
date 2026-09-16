import { useEffect } from 'react';
import { View, type ColorValue } from 'react-native';
import Svg, { Circle, Path, Rect } from 'react-native-svg';
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from 'react-native-reanimated';
import { colors, radius, spring } from '@/theme';

type Name = 'today' | 'library' | 'map' | 'league' | 'profile';

/**
 * ხატულები ხელით დახატულია — icon-პაკეტს ვერიდებით, რომ ვიზუალური
 * ენა ერთიანი იყოს: 1.8px შტრიხი, მრგვალი ბოლოები, გეომეტრიული ფორმები.
 *
 * აქტიური ტაბი ოდნავ ზემოთ ადის და მის თავზე ლაიმის ხაზი ჩნდება —
 * ერთადერთი „ჩართული" ინდიკატორი, რომელიც ხატულასთან არ კონკურირებს.
 */
export function TabIcon({ name, color, focused }: { name: Name; color: ColorValue; focused: boolean }) {
  const on = useSharedValue(focused ? 1 : 0);

  useEffect(() => {
    on.value = withSpring(focused ? 1 : 0, spring);
  }, [focused, on]);

  const lift = useAnimatedStyle(() => ({
    transform: [{ translateY: -2.5 * on.value }, { scale: 1 + 0.09 * on.value }],
  }));

  const marker = useAnimatedStyle(() => ({
    width: 4 + 12 * on.value,
    opacity: on.value,
  }));

  const width = focused ? 2.3 : 1.8;
  const common = { stroke: color as string, strokeWidth: width, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, fill: 'none' };

  return (
    <View style={{ alignItems: 'center', justifyContent: 'center' }}>
      <Animated.View
        style={[
          { height: 2.5, borderRadius: radius.pill, backgroundColor: colors.accent, marginBottom: 3 },
          marker,
        ]}
      />

      <Animated.View style={lift}>
    <Svg width={25} height={25} viewBox="0 0 24 24">
      {name === 'today' && (
        <>
          <Path d="M13 2 4.5 13.5H11l-.8 8.5L19.5 10H13z" {...common} fill={focused ? (color as string) : 'none'} />
        </>
      )}

      {name === 'library' && (
        <>
          <Rect x="3" y="4" width="5" height="16" rx="1.6" {...common} />
          <Rect x="10" y="4" width="5" height="16" rx="1.6" {...common} fill={focused ? (color as string) : 'none'} />
          <Path d="M18 6.5 21 17.5" {...common} />
        </>
      )}

      {name === 'map' && (
        <>
          <Path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z" {...common} />
          <Circle cx="12" cy="10" r="2.6" {...common} fill={focused ? (color as string) : 'none'} />
        </>
      )}

      {name === 'league' && (
        <>
          <Path d="M6 4h12v4a6 6 0 0 1-12 0z" {...common} fill={focused ? (color as string) : 'none'} />
          <Path d="M6 6H3.5v1.5A3.5 3.5 0 0 0 6 11M18 6h2.5v1.5A3.5 3.5 0 0 1 18 11" {...common} />
          <Path d="M9.5 20h5M12 14v6" {...common} />
        </>
      )}

      {name === 'profile' && (
        <>
          <Circle cx="12" cy="8" r="3.6" {...common} fill={focused ? (color as string) : 'none'} />
          <Path d="M4.5 20a7.5 7.5 0 0 1 15 0" {...common} />
        </>
      )}
    </Svg>
      </Animated.View>
    </View>
  );
}
