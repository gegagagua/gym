import type { ReactNode } from 'react';
import { View } from 'react-native';
import { space } from '@/theme';
import { Text } from './Text';

export function SectionHeader({
  title,
  action,
  tone = 'muted',
}: {
  title: string;
  action?: ReactNode;
  tone?: 'muted' | 'accent';
}) {
  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginTop: space.xl,
        marginBottom: space.md,
      }}
    >
      <Text variant="overline" tone={tone}>
        {title}
      </Text>
      {action}
    </View>
  );
}
