import { useMemo, useState } from 'react';
import { View, FlatList, SectionList, TextInput } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { Screen, Text, Card, Chip, Tap, Button, Reveal, SlideUp, SkeletonRows, Pop } from '@/components';
import { colors, forceTheme, zoneTheme, radius, space, border, gutter, type ZoneKey } from '@/theme';
import { exercises as exercisesApi, me as meApi } from '@/api/endpoints';
import { resolveLoop } from '@/lib/media';
import { useFreestyle, FREESTYLE_MIN_EXERCISES } from '@/store/freestyle';
import { usePlayer } from '@/store/player';
import { useAuth } from '@/store/auth';
import type { Exercise } from '@/api/types';

/** ბიბლიოთეკის თანმიმდევრობა — სერვერის Exercise::ZONES */
const ZONES: ZoneKey[] = ['chest', 'back', 'shoulders', 'arms', 'core', 'legs', 'full_body', 'pelvic_floor', 'mobility'];

export default function LibraryScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const [query, setQuery] = useState('');
  const [zone, setZone] = useState<ZoneKey | null>(null);
  const [onlyMyGear, setOnlyMyGear] = useState(false);

  const basket = useFreestyle();
  const startSession = usePlayer((s) => s.start);
  const user = useAuth((s) => s.user);

  // პროფილში ინვენტარი წვდომის დონეა (none|bar|yard|gym); სერვერი მას
  // ფიზიკურ ტეგებად შლის (ExerciseController::expandEquipment)
  const myGear = user?.profile?.equipment ?? [];

  const { data, isLoading } = useQuery({
    queryKey: ['exercises', onlyMyGear ? myGear.join(',') : null],
    queryFn: () =>
      exercisesApi.list({ limit: 300, ...(onlyMyGear && myGear.length ? { equipment: myGear.join(',') } : {}) }),
    staleTime: 10 * 60_000,
  });

  // check-in მოქმედია → სესია T2-ია და XP ×1.15 (სპეც. 6.3)
  const checkin = useQuery({ queryKey: ['checkin'], queryFn: () => meApi.checkin() });

  const filtered = useMemo(() => {
    const all = data?.data ?? [];
    const needle = query.trim().toLowerCase();

    return all.filter((exercise) => {
      if (zone && exercise.zone !== zone) return false;
      if (!needle) return true;
      return (
        exercise.name.toLowerCase().includes(needle) ||
        exercise.slug.toLowerCase().includes(needle)
      );
    });
  }, [data, query, zone]);

  // ფილტრის გარეშე — ზონების სექციები; ძებნა ან ზონა — ერთი სია
  const grouped = !zone && !query.trim();
  const sections = useMemo(
    () =>
      ZONES.map((key) => ({ key, data: filtered.filter((exercise) => exercise.zone === key) })).filter(
        (section) => section.data.length > 0,
      ),
    [filtered],
  );

  const listPadding = {
    padding: gutter,
    paddingTop: space.md,
    // კალათის ზოლი სიის ბოლო რიგს არ უნდა ფარავდეს
    paddingBottom: basket.items.length > 0 ? 190 : space.huge,
  };

  const startFreestyle = () => {
    if (!basket.ready()) return;

    startSession({
      exercises: basket.items,
      source: 'freestyle',
      spotCheckinId: checkin.data?.checkin?.id ?? null,
      bodyweightKg: user?.profile?.weight_kg ?? 75,
    });

    basket.clear();
    router.push('/player');
  };

  return (
    <Screen scroll={false} ambient={zone ? zoneTheme[zone] : colors.rank} padded={false}>
      <Reveal from="top" distance={10} style={{ paddingHorizontal: gutter, gap: space.md }}>
        <Text variant="title">{t('library.title')}</Text>

        <TextInput
          value={query}
          onChangeText={setQuery}
          placeholder={t('library.search')}
          placeholderTextColor={colors.textDisabled}
          style={{
            height: 46,
            borderRadius: radius.md,
            paddingHorizontal: space.base,
            backgroundColor: colors.surface,
            borderWidth: border.hair,
            borderColor: colors.border,
            color: colors.text,
            fontFamily: 'NotoSansGeorgian_400Regular',
            fontSize: 15,
          }}
        />

        <FlatList
          horizontal
          showsHorizontalScrollIndicator={false}
          data={[null, ...ZONES]}
          keyExtractor={(item) => item ?? 'all'}
          contentContainerStyle={{ gap: space.sm, paddingVertical: space.xxs }}
          ListHeaderComponent={
            myGear.length > 0 ? (
              <View style={{ marginRight: space.sm }}>
                <Chip
                  label={t('library.filterEquipment')}
                  selected={onlyMyGear}
                  tint={colors.info}
                  onPress={() => setOnlyMyGear((value) => !value)}
                />
              </View>
            ) : null
          }
          renderItem={({ item }) => (
            <Chip
              label={item ? t(`library.zone_${item}`) : t('common.all')}
              selected={zone === item}
              tint={item ? zoneTheme[item] : colors.accent}
              onPress={() => setZone(item)}
            />
          )}
        />
      </Reveal>

      {zone === 'pelvic_floor' ? (
        <View style={{ paddingHorizontal: gutter, paddingTop: space.md }}>
          <GuidedBanner onPress={() => router.push('/kegel')} />
        </View>
      ) : null}

      {grouped && !isLoading ? (
        <SectionList
          sections={sections}
          keyExtractor={(item) => String(item.id)}
          stickySectionHeadersEnabled={false}
          contentContainerStyle={listPadding}
          showsVerticalScrollIndicator={false}
          renderSectionHeader={({ section }) => (
            <Tap onPress={() => setZone(section.key)} style={{ marginTop: space.lg, marginBottom: space.sm }}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm }}>
                <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: zoneTheme[section.key] }} />
                <Text variant="overline" style={{ flex: 1, color: zoneTheme[section.key] }}>
                  {t(`library.zone_${section.key}`)} · {section.data.length}
                </Text>
                {section.key === 'pelvic_floor' ? (
                  <Tap onPress={() => router.push('/kegel')} hitSlop={10}>
                    <Text variant="label" style={{ color: zoneTheme.pelvic_floor }}>
                      ▶ {t('library.guided')}
                    </Text>
                  </Tap>
                ) : (
                  <Text variant="caption" tone="muted">
                    {t('library.seeAll')} →
                  </Text>
                )}
              </View>
            </Tap>
          )}
          renderItem={({ item, index, section }) => {
            // კასკადი მხოლოდ პირველ ეკრანზე (პირველი სექციის პირველი რიგები)
            const first = section.key === sections[0]?.key;
            return (
              <Reveal index={first && index < 6 ? index : 0} distance={10} style={{ marginBottom: space.sm }}>
                <ExerciseRow
                  exercise={item}
                  selected={basket.has(item.id)}
                  onPress={() => router.push(`/exercise/${item.id}`)}
                  onToggle={() => basket.toggle(item)}
                />
              </Reveal>
            );
          }}
        />
      ) : (
        <FlatList
          data={filtered}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ ...listPadding, gap: space.sm }}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={
            isLoading ? (
              <SkeletonRows rows={6} height={82} gap={space.sm} />
            ) : (
              <Reveal style={{ marginTop: space.xxl }}>
                <Text variant="bodySm" tone="muted" center>
                  {t('library.empty')}
                </Text>
              </Reveal>
            )
          }
          renderItem={({ item, index }) => (
            // კასკადი მხოლოდ პირველ ეკრანზე — გადახვევისას რიგი შეყოვნების
            // გარეშე ჩნდება, თორემ სია „ჩამორჩება“ თითს
            <Reveal index={index < 8 ? index : 0} distance={10}>
              <ExerciseRow
                exercise={item}
                selected={basket.has(item.id)}
                onPress={() => router.push(`/exercise/${item.id}`)}
                onToggle={() => basket.toggle(item)}
              />
            </Reveal>
          )}
        />
      )}

      {/* ---- თავისუფალი ვარჯიშის კალათა ---- */}
      <SlideUp
        visible={basket.items.length > 0}
        style={{
          position: 'absolute',
          left: gutter,
          right: gutter,
          bottom: space.base,
          gap: space.sm,
        }}
      >
          <Card accent={basket.ready() ? colors.accent : colors.warning}>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <Pop trigger={basket.items.length}>
                <Text variant="subheading">{t('freestyle.count', { count: basket.items.length })}</Text>
              </Pop>

              <Tap onPress={basket.clear} hitSlop={12}>
                <Text variant="caption" tone="muted">
                  {t('freestyle.clear')}
                </Text>
              </Tap>
            </View>

            {basket.ready() ? null : (
              <Text variant="caption" tone="muted" style={{ marginTop: space.xs }}>
                {t('freestyle.needMore', { count: FREESTYLE_MIN_EXERCISES })}
              </Text>
            )}

            <View style={{ marginTop: space.md }}>
              <Button
                title={`${t('freestyle.title')} · ${t('freestyle.start')}`}
                disabled={!basket.ready()}
                onPress={startFreestyle}
                haptic="success"
              />
            </View>
          </Card>
      </SlideUp>
    </Screen>
  );
}

