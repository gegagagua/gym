import { View, ScrollView, Linking } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Text, Card, Button, Chip, Tap, Glow, Grain, MediaLoop, Reveal, Skeleton, Pop } from '@/components';
import { colors, forceTheme, gutter, space } from '@/theme';
import { exercises as exercisesApi } from '@/api/endpoints';
import { resolveLoop, requiresAttribution } from '@/lib/media';
import { useFreestyle, FREESTYLE_MIN_EXERCISES } from '@/store/freestyle';

export default function ExerciseDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const { data } = useQuery({
    queryKey: ['exercise', id],
    queryFn: () => exercisesApi.get(Number(id)),
    enabled: !!id,
  });

  const exercise = data?.data;
  const tint = exercise ? (forceTheme[exercise.force] ?? colors.accent) : colors.accent;

  // ლუპი უხმოდ და უსასრულოდ — ეს ინსტრუქციაა, არა ვიდეო-კონტენტი
  const loop = resolveLoop(exercise?.media);

  const basket = useFreestyle();
  const inBasket = exercise ? basket.has(exercise.id) : false;

  const asList = (value: unknown): string[] =>
    Array.isArray(value) ? value : value && typeof value === 'object' ? Object.values(value as object) : [];

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={tint} size={480} opacity={0.28} style={{ top: -260, right: -140 }} />
      <Grain />

      <ScrollView
        contentContainerStyle={{ paddingTop: insets.top + space.sm, paddingBottom: insets.bottom + space.huge }}
        showsVerticalScrollIndicator={false}
      >
        <View style={{ paddingHorizontal: gutter, gap: space.base }}>
          <Reveal from="top" distance={8} style={{ alignSelf: 'flex-start' }}>
            <Tap onPress={() => router.back()}>
              <Text variant="label" tone="muted">
                ← {t('common.back')}
              </Text>
            </Tap>
          </Reveal>

          <Reveal index={1} zoom distance={12}>
          <MediaLoop
            source={loop}
            tint={tint}
            aspectRatio={1}
            initials={exercise?.name ?? ''}
            emptyLabel={exercise ? t('library.noLoop') : t('common.loading')}
          />
          </Reveal>

          {/* ატრიბუცია იქვე, სადაც კადრი ჩანს — CC BY-SA ამას ითხოვს (სპეც. 13.1) */}
          {requiresAttribution(loop) ? (
            <Reveal index={2}>
            <Tap
              onPress={() => loop?.sourceUrl && Linking.openURL(loop.sourceUrl)}
              disabled={!loop?.sourceUrl}
            >
              <Text variant="caption" tone="muted">
                {loop?.still ? `${t('library.stillFrame')} · ` : ''}
                {loop?.credit}
              </Text>
            </Tap>
            </Reveal>
          ) : null}

          <Reveal index={3} style={{ gap: space.sm }}>
            {exercise ? (
              <Text variant="title">{exercise.name}</Text>
            ) : (
              <Skeleton height={28} width="70%" />
            )}
            {exercise?.short_desc ? (
              <Text variant="body" tone="secondary">
                {exercise.short_desc}
              </Text>
            ) : null}
          </Reveal>

          <Reveal index={4} style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
            {exercise ? <Chip label={t(`library.force_${exercise.force}`)} tint={tint} selected compact /> : null}
            {exercise ? <Chip label={`k ${exercise.difficulty_coef.toFixed(1)}`} compact /> : null}
            {exercise ? <Chip label={`L${exercise.level_min}–${exercise.level_max}`} compact /> : null}
            {exercise?.equipment?.map((tag) => (
              <Chip key={tag} label={t(`map.eq_${tag}`, { defaultValue: tag })} compact />
            ))}
          </Reveal>

          {exercise?.is_skill_unlock ? (
            <Reveal index={5} zoom>
            <Card accent={colors.rank}>
              <Text variant="overline" tone="muted">
                skill unlock
              </Text>
              <Text variant="heading" style={{ color: colors.rank, marginTop: space.xs }}>
                +{exercise.unlock_bonus_xp} XP
              </Text>
              <Text variant="caption" tone="muted" style={{ marginTop: space.xs }}>
                {exercise.unlock_threshold} {exercise.unit === 'seconds' ? t('common.sec') : t('common.reps')}
              </Text>
            </Card>
            </Reveal>
          ) : null}

          <Section title={t('library.instructions')} index={6}>
            {asList(exercise?.instructions).map((step, index) => (
              <Reveal key={index} index={7 + index} from="left" distance={10} style={{ flexDirection: 'row', gap: space.md }}>
                <Text variant="numeric" style={{ color: tint, fontSize: 14, width: 18 }}>
                  {index + 1}
                </Text>
                <Text variant="body" tone="secondary" style={{ flex: 1 }}>
                  {step}
                </Text>
              </Reveal>
            ))}
          </Section>

          {/* „ხშირი შეცდომები" სავალდებულო ბლოკია — ტრავმის პრევენცია (სპეც. 18) */}
          <Section title={t('library.mistakes')} tint={colors.danger} index={8}>
            {asList(exercise?.common_mistakes).map((mistake, index) => (
              <Reveal key={index} index={9 + index} from="left" distance={10} style={{ flexDirection: 'row', gap: space.md }}>
                <Text style={{ color: colors.danger, width: 18 }}>✕</Text>
                <Text variant="body" tone="secondary" style={{ flex: 1 }}>
                  {mistake}
                </Text>
              </Reveal>
            ))}
          </Section>

          {exercise ? (
            <Reveal index={10} style={{ gap: space.sm, marginTop: space.md }}>
              <Pop trigger={inBasket}>
              <Button
                title={inBasket ? t('library.inWorkout') : t('library.addToWorkout')}
                variant={inBasket ? 'secondary' : 'primary'}
                onPress={() => basket.toggle(exercise)}
                haptic="medium"
              />
              </Pop>

              {basket.items.length > 0 ? (
                <Button
                  title={
                    basket.ready()
                      ? `${t('freestyle.start')} · ${t('freestyle.count', { count: basket.items.length })}`
                      : t('freestyle.needMore', { count: FREESTYLE_MIN_EXERCISES })
                  }
                  variant="secondary"
                  size="sm"
                  disabled={!basket.ready()}
                  onPress={() => router.push('/(tabs)/library')}
                />
              ) : null}
            </Reveal>
          ) : null}

          {exercise?.progression_from_id || exercise?.progression_to_id ? (
            <Section title={t('library.progression')} index={11}>
              <View style={{ flexDirection: 'row', gap: space.sm }}>
                {exercise.progression_from_id ? (
                  <Button
                    title={t('library.easier')}
                    variant="secondary"
                    size="sm"
                    full={false}
                    onPress={() => router.replace(`/exercise/${exercise.progression_from_id}`)}
                  />
                ) : null}
                {exercise.progression_to_id ? (
                  <Button
                    title={t('library.harder')}
                    variant="secondary"
                    size="sm"
                    full={false}
                    onPress={() => router.replace(`/exercise/${exercise.progression_to_id}`)}
                  />
                ) : null}
              </View>
            </Section>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

function Section({
  title,
  tint = colors.textMuted,
  index = 0,
  children,
}: {
  title: string;
  tint?: string;
  index?: number;
  children: React.ReactNode;
}) {
  return (
    <View style={{ gap: space.md, marginTop: space.md }}>
      <Reveal index={index}>
        <Text variant="overline" style={{ color: tint }}>
          {title}
        </Text>
      </Reveal>
      <View style={{ gap: space.md }}>{children}</View>
    </View>
  );
}
