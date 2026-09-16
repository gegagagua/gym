import { useMemo } from 'react';
import { View } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import { colors } from '@/theme';

/**
 * მსუბუქი „ბეტონის“ ტექსტურა. feTurbulence-ს ვერიდებით — react-native-svg-ში
 * ფილტრები Android-ზე არასტაბილურია; ამის ნაცვლად დეტერმინისტული
 * წერტილოვანი ბადეა, რომელიც ერთხელ ითვლება და ქეშირდება.
 */
export function Grain({ intensity = 0.035, dots = 140, size = 400 }: { intensity?: number; dots?: number; size?: number }) {
  const points = useMemo(() => {
    // LCG — Math.random-ის გარეშე, რომ რენდერები იდენტური იყოს
    let seed = 991;
    const next = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
    return Array.from({ length: dots }, () => ({
      x: next() * size,
      y: next() * size,
      r: 0.4 + next() * 0.9,
    }));
  }, [dots, size]);

  return (
    <View pointerEvents="none" style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, opacity: intensity }}>
      <Svg width="100%" height="100%" viewBox={`0 0 ${size} ${size}`} preserveAspectRatio="none">
        {points.map((p, i) => (
          <Circle key={i} cx={p.x} cy={p.y} r={p.r} fill={colors.text} />
        ))}
      </Svg>
    </View>
  );
}
