import type { ReactNode } from 'react';
import { View, type ViewStyle } from 'react-native';
import { colors, radius, border, space } from '@/theme';
import { Glow } from './Glow';

export interface CardProps {
  children: ReactNode;
  /** ფერადი ნათება ბარათის უკან — აქცენტის დასმისთვის */
  accent?: string;
  padded?: boolean;
  style?: ViewStyle | ViewStyle[];
  radiusKey?: keyof typeof radius;
}

export function Card({ children, accent, padded = true, style, radiusKey = 'lg' }: CardProps) {
  return (
    <View
      style={[
        {
          backgroundColor: colors.surface,
          borderRadius: radius[radiusKey],
          borderWidth: border.hair,
          borderColor: accent ? `${accent}33` : colors.border,
          padding: padded ? space.base : 0,
          overflow: 'hidden',
        },
        style as ViewStyle,
      ]}
    >
      {accent ? <Glow color={accent} size={260} opacity={0.22} style={{ top: -150, right: -90 }} /> : null}
      {children}
    </View>
  );
}