function ExerciseRow({
  exercise,
  selected,
  onPress,
  onToggle,
}: {
  exercise: Exercise;
  selected: boolean;
  onPress: () => void;
  onToggle: () => void;
}) {
  const { t } = useTranslation();
  const tint = (exercise.zone ? zoneTheme[exercise.zone] : forceTheme[exercise.force]) ?? colors.accent;
  const loop = resolveLoop(exercise.media);

  return (
    <Tap onPress={onPress} scaleTo={0.985}>
      <Card padded={false} radiusKey="md">
        <View style={{ flexDirection: 'row', alignItems: 'center' }}>
          {/* ვექტორის ფერადი ზოლი — სიაში ფილტრის გარეშეც იკითხება */}
          <View style={{ width: 3, alignSelf: 'stretch', backgroundColor: tint }} />

          <View
            style={{
              width: 58,
              height: 58,
              margin: space.sm,
              borderRadius: radius.sm,
              overflow: 'hidden',
              backgroundColor: colors.surfaceHi,
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            {loop ? (
              <Image source={{ uri: loop.url }} style={{ flex: 1, width: '100%' }} contentFit="cover" cachePolicy="disk" />
            ) : (
              <Text variant="numeric" style={{ color: `${tint}66`, fontSize: 15 }}>
                {exercise.name.slice(0, 2).toUpperCase()}
              </Text>
            )}
          </View>

          <View style={{ flex: 1, paddingVertical: space.base, gap: space.xs }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.sm }}>
              <Text variant="subheading" style={{ flex: 1 }} numberOfLines={1}>
                {exercise.name}
              </Text>

              {exercise.is_skill_unlock ? (
                <View
                  style={{
                    paddingHorizontal: space.sm,
                    paddingVertical: 2,
                    borderRadius: radius.xs,
                    backgroundColor: `${colors.rank}1F`,
                  }}
                >
                  <Text variant="caption" style={{ color: colors.rank }}>
                    +{exercise.unlock_bonus_xp}
                  </Text>
                </View>
              ) : null}
            </View>

            <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
              <Text variant="caption" tone="muted">
                {exercise.zone ? t(`library.zone_${exercise.zone}`) : t(`library.force_${exercise.force}`)}
              </Text>
              <Text variant="caption" tone="muted">
                L{exercise.level_min}–{exercise.level_max}
              </Text>
              <Text variant="numeric" style={{ fontSize: 13, color: tint }}>
                k {exercise.difficulty_coef.toFixed(1)}
              </Text>
            </View>
          </View>

          {/* კალათაში დამატება — რიგის გახსნის გარეშე */}
          <Tap onPress={onToggle} hitSlop={10} haptic={selected ? 'light' : 'medium'} style={{ paddingHorizontal: space.base }}>
            <Pop trigger={selected}>
              <View
                style={{
                  width: 30,
                  height: 30,
                  borderRadius: 15,
                  alignItems: 'center',
                  justifyContent: 'center',
                  backgroundColor: selected ? colors.accent : 'transparent',
                  borderWidth: border.thin,
                  borderColor: selected ? colors.accent : colors.borderStrong,
                }}
              >
                <Text variant="label" style={{ color: selected ? colors.bg : colors.textMuted, fontSize: 16 }}>
                  {selected ? '✓' : '+'}
                </Text>
              </View>
            </Pop>
          </Tap>
        </View>
      </Card>
    </Tap>
  );
}

function GuidedBanner({ onPress }: { onPress: () => void }) {
  const { t } = useTranslation();
  const tint = zoneTheme.pelvic_floor;

  return (
    <Tap onPress={onPress} scaleTo={0.985} haptic="medium">
      <Card accent={tint}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}>
          <View style={{ flex: 1, gap: 2 }}>
            <Text variant="subheading">{t('kegel.title')}</Text>
            <Text variant="caption" tone="muted">
              {t('kegel.bannerHint')}
            </Text>
          </View>
          <Text variant="heading" style={{ color: tint }}>
            ▶
          </Text>
        </View>
      </Card>
    </Tap>
  );
}
