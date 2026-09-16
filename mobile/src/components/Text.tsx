import { Text as RNText, type TextProps as RNTextProps, type TextStyle } from 'react-native';
import { colors, type as typeScale } from '@/theme';

type Variant = keyof typeof typeScale;
type Tone = 'default' | 'secondary' | 'muted' | 'accent' | 'rank' | 'streak' | 'danger' | 'success' | 'inverse';

const tones: Record<Tone, string> = {
  default: colors.text,
  secondary: colors.textSecondary,
  muted: colors.textMuted,
  accent: colors.accent,
  rank: colors.rank,
  streak: colors.streak,
  danger: colors.danger,
  success: colors.success,
  inverse: colors.onAccent,
};

export interface TextProps extends RNTextProps {
  variant?: Variant;
  tone?: Tone;
  center?: boolean;
  style?: TextStyle | TextStyle[];
}

/**
 * ერთადერთი ტექსტის კომპონენტი აპში — ნედლი <Text> არსად არ გამოიყენება.
 * ეს გვაძლევს გარანტიას, რომ ქართული ფონტი ყველგან ჩაირთვება და
 * ტიპოგრაფიული სკალა არ დაირღვევა.
 */
export function Text({ variant = 'body', tone = 'default', center, style, ...rest }: TextProps) {
  return (
    <RNText
      {...rest}
      style={[
        typeScale[variant] as TextStyle,
        { color: tones[tone] },
        center && { textAlign: 'center' },
        style as TextStyle,
      ]}
    />
  );
}
