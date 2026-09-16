import { View } from 'react-native';
import { Text } from '@/components';
import { colors, radius, space, border } from '@/theme';

/**
 * მოედნის მარკერი. ზომა და ფერი გადმოსცემს „რამდენად ცოცხალია":
 * ვინც ახლა ვარჯიშობს — ლაიმისფერი და დათვლილი, ცარიელი — ჩუმი.
 */
export function SpotMarker({ activeNow, equipmentCount }: { activeNow: number; equipmentCount: number }) {
  const live = activeNow > 0;

  return (
    <View style={{ alignItems: 'center' }}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          gap: space.xs,
          paddingHorizontal: live ? space.sm : space.xs + 2,
          paddingVertical: space.xs,
          borderRadius: radius.pill,
          backgroundColor: live ? colors.accent : colors.surface,
          borderWidth: border.thin,
          borderColor: live ? colors.accent : colors.borderStrong,
        }}
      >
        <View
          style={{
            width: 6,
            height: 6,
            borderRadius: 3,
            backgroundColor: live ? colors.onAccent : colors.textMuted,
          }}
        />
        <Text
          variant="caption"
          style={{ color: live ? colors.onAccent : colors.textSecondary, fontSize: 10.5 }}
        >
          {live ? activeNow : equipmentCount}
        </Text>
      </View>

      {/* პატარა კუდი — მარკერი კონკრეტულ წერტილს უნდა უთითებდეს */}
      <View
        style={{
          width: 2,
          height: 7,
          backgroundColor: live ? colors.accent : colors.borderStrong,
        }}
      />
    </View>
  );
}
