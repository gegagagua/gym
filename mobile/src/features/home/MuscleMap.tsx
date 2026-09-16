import { useEffect } from 'react';
import { View } from 'react-native';
import Animated, {
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withSpring,
} from 'react-native-reanimated';
import { Text } from '@/components';
import { colors, radius, space, spring, stagger } from '@/theme';

const LABELS: Record<string, string> = {
  chest: 'chest',
  lats: 'lats',
  biceps: 'biceps',
  triceps: 'triceps',
  shoulders: 'shoulders',
  core: 'core',
  obliques: 'obliques',
  quads: 'quads',
  glutes: 'glutes',
  hamstrings: 'hamstrings',
  calves: 'calves',
  forearms: 'forearms',
  rhomboids: 'rhomboids',
  hip_flexors: 'hip flexors',
  spine: 'spine',
};

/**
 * კუნთების დატვირთვა ჰორიზონტალური ზოლებით.
 *
 * ანატომიური სილუეტის ნაცვლად განზრახ ზოლებია: სილუეტი ლამაზია,
 * მაგრამ „რა დამრჩა" კითხვაზე პასუხს ცუდად აძლევს. ზოლები დალაგებულია
 * და დისბალანსი მაშინვე ჩანს.
 *
 * ზოლები ზემოდან ქვემოთ, რიგრიგობით ივსება — თვალი დისბალანსს
 * მოძრაობაში უფრო სწრაფად იჭერს, ვიდრე გაყინულ სურათზე.
 */
export function MuscleMap({ load }: { load: Record<string, number> }) {
  const entries = Object.entries(load).slice(0, 8);

  if (entries.length === 0) return null;

  return (
    <View style={{ gap: space.sm }}>
      {entries.map(([muscle, value], index) => (
        <View key={muscle} style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
          <Text variant="caption" tone="muted" style={{ width: 84 }} numberOfLines={1}>
            {LABELS[muscle] ?? muscle}
          </Text>

          <View
            style={{
              flex: 1,
              height: 6,
              borderRadius: radius.pill,
              backgroundColor: colors.surfaceHi,
              overflow: 'hidden',
            }}
          >
            <LoadBar value={value} index={index} />
          </View>
        </View>
      ))}
    </View>
  );
}

function LoadBar({ value, index }: { value: number; index: number }) {
  const width = useSharedValue(0);

  useEffect(() => {
    width.value = withDelay(index * stagger, withSpring(Math.min(1, Math.max(0, value)), spring));
  }, [value, index, width]);

  const animated = useAnimatedStyle(() => ({ width: `${width.value * 100}%` }));

  return (
    <Animated.View
      style={[
        {
          height: '100%',
          borderRadius: radius.pill,
          // მაღალი დატვირთვა ცეცხლისფერია, დაბალი — ცივი
          backgroundColor: value > 0.66 ? colors.streak : value > 0.33 ? colors.accent : colors.info,
        },
        animated,
      ]}
    />
  );
}
