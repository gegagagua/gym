import { useEffect, useState } from 'react';
import { View, Alert, Switch, Linking } from 'react-native';
import * as Notifications from 'expo-notifications';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import {
  Screen,
  Text,
  Card,
  Button,
  Chip,
  StatTile,
  SectionHeader,
  TierBadge,
  Tap,
  Reveal,
  CountUp,
  SkeletonRows,
} from '@/components';
import { colors, radius, space, border } from '@/theme';
import { me as meApi, auth as authApi, content as contentApi } from '@/api/endpoints';
import { formatXp } from '@/lib/xp';
import { useAuth } from '@/store/auth';
import { useSettings } from '@/store/settings';
import { registerForPush } from '@/lib/push';
import { LOCALE_LABELS, SUPPORTED_LOCALES, type AppLocale } from '@/i18n';

export default function ProfileScreen() {
  const { t, i18n } = useTranslation();
  const router = useRouter();
  const { user, setLocale, signOut, deviceUuid } = useAuth();
  const settings = useSettings();
  const [pushEnabled, setPushEnabled] = useState(false);

  useEffect(() => {
    Notifications.getPermissionsAsync()
      .then(({ status }) => setPushEnabled(status === 'granted'))
      .catch(() => undefined);
  }, []);

  // ჩართვა ნებართვას ითხოვს; გამორთვა სისტემურ პარამეტრებში ხდება —
  // iOS-ზე აპს ნებართვის უკან წართმევა არ შეუძლია.
  const togglePush = async () => {
    if (pushEnabled) {
      Alert.alert(t('profile.push'), t('profile.notifications'), [
        { text: t('common.cancel'), style: 'cancel' },
        { text: t('profile.settings'), onPress: () => Linking.openSettings() },
      ]);
      return;
    }

    const token = deviceUuid ? await registerForPush(deviceUuid) : null;
    setPushEnabled(!!token);
  };

  const stats = useQuery({ queryKey: ['stats', 'all'], queryFn: () => meApi.stats('all') });
  const records = useQuery({ queryKey: ['records'], queryFn: () => meApi.records() });
  const attributions = useQuery({ queryKey: ['attributions'], queryFn: () => contentApi.attributions() });

  const confirmDelete = () =>
    Alert.alert(t('profile.deleteAccount'), t('profile.deleteWarn'), [
      { text: t('common.cancel'), style: 'cancel' },
      {
        text: t('profile.deleteAccount'),
        style: 'destructive',
        onPress: async () => {
          await authApi.deleteAccount().catch(() => undefined);
          await signOut();
          router.replace('/(onboarding)');
        },
      },
    ]);

  return (
    <Screen ambient={colors.rank}>
      <Reveal from="top" distance={10} style={{ gap: space.xs, marginBottom: space.lg }}>
        <Text variant="title">{user?.display_name ?? user?.username ?? t('profile.title')}</Text>
        {user?.is_guest ? (
          <Text variant="caption" tone="muted">
            {t('auth.guestNote')}
          </Text>
        ) : null}
      </Reveal>

      {/* სტუმრის პროგრესი მოწყობილობაზეა — ნომრის მიბმა ერთადერთი გზაა მის შესანარჩუნებლად */}
      {user?.is_guest ? (
        <Reveal index={1} style={{ marginBottom: space.lg }}>
          <Button title={t('auth.saveProgress')} onPress={() => router.push('/auth?mode=upgrade')} />
        </Reveal>
      ) : null}

      <Reveal index={2} zoom>
      <Card accent={colors.rank}>
        <View style={{ flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between' }}>
          <View>
            <Text variant="overline" tone="muted">
              {t('profile.totalXp')}
            </Text>
            <CountUp
              value={stats.data?.xp_total ?? 0}
              duration={1100}
              delay={280}
              render={(shown) => (
                <Text variant="display" style={{ color: colors.rank }}>
                  {formatXp(shown)}
                </Text>
              )}
            />
          </View>

          <View style={{ alignItems: 'flex-end', gap: space.xs }}>
            <Text variant="overline" tone="muted">
              {t('common.level')}
            </Text>
            <Text variant="title" style={{ color: colors.accent }}>
              {stats.data?.level ?? user?.profile?.level ?? 1}
            </Text>
          </View>
        </View>
      </Card>
      </Reveal>

      <Reveal index={3} style={{ flexDirection: 'row', gap: space.sm, marginTop: space.md }}>
        <StatTile label={t('profile.sessions')} value={stats.data?.sessions ?? 0} />
        <StatTile
          label={t('profile.longestStreak')}
          value={stats.data?.longest_streak ?? 0}
          tint={colors.streak}
        />
        <StatTile label={t('common.reps')} value={formatXp(stats.data?.reps ?? 0)} />
      </Reveal>

      {/* ---- პირადი რეკორდები ---- */}
      <Reveal index={4}>
        <SectionHeader title={t('profile.records')} />
      </Reveal>

      <Reveal index={5}>
      <Card>
        {records.isLoading ? (
          <SkeletonRows rows={3} height={20} gap={space.md} />
        ) : records.data?.data?.length ? (
          <View style={{ gap: space.md }}>
            {records.data.data.slice(0, 10).map((record: any, index: number) => (
              <Reveal
                key={`${record.exercise_id}-${record.metric}`}
                index={6 + index}
                from="left"
                distance={10}
                style={{ flexDirection: 'row', alignItems: 'center', gap: space.md }}
              >
                <Text variant="body" style={{ flex: 1 }} numberOfLines={1}>
                  {record.name}
                </Text>

                <TierBadge tier={record.verification_tier} />

                <Text variant="numeric" style={{ fontSize: 15, color: colors.accent, minWidth: 44, textAlign: 'right' }}>
                  {Math.round(record.value)}
                  {record.metric === 'max_seconds' ? t('common.sec') : ''}
                  {record.metric === 'max_weight' ? t('common.kg') : ''}
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

      {/* ---- ენა ---- */}
      <Reveal index={7}>
        <SectionHeader title={t('profile.language')} />
      </Reveal>

      <Reveal index={8} style={{ flexDirection: 'row', gap: space.sm }}>
        {SUPPORTED_LOCALES.map((locale) => (
          <Chip
            key={locale}
            label={LOCALE_LABELS[locale]}
            selected={i18n.language === locale}
            onPress={() => setLocale(locale as AppLocale)}
          />
        ))}
      </Reveal>

      {/* ---- პარამეტრები ---- */}
      <Reveal index={9}>
        <SectionHeader title={t('profile.settings')} />
      </Reveal>

      <Reveal index={10}>
      <Card padded={false}>
        <SettingRow
          label={t('profile.haptics')}
          value={settings.hapticCues}
          onToggle={() => settings.toggle('hapticCues')}
        />
        <SettingRow
          label={t('profile.sound')}
          value={settings.soundCues}
          onToggle={() => settings.toggle('soundCues')}
        />
        <SettingRow
          label={t('profile.push')}
          value={pushEnabled}
          onToggle={togglePush}
          last
        />
      </Card>
      </Reveal>

      {/* ---- ატრიბუცია: ავტომატურად exercise_media-დან (სპეც. 13.1) ---- */}
      {attributions.data?.data?.length ? (
        <>
          <SectionHeader title={t('profile.attribution')} />
          <Card>
            <View style={{ gap: space.sm }}>
              {attributions.data.data.map((row: any, index: number) => (
                <Tap
                  key={index}
                  onPress={() => row.source_url && Linking.openURL(row.source_url)}
                  disabled={!row.source_url}
                >
                  <Text variant="caption" tone="muted">
                    {row.attribution_text} · {row.license}
                  </Text>
                </Tap>
              ))}
            </View>
          </Card>
        </>
      ) : null}

      <Reveal index={11} style={{ gap: space.sm, marginTop: space.xxl }}>
        <Text variant="caption" tone="muted" center>
          {t('profile.private')}
        </Text>

        <Button
          title={t('profile.logout')}
          variant="secondary"
          onPress={async () => {
            await signOut();
            router.replace('/(onboarding)');
          }}
        />

        <Button title={t('profile.deleteAccount')} variant="danger" onPress={confirmDelete} />
      </Reveal>
    </Screen>
  );
}

function SettingRow({
  label,
  value,
  onToggle,
  last,
}: {
  label: string;
  value: boolean;
  onToggle: () => void;
  last?: boolean;
}) {
  return (
    <View
      style={{
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: space.base,
        paddingVertical: space.md,
        borderBottomWidth: last ? 0 : border.hair,
        borderBottomColor: colors.border,
        borderRadius: last ? radius.lg : 0,
      }}
    >
      <Text variant="body">{label}</Text>
      <Switch
        value={value}
        onValueChange={onToggle}
        trackColor={{ false: colors.surfaceHi, true: colors.accentDim }}
        thumbColor={value ? colors.accent : colors.textMuted}
      />
    </View>
  );
}
