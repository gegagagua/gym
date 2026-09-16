import type { ReactNode } from 'react';
import { ActivityIndicator, View, type ViewStyle } from 'react-native';
import { colors, radius, space, border, type as typeScale } from '@/theme';
import { Tap, type TapProps } from './Pressable';
import { Text } from './Text';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

export interface ButtonProps extends Omit<TapProps, 'children' | 'style'> {
  title: string;
  variant?: Variant;
  size?: Size;
  loading?: boolean;
  icon?: ReactNode;
  full?: boolean;
  style?: ViewStyle;
}

const heights: Record<Size, number> = { sm: 40, md: 50, lg: 58 };
const padding: Record<Size, number> = { sm: space.base, md: space.lg, lg: space.xl };

export function Button({
  title,
  variant = 'primary',
  size = 'md',
  loading,
  icon,
  full = true,
  disabled,
  style,
  ...rest
}: ButtonProps) {
  const surface: Record<Variant, ViewStyle> = {
    primary: { backgroundColor: colors.accent },
    secondary: { backgroundColor: colors.surfaceHi, borderWidth: border.hair, borderColor: colors.borderStrong },
    ghost: { backgroundColor: 'transparent' },
    danger: { backgroundColor: 'transparent', borderWidth: border.thin, borderColor: `${colors.danger}66` },
  };
  const tone = {
    primary: 'inverse',
    secondary: 'default',
    ghost: 'secondary',
    danger: 'danger',
  } as const;

  return (
    <Tap
      disabled={disabled || loading}
      haptic={variant === 'primary' ? 'medium' : 'light'}
      style={[
        {
          height: heights[size],
          borderRadius: radius.pill,
          paddingHorizontal: padding[size],
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'center',
          gap: space.sm,
          alignSelf: full ? 'stretch' : 'flex-start',
        },
        surface[variant],
        style as ViewStyle,
      ]}
      {...rest}
    >
      {loading ? (
        <ActivityIndicator color={variant === 'primary' ? colors.onAccent : colors.text} />
      ) : (
        <>
          {icon ? <View>{icon}</View> : null}
          {/*
            ქართული ტექსტი ღილაკზე ხშირად სცილდება — ერთ ხაზში ვინახავთ
            და საჭიროებისას ვამცირებთ, ვიდრე ორ ხაზად გავტეხავდეთ.
          */}
          <Text
            tone={tone[variant]}
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.82}
            style={{
              ...typeScale.subheading,
              fontFamily: 'NotoSansGeorgian_700Bold',
              flexShrink: 1,
              textAlign: 'center',
            }}
          >
            {title}
          </Text>
        </>
      )}
    </Tap>
  );
}
