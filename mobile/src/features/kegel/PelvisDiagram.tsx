import Svg, { Path, Circle } from 'react-native-svg';
import { View } from 'react-native';
import { Squeeze } from '@/components';
import { colors, zoneTheme } from '@/theme';

/**
 * სქემა, არა ანატომიური ატლასი: მენჯის „თასი" და ფსკერის კუნთი-ჰამაკი.
 * შეკუმშვისას ჰამაკი მაღლა იწევა — ზუსტად ის, რაც უნდა იგრძნოს.
 */
export function PelvisDiagram({ level, ms, size = 220 }: { level: number; ms: number; size?: number }) {
  const tint = zoneTheme.pelvic_floor;
  const h = size * 0.72;

  return (
    <View style={{ width: size, height: h }}>
      <Svg width={size} height={h} viewBox="0 0 220 158" style={{ position: 'absolute' }}>
        {/* მენჯის ძვლები */}
        <Path
          d="M18 20 C 30 70, 58 112, 92 126 M202 20 C 190 70, 162 112, 128 126"
          stroke={colors.borderStrong}
          strokeWidth={10}
          strokeLinecap="round"
          fill="none"
        />
        <Circle cx={110} cy={138} r={9} fill={colors.borderStrong} />
      </Svg>

      <Squeeze level={level} ms={ms} mode="lift" lift={28} style={{ position: 'absolute', left: 0, right: 0, top: 0, bottom: 0 }}>
        <Svg width={size} height={h} viewBox="0 0 220 158">
          {/* ფსკერის კუნთი */}
          <Path
            d="M40 76 C 70 128, 150 128, 180 76"
            stroke={tint}
            strokeWidth={9}
            strokeLinecap="round"
            fill="none"
            opacity={0.95}
          />
          <Path d="M58 92 C 84 118, 136 118, 162 92" stroke={tint} strokeWidth={3} fill="none" opacity={0.35} />
        </Svg>
      </Squeeze>
    </View>
  );
}
