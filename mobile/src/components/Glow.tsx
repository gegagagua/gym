import { useId } from 'react';
import { View, type ViewStyle } from 'react-native';
import Svg, { Defs, RadialGradient, Rect, Stop } from 'react-native-svg';

interface GlowProps {
  color: string;
  size?: number;
  opacity?: number;
  style?: ViewStyle;
}

/**
 * რბილი რადიალური ნათება — სიღრმის ერთადერთი წყარო შავ ფონზე.
 * BlurView-ს ვერიდებით: ის Android-ზე ძვირია და გამჭვირვალობის
 * არტეფაქტებს ტოვებს ვიდეო-ლუპებზე.
 */
export function Glow({ color, size = 320, opacity = 0.5, style }: GlowProps) {
  // ერთ ეკრანზე რამდენიმე ნათებაა — გრადიენტის id უნიკალური უნდა იყოს,
  // თორემ ყველა პირველის ფერს იღებს
  const id = `glow${useId().replace(/[^a-zA-Z0-9]/g, '')}`;

  return (
    <View pointerEvents="none" style={[{ position: 'absolute', width: size, height: size, opacity }, style]}>
      <Svg width={size} height={size}>
        <Defs>
          <RadialGradient id={id} cx="50%" cy="50%" r="50%">
            <Stop offset="0%" stopColor={color} stopOpacity={0.85} />
            <Stop offset="45%" stopColor={color} stopOpacity={0.28} />
            <Stop offset="100%" stopColor={color} stopOpacity={0} />
          </RadialGradient>
        </Defs>
        <Rect width={size} height={size} fill={`url(#${id})`} />
      </Svg>
    </View>
  );
}
