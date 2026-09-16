import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { Card, Text, Ring } from '@/components';
import { colors, space } from '@/theme';

interface StreakCardProps {
  days: number;
  coveredToday: boolean;
  longest: number;
}

/**
 * Streak-ის მამრავლის საფეხურები (სპეც. 6.2) ვიზუალურად ჩანს —
 * მომხმარებელი ხედავს, სად არის და რამდენი დარჩა შემდეგ ბონუსამდე.
 */
const TIERS = [3, 7, 14, 30];
const MULTIPLIERS: Record<number, string> = { 3: '×1.05', 7: '×1.10', 14: '×1.15', 30: '×1.25' };

export function StreakCard({ days, coveredToday, longest }: StreakCardProps) {
  const { t } = useTranslation();

  const nextTier = TIERS.find((tier) => days < tier);
  const previousTier = [...TIERS].reverse().find((tier) => days >= tier) ?? 0;
  const progress = nextTier ? (days - previousTier) / (nextTier - previousTier) : 1;

  const currentMultiplier = previousTier ? MULTIPLIERS[previousTier] : '×1.00';

  return (
    <Card accent={colors.streak}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.lg }}>
        <Ring progress={progress} size={92} stroke={8} from={colors.streak} to={colors.accent}>
          <Text variant="display" style={{ fontSize: 30, color: colors.streak }}>
            {days}
          </Text>
        </Ring>

        <View style={{ flex: 1, gap: space.xs }}>
          <Text variant="overline" tone="muted">
            {t('home.streakDays')}
          </Text>

          <Text variant="heading" style={{ color: coveredToday ? colors.success : colors.warning }}>
            {coveredToday ? t('home.streakSafe') : t('home.streakRisk')}
          </Text>

          <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm, marginTop: space.xs }}>
            <Text variant="numeric" style={{ color: colors.streak, fontSize: 15 }}>
              {currentMultiplier}
            </Text>
            {nextTier ? (
              <Text variant="caption" tone="muted">
                → {MULTIPLIERS[nextTier]} · {nextTier - days} {t('common.level') === 'level' ? 'd' : ''}
              </Text>
            ) : null}
          </View>

          {longest > days ? (
            <Text variant="caption" tone="muted">
              {t('profile.longestStreak')}: {longest}
            </Text>
          ) : null}
        </View>
      </View>
    </Card>
  );
}
