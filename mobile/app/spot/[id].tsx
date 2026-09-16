import { useState } from 'react';
import { View, Linking, Alert, ScrollView } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import * as Location from 'expo-location';
import { Image } from 'expo-image';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Text, Card, Button, Chip, Tap, Glow, Grain, SectionHeader, StatTile, Reveal, Skeleton } from '@/components';
import { colors, gutter, radius, space, border } from '@/theme';
import { spots as spotsApi, league as leagueApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';

export default function SpotDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const [checkingIn, setCheckingIn] = useState(false);

  const spotId = Number(id);

  const { data } = useQuery({
    queryKey: ['spot', spotId],
    queryFn: () => spotsApi.get(spotId),
    enabled: !!spotId,
  });

  const board = useQuery({
    queryKey: ['spot-board', spotId],
    queryFn: () => leagueApi.spot(spotId, 'week'),
    enabled: !!spotId,
  });

  const spot = data?.data;

  const checkIn = async () => {
    setCheckingIn(true);

    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert(t('map.checkIn'), t('map.checkInTooFar'));
        return;
      }

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });

      await spotsApi.checkIn(
        spotId,
        position.coords.latitude,
        position.coords.longitude,
        Math.round(position.coords.accuracy ?? 0),
      );

      await queryClient.invalidateQueries({ queryKey: ['checkin'] });
      Alert.alert(t('map.checkedIn'), `+15% XP`);
      router.back();
    } catch (error) {
      const message =
        error instanceof ApiError && error.payload?.error === 'too_far'
          ? t('map.checkInTooFar')
          : t('common.error');

      Alert.alert(t('map.checkIn'), message);
    } finally {
      setCheckingIn(false);
    }
  };

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={colors.accent} size={460} opacity={0.24} style={{ top: -250, left: -110 }} />
      <Grain />

      <ScrollView
        contentContainerStyle={{ paddingTop: insets.top + space.sm, paddingBottom: insets.bottom + 120 }}
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

          {spot?.photos?.length ? (
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: space.sm }}>
              {spot.photos.map((photo, index) => (
                <Image
                  key={index}
                  source={{ uri: photo.thumb_url ?? photo.url }}
                  style={{
                    width: 220,
                    height: 150,
                    borderRadius: radius.md,
                    borderWidth: border.hair,
                    borderColor: colors.border,
                  }}
                  contentFit="cover"
                  transition={200}
                />
              ))}
            </ScrollView>
          ) : null}

          <Reveal index={1} style={{ gap: space.xs }}>
            {spot ? <Text variant="title">{spot.name}</Text> : <Skeleton height={28} width="65%" />}
            {spot?.description ? (
              <Text variant="bodySm" tone="secondary">
                {spot.description}
              </Text>
            ) : null}
          </Reveal>

          <Reveal index={2} style={{ flexDirection: 'row', gap: space.sm }}>
            <StatTile label={t('map.condition')} value={spot?.condition_rating ?? '—'} unit="/5" />
            <StatTile label="check-in" value={spot?.checkin_count ?? 0} tint={colors.accent} />
            <StatTile
              label={t('map.title')}
              value={spot?.has_lighting ? '☾' : '—'}
              hint={spot?.access}
            />
          </Reveal>

          <Reveal index={3}>
            <SectionHeader title={t('map.equipment')} />
          </Reveal>

          <Reveal index={4} style={{ flexDirection: 'row', flexWrap: 'wrap', gap: space.sm }}>
            {spot?.equipment?.map((tag) => (
              <Chip key={tag} label={t(`map.eq_${tag}`, { defaultValue: tag })} compact />
            ))}
          </Reveal>

          {/* ---- მოედნის ბორდი: ყველაზე ძლიერი სოციალური კაუჭი (სპეც. 7.2) ---- */}
          <Reveal index={5}>
            <SectionHeader title={t('map.board')} />
          </Reveal>

          <Reveal index={6}>
          <Card>
            {board.data?.data?.length ? (
              <View style={{ gap: space.md }}>
                {board.data.data.slice(0, 10).map((row: any, index: number) => (
                  <Reveal
                    key={row.user_id}
                    index={7 + index}
                    from="left"
                    distance={10}
                    style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}
                  >
                    <Text
                      variant="numeric"
                      style={{
                        width: 22,
                        fontSize: 14,
                        color: index === 0 ? colors.accent : colors.textMuted,
                      }}
                    >
                      {index + 1}
                    </Text>
                    <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
                      {row.display_name ?? row.username ?? `#${row.user_id}`}
                    </Text>
                    <Text variant="numeric" style={{ fontSize: 14, color: colors.accent }}>
                      {row.xp}
                    </Text>
                  </Reveal>
                ))}
              </View>
            ) : (
              <Text variant="bodySm" tone="muted">
                {t('library.empty')}
              </Text>
            )}
          </Card>
          </Reveal>

          {/* ---- ტრენერები ---- */}
          {spot?.trainers?.length ? (
            <>
              <Reveal index={8}>
                <SectionHeader title={t('map.trainer')} />
              </Reveal>

              <View style={{ gap: space.sm }}>
                {spot.trainers.map((trainer, index) => (
                  <Reveal key={trainer.id} index={9 + index} zoom>
                  <Card accent={trainer.is_verified ? colors.rank : undefined}>
                    <Text variant="subheading">{trainer.name}</Text>
                    {trainer.bio ? (
                      <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
                        {trainer.bio}
                      </Text>
                    ) : null}

                    {trainer.contacts ? (
                      <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
                        {trainer.contacts.instagram ? (
                          <Button
                            title="Instagram"
                            variant="secondary"
                            size="sm"
                            full={false}
                            onPress={() =>
                              Linking.openURL(`https://instagram.com/${trainer.contacts!.instagram}`)
                            }
                          />
                        ) : null}
                        {trainer.contacts.phone ? (
                          <Button
                            title={trainer.contacts.phone}
                            variant="secondary"
                            size="sm"
                            full={false}
                            onPress={() => Linking.openURL(`tel:${trainer.contacts!.phone}`)}
                          />
                        ) : null}
                      </View>
                    ) : null}
                  </Card>
                  </Reveal>
                ))}
              </View>
            </>
          ) : null}
        </View>
      </ScrollView>

      <View
        style={{
          position: 'absolute',
          bottom: 0,
          left: 0,
          right: 0,
          paddingHorizontal: gutter,
          paddingTop: space.md,
          paddingBottom: insets.bottom + space.md,
          backgroundColor: colors.bg,
          borderTopWidth: border.hair,
          borderTopColor: colors.border,
        }}
      >
        <Button title={t('map.checkIn')} loading={checkingIn} onPress={checkIn} haptic="success" />
      </View>
    </View>
  );
}
