import type { ReactNode } from 'react';
import { View } from 'react-native';
import { colors, radius, space, border } from '@/theme';
import { Text } from './Text';

interface StatTileProps {
  label: string;
  value: string | number;
  unit?: string;
  tint?: string;
  hint?: string;
  icon?: ReactNode;
}

/**
 * მნიშვნელობა დომინანტურია, ეტიკეტი ჩუმი — ვარჯიშის შემდეგ
 * მომხმარებელი ციფრს ეძებს, არა სათაურს.
 */
export function StatTile({ label, value, unit, tint = colors.text, hint, icon }: StatTileProps) {
  return (
    <View
      style={{
        flex: 1,
        minWidth: 96,
        backgroundColor: colors.surface,
        borderRadius: radius.md,
        borderWidth: border.hair,
        borderColor: colors.border,
        paddingHorizontal: space.md,
        paddingVertical: space.md,
        gap: space.xs,
      }}
    >
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.xs }}>
        {icon}
        <Text variant="overline" tone="muted" numberOfLines={1} style={{ flex: 1 }}>
          {label}
        </Text>
      </View>

      <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 3 }}>
        <Text variant="numeric" style={{ color: tint, fontSize: 24 }}>
          {value}
        </Text>
        {unit ? (
          <Text variant="caption" tone="muted">
            {unit}
          </Text>
        ) : null}
      </View>

      {hint ? (
        <Text variant="caption" tone="muted" numberOfLines={1}>
          {hint}
        </Text>
      ) : null}
    </View>
  );
}
