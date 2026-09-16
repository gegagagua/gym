import { useEffect, useRef, useState } from 'react';
import { View, Share as NativeShare } from 'react-native';
import { Image } from 'expo-image';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { Screen, Text, Card, Button, StatTile, SectionHeader, Glow, Reveal, CountUp, Breathe, Fade, Skeleton, Tap } from '@/components';
import { colors, gutter, radius, space, border } from '@/theme';
import { usePlayer } from '@/store/player';
import { flushQueue } from '@/db/sync';
import { formatDuration, formatXp } from '@/lib/xp';
import { share as shareApi, programs as programsApi } from '@/api/endpoints';

export default function SummaryScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const queryClient = useQueryClient();

  const { logged, exercises, painStop, source, programDayId, finish, reset, estimatedXp } = usePlayer();
  const [saved, setSaved] = useState<{ clientUuid: string; durationMs: number } | null>(null);
  const [sharing, setSharing] = useState(false);
  const [card, setCard] = useState<{ url: string | null; failed: boolean } | null>(null);
  const polling = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => () => (polling.current ? clearTimeout(polling.current) : undefined), []);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const result = await finish();
      if (cancelled || !result) return;

      setSaved(result);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success).catch(() => undefined);

      // პროგრამის დღე დახურულია — კურსორი შემდეგზე გადადის, თორემ
      // მომხმარებელი სამუდამოდ W1D1-ზე რჩება
      if (source === 'program' && programDayId) {
        await programsApi.advance().catch(() => undefined);
      }

      // სესია ლოკალურად უკვე შენახულია — სინქი ფონურად, შედეგზე ლოდინის გარეშე
      flushQueue()
        .then(() => queryClient.invalidateQueries())
        .catch(() => undefined);
    })();

    return () => {
      cancelled = true;
    };
  }, [finish, queryClient, source, programDayId]);

  const totalReps = logged.reduce((sum, entry) => sum + (entry.reps ?? 0), 0);
  const totalSeconds = logged.reduce((sum, entry) => sum + (entry.seconds ?? 0), 0);
  const exerciseCount = new Set(logged.map((entry) => entry.exerciseId)).size;

  const close = () => {
    reset();
    router.replace('/(tabs)');
  };

  /**
   * ბარათი queue-ში იხატება — კლიენტი სტატუსს ეკითხება, სანამ არ გამზადდება.
   * queue worker-ის გარეშე (ლოკალურად) 12 წამში ვჩერდებით და ვამბობთ.
   */
  const createCard = async () => {
    setSharing(true);
    setCard(null);

    try {
      const { card: created } = await shareApi.create('session');
      const deadline = Date.now() + 12_000;

      const poll = async () => {
        const { card: state } = await shareApi.get(created.id).catch(() => ({ card: null as any }));

        if (state?.status === 'ready' && state.url) {
          setCard({ url: state.url, failed: false });
          setSharing(false);
          return;
        }

        if (state?.status === 'failed' || Date.now() > deadline) {
          setCard({ url: null, failed: true });
          setSharing(false);
          return;
        }

        polling.current = setTimeout(poll, 900);
      };

      polling.current = setTimeout(poll, 700);
    } catch {
      setCard({ url: null, failed: true });
      setSharing(false);
    }
  };

  const sendCard = () => {
    if (!card?.url) return;
    NativeShare.share({ url: card.url, message: card.url }).catch(() => undefined);
  };

  return (
    <Screen
      ambient={painStop ? colors.warning : colors.accent}
      footer={
        <View style={{ gap: space.sm }}>
          <Button
            title={t('player.share')}
            variant="secondary"
            loading={sharing}
            onPress={card?.url ? sendCard : createCard}
          />
          <Button title={t('common.done')} onPress={close} />
        </View>
      }
    >
      <View style={{ alignItems: 'center', gap: space.md, marginTop: space.xl, marginBottom: space.xl }}>
        {/* ნათება ნელა ისუნთქავს — შეჯამება ერთადერთი ეკრანია, სადაც ჩერდები */}
        <Breathe from={0.32} to={0.5} period={4600} style={{ position: 'absolute', top: -60 }}>
          <Glow color={painStop ? colors.warning : colors.accent} size={360} opacity={1} />
        </Breathe>

        <Reveal from="top" distance={10}>
          <Text variant="overline" tone="muted">
            {painStop ? t('player.painTitle') : t('player.summaryTitle')}
          </Text>
        </Reveal>

        {/* ციფრი 0-დან ითვლება — ვარჯიშის შედეგი „გროვდება“ თვალწინ */}
        <Reveal index={1} zoom distance={0}>
          <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: space.sm }}>
            <CountUp
              value={estimatedXp()}
              duration={1200}
              delay={260}
              render={(shown) => (
                <Text variant="hero" style={{ color: colors.accent }}>
                  {formatXp(shown)}
                </Text>
              )}
            />
            <Text variant="title" tone="muted">
              XP
            </Text>
          </View>
        </Reveal>

        {/* კლიენტის ესტიმაცია ვიზუალურად აღნიშნულია — სერვერი გადათვლის (სპეც. 12.3) */}
        <Reveal index={2}>
          <Text variant="caption" tone="muted" center>
            {t('player.estimated')}
          </Text>
        </Reveal>
      </View>

      {painStop ? (
        <Reveal index={3}>
          <Card style={{ borderColor: `${colors.warning}55`, marginBottom: space.md }}>
            <Text variant="body" tone="secondary">
              {t('player.painBody')}
            </Text>
          </Card>
        </Reveal>
      ) : null}

      <Reveal index={3}>
        <View style={{ flexDirection: 'row', gap: space.sm }}>
          <StatTile
            label={t('player.duration')}
            value={saved ? formatDuration(saved.durationMs) : '—'}
          />
          <StatTile label={t('common.sets')} value={logged.length} />
          <StatTile label={t('library.title')} value={`${exerciseCount}/${exercises.length}`} />
        </View>
      </Reveal>

      <Reveal index={4}>
        <View style={{ flexDirection: 'row', gap: space.sm, marginTop: space.sm }}>
          <StatTile label={t('common.reps')} value={totalReps} tint={colors.accent} />
          <StatTile label={t('common.sec')} value={totalSeconds} tint={colors.info} />
        </View>
      </Reveal>

      <Reveal index={5}>
        <SectionHeader title={t('player.volume')} />
      </Reveal>

      <Reveal index={6}>
      <Card>
        <View style={{ gap: space.md }}>
          {exercises.map((exercise) => {
            const sets = logged.filter((entry) => entry.exerciseId === exercise.exerciseId);
            if (sets.length === 0) return null;

            const total = sets.reduce((sum, s) => sum + (s.reps ?? s.seconds ?? 0), 0);

            return (
              <Reveal
                key={exercise.exerciseId}
                index={7 + exercises.indexOf(exercise)}
                from="left"
                distance={10}
                style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}
              >
                <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
                  {exercise.name}
                </Text>

                <Text variant="caption" tone="muted">
                  {sets.length} × {t('common.sets')}
                </Text>

                <Text variant="numeric" style={{ fontSize: 15, color: colors.accent, minWidth: 46, textAlign: 'right' }}>
                  {total}
                  {exercise.unit === 'seconds' ? t('common.sec') : ''}
                </Text>
              </Reveal>
            );
          })}
        </View>
      </Card>
      </Reveal>

      {/* ---- გაზიარების ბარათი: შექმნა → რენდერი → ჩვენება ---- */}
      <Fade visible={sharing || !!card} style={{ marginTop: space.lg }}>
        <Card accent={colors.rank}>
          <Text variant="overline" tone="muted" style={{ marginBottom: space.md }}>
            {t('player.share')}
          </Text>

          {sharing ? (
            <Skeleton height={220} round={radius.md} />
          ) : card?.url ? (
            <View style={{ gap: space.md }}>
              <Tap onPress={sendCard} scaleTo={0.98}>
                <Image
                  source={{ uri: card.url }}
                  style={{
                    width: '100%',
                    aspectRatio: 9 / 16,
                    maxHeight: 320,
                    borderRadius: radius.md,
                    borderWidth: border.hair,
                    borderColor: colors.border,
                  }}
                  contentFit="contain"
                  transition={220}
                />
              </Tap>

              <Button title={t('player.share')} onPress={sendCard} haptic="success" />
            </View>
          ) : (
            <Text variant="bodySm" tone="muted">
              {t('player.shareQueued')}
            </Text>
          )}
        </Card>
      </Fade>
    </Screen>
  );
}
