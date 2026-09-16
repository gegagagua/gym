import { useState } from 'react';
import { View, FlatList } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Screen, Text, Card, Chip, Bar, DivisionBadge, SectionHeader, Reveal, CountUp, Skeleton, SkeletonRows, Pulse } from '@/components';
import { colors, divisionTheme, radius, space, border, type DivisionId } from '@/theme';
import { league as leagueApi } from '@/api/endpoints';
import type { LeagueMemberRow } from '@/api/types';

type Board = 'league' | 'friends';

export default function LeagueScreen() {
  const { t } = useTranslation();
  const [board, setBoard] = useState<Board>('league');

  const league = useQuery({ queryKey: ['league'], queryFn: () => leagueApi.current() });
  const friends = useQuery({
    queryKey: ['friends'],
    queryFn: () => leagueApi.friends(),
    enabled: board === 'friends',
  });

  const division = (league.data?.division ?? 1) as DivisionId;
  const tint = divisionTheme[division].tint;

  return (
    <Screen ambient={league.data?.enabled ? tint : colors.rank}>
      <Reveal from="top" distance={10} style={{ gap: space.md, marginBottom: space.lg }}>
        <Text variant="title">{t('league.title')}</Text>

        <View style={{ flexDirection: 'row', gap: space.sm }}>
          <Chip label={t('league.title')} selected={board === 'league'} onPress={() => setBoard('league')} />
          <Chip
            label={t('league.friends')}
            selected={board === 'friends'}
            tint={colors.info}
            onPress={() => setBoard('friends')}
          />
        </View>
      </Reveal>

      {board === 'friends' ? (
        <FriendsBoard rows={friends.data?.data ?? []} />
      ) : league.isLoading ? (
        <View style={{ gap: space.md }}>
          <Skeleton height={140} round={20} />
          <SkeletonRows rows={6} height={50} gap={space.xs} />
        </View>
      ) : !league.data ? null : !league.data.enabled ? (
        <ColdStart weeklyActive={league.data.weekly_active ?? 0} required={league.data.required ?? 200} />
      ) : !league.data.joined ? (
        <Reveal index={1} zoom>
        <Card>
          <Text variant="body" tone="secondary">
            {t('league.lockedBody')}
          </Text>
        </Card>
        </Reveal>
      ) : (
        <LeagueBoard
          division={division}
          members={league.data.members ?? []}
          myRank={league.data.my_rank ?? null}
          endsAt={league.data.ends_at}
        />
      )}
    </Screen>
  );
}

/**
 * ლიგა ჩაკეტილია სანამ 200 კვირეული აქტიური არ გვყავს (სპეც. 7.1).
 * ცარიელი ლიგის ჩვენების ნაცვლად პროგრესს ვაჩვენებთ — ლოდინი
 * მაშინაა ასატანი, როცა ხედავ, სად ხარ.
 */
