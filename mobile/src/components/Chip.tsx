import { View } from 'react-native';
import { colors, radius, space, border } from '@/theme';
import { Tap } from './Pressable';
import { Text } from './Text';

interface ChipProps {
  label: string;
  selected?: boolean;
  tint?: string;
  onPress?: () => void;
  compact?: boolean;
}

export function Chip({ label, selected, tint = colors.accent, onPress, compact }: ChipProps) {
  const content = (
    <View
      style={{
        paddingHorizontal: compact ? space.md : space.base,
        paddingVertical: compact ? space.xs + 2 : space.sm,
        borderRadius: radius.pill,
        backgroundColor: selected ? tint : colors.surface,
        borderWidth: border.hair,
        borderColor: selected ? tint : colors.border,
      }}
    >
      <Text
        variant={compact ? 'caption' : 'label'}
        style={{ color: selected ? colors.onAccent : colors.textSecondary }}
        numberOfLines={1}
      >
        {label}
      </Text>
    </View>
  );

  return onPress ? (
    <Tap onPress={onPress} scaleTo={0.94}>
      {content}
    </Tap>
  ) : (
    content
  );
}
