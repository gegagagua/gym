import type { ReactNode } from 'react';
import { ScrollView, View, type ViewStyle, RefreshControl } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, gutter, space } from '@/theme';
import { Glow } from './Glow';
import { Grain } from './Grain';

interface ScreenProps {
  children: ReactNode;
  /** ეკრანის ზედა ნაწილში ფერადი ატმოსფერო — თითოეულ ტაბს თავისი აქვს */
  ambient?: string;
  scroll?: boolean;
  padded?: boolean;
  refreshing?: boolean;
  onRefresh?: () => void;
  contentStyle?: ViewStyle;
  footer?: ReactNode;
}

export function Screen({
  children,
  ambient = colors.accent,
  scroll = true,
  padded = true,
  refreshing,
  onRefresh,
  contentStyle,
  footer,
}: ScreenProps) {
  const insets = useSafeAreaInsets();

  // scroll=false-ზე body-ს სრული სიმაღლე სჭირდება, თორემ შვილების
  // justifyContent/flex ვერაფერზე გაიწელება და კონტენტი ზემოთ იკუმშება.
  const body = (
    <View style={[!scroll && { flex: 1 }, padded && { paddingHorizontal: gutter }, contentStyle]}>
      {children}
    </View>
  );

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      {/* ატმოსფერო: ერთი დიდი ნათება ზემოთ + ბეტონის მარცვალი მთელ ეკრანზე */}
      <Glow color={ambient} size={520} opacity={0.30} style={{ top: -300, left: -120 }} />
      <Grain />

      {scroll ? (
        <ScrollView
          contentContainerStyle={{
            paddingTop: insets.top + space.sm,
            paddingBottom: insets.bottom + space.huge,
          }}
          showsVerticalScrollIndicator={false}
          refreshControl={
            onRefresh ? (
              <RefreshControl
                refreshing={!!refreshing}
                onRefresh={onRefresh}
                tintColor={colors.accent}
                colors={[colors.accent]}
                progressBackgroundColor={colors.surface}
              />
            ) : undefined
          }
        >
          {body}
        </ScrollView>
      ) : (
        <View style={{ flex: 1, paddingTop: insets.top + space.sm }}>{body}</View>
      )}

      {footer ? (
        <View
          style={{
            paddingHorizontal: gutter,
            paddingBottom: insets.bottom + space.md,
            paddingTop: space.md,
            backgroundColor: colors.bg,
            borderTopWidth: 0.5,
            borderTopColor: colors.border,
          }}
        >
          {footer}
        </View>
      ) : null}
    </View>
  );
}