function ColdStart({ weeklyActive, required }: { weeklyActive: number; required: number }) {
  const { t } = useTranslation();

  return (
    <Reveal index={1} zoom>
    <Card accent={colors.rank}>
      <Text variant="heading">{t('league.lockedTitle')}</Text>
      <Text variant="bodySm" tone="secondary" style={{ marginTop: space.sm }}>
        {t('league.lockedBody')}
      </Text>

      <View style={{ marginTop: space.lg, gap: space.sm }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <CountUp
            value={weeklyActive}
            delay={300}
            render={(shown) => (
              <Text variant="numeric" style={{ color: colors.rank }}>
                {shown}
              </Text>
            )}
          />
          <Text variant="numeric" tone="muted">
            {required}
          </Text>
        </View>
        <Bar progress={weeklyActive / required} from={colors.rank} to={colors.accent} />
      </View>
    </Card>
    </Reveal>
  );
}

function LeagueBoard({
  division,
  members,
  myRank,
  endsAt,
}: {
  division: DivisionId;
  members: LeagueMemberRow[];
  myRank: number | null;
  endsAt?: string;
}) {
  const { t } = useTranslation();
  const tint = divisionTheme[division].tint;

  const hoursLeft = endsAt
    ? Math.max(0, Math.round((new Date(endsAt).getTime() - Date.now()) / 3_600_000))
    : null;

  return (
    <>
      <Reveal index={1} zoom>
      <Card accent={tint}>
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <DivisionBadge division={division} />
          {hoursLeft !== null ? (
            <Text variant="caption" tone="muted">
              {t('league.endsIn', { time: `${hoursLeft}h` })}
            </Text>
          ) : null}
        </View>

        {myRank ? (
          <View style={{ marginTop: space.lg, alignItems: 'center' }}>
            <Text variant="overline" tone="muted">
              {t('league.myRank')}
            </Text>
            <CountUp
              value={myRank}
              duration={1000}
              delay={380}
              render={(shown) => (
                <Text variant="hero" style={{ color: tint }}>
                  {shown}
                </Text>
              )}
            />
          </View>
        ) : null}
      </Card>
      </Reveal>

      <Reveal index={2}>
        <SectionHeader title={t('league.title')} />
      </Reveal>

      <View style={{ gap: space.xs }}>
        {members.map((row, index) => (
          <Reveal key={row.rank} index={3 + Math.min(index, 12)} from="left" distance={12}>
            <MemberRow row={row} tint={tint} />
          </Reveal>
        ))}
      </View>
    </>
  );
}

function MemberRow({ row, tint }: { row: LeagueMemberRow; tint: string }) {
  const zoneColor =
    row.zone === 'promotion' ? colors.success : row.zone === 'demotion' ? colors.danger : 'transparent';

  const body = (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        gap: space.md,
        paddingHorizontal: space.base,
        paddingVertical: space.md,
        borderRadius: radius.md,
        backgroundColor: row.is_me ? `${tint}14` : colors.surface,
        borderWidth: row.is_me ? border.thin : border.hair,
        borderColor: row.is_me ? tint : colors.border,
      }}
    >
      {/* პრომოუშენ/დაქვეითების ზონა — მარცხენა ფერადი ხაზი, ზედმეტი ტექსტის გარეშე */}
      <View style={{ width: 3, height: 26, borderRadius: 2, backgroundColor: zoneColor }} />

      <Text variant="numeric" style={{ width: 26, fontSize: 15, color: row.is_me ? tint : colors.textMuted }}>
        {row.rank}
      </Text>

      <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
        {row.user?.display_name ?? row.user?.username ?? `#${row.user?.id ?? '—'}`}
      </Text>

      <Text variant="numeric" style={{ fontSize: 15, color: row.is_me ? tint : colors.textSecondary }}>
        {row.xp}
      </Text>
    </View>
  );

  // საკუთარი რიგი სიაში სუნთქავს — 30 რიგში თავის პოვნა წამში უნდა ხდებოდეს
  return row.is_me ? (
    <Pulse to={1.015} period={2600}>
      {body}
    </Pulse>
  ) : (
    body
  );
}

function FriendsBoard({ rows }: { rows: any[] }) {
  const { t } = useTranslation();

  if (rows.length === 0) {
    return (
      <Card>
        <Text variant="bodySm" tone="muted">
          {t('library.empty')}
        </Text>
      </Card>
    );
  }

  return (
    <FlatList
      scrollEnabled={false}
      data={rows}
      keyExtractor={(item) => String(item.user_id)}
      contentContainerStyle={{ gap: space.xs }}
      renderItem={({ item, index }) => (
        <Reveal
          index={Math.min(index, 12)}
          from="left"
          distance={12}
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            gap: space.md,
            paddingHorizontal: space.base,
            paddingVertical: space.md,
            borderRadius: radius.md,
            backgroundColor: colors.surface,
            borderWidth: border.hair,
            borderColor: colors.border,
          }}
        >
          <Text variant="numeric" style={{ width: 26, fontSize: 15, color: colors.textMuted }}>
            {index + 1}
          </Text>
          <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
            {item.display_name ?? item.username ?? `#${item.user_id}`}
          </Text>
          <Text variant="numeric" style={{ fontSize: 15, color: colors.info }}>
            {item.xp}
          </Text>
        </Reveal>
      )}
    />
  );
}
