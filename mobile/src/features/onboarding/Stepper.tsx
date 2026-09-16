import { useEffect, useRef } from 'react';
import { View } from 'react-native';
import { colors, radius, space, border } from '@/theme';
import { Tap, Text, Pop } from '@/components';

interface StepperProps {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  step?: number;
  unit?: string;
  tint?: string;
}

/**
 * დიდი ციფრი, დიდი ღილაკები. ვარჯიშის შემდეგ ხელები კანკალებს —
 * პატარა სამიზნეები აქ არ მუშაობს. ღილაკის შენარჩუნება ციფრს აჩქარებს:
 * 60 აჭიმის შეყვანა 60 შეხებით არავის უნდა.
 */
export function Stepper({
  value,
  onChange,
  min = 0,
  max = 999,
  step = 1,
  unit,
  tint = colors.accent,
}: StepperProps) {
  const repeat = useRef<ReturnType<typeof setInterval> | null>(null);
  const held = useRef(value);
  held.current = value;

  useEffect(() => () => (repeat.current ? clearInterval(repeat.current) : undefined), []);

  const clamp = (next: number) => Math.min(max, Math.max(min, next));

  const button = (label: string, delta: number) => (
    <Tap
      onPress={() => onChange(clamp(value + delta))}
      onLongPress={() => {
        repeat.current = setInterval(() => onChange(clamp(held.current + delta)), 80);
      }}
      onPressOut={() => {
        if (repeat.current) clearInterval(repeat.current);
        repeat.current = null;
      }}
      delayLongPress={300}
      haptic="medium"
      scaleTo={0.92}
      style={{
        width: 60,
        height: 60,
        borderRadius: radius.md,
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: colors.surfaceHi,
        borderWidth: border.hair,
        borderColor: colors.border,
      }}
    >
      <Text variant="title" tone="secondary">
        {label}
      </Text>
    </Tap>
  );

  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: space.base }}>
      {button('−', -step)}

      <View style={{ flex: 1, alignItems: 'center' }}>
        <Pop trigger={value}>
          <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: space.xs }}>
            <Text variant="display" style={{ color: tint }}>
              {value}
            </Text>
            {unit ? (
              <Text variant="label" tone="muted">
                {unit}
              </Text>
            ) : null}
          </View>
        </Pop>
      </View>

      {button('+', step)}
    </View>
  );
}
