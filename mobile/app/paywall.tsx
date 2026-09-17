import { useEffect, useState } from 'react';
import { View, ScrollView, Linking, Alert } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import type { PurchasesPackage } from 'react-native-purchases';
import { Text, Card, Button, Tap, Glow, Grain, Reveal, Skeleton, Pop } from '@/components';
import { colors, gutter, radius, space, border } from '@/theme';
import { billing } from '@/api/endpoints';
import { useAuth } from '@/store/auth';
import { usePremium } from '@/lib/premium';
import {
  identify,
  legalUrls,
  manageSubscriptionsUrl,
  monthlyPackage,
  purchase,
  purchasesEnabled,
  restore,
} from '@/lib/purchases';

const FEATURES = ['calendar', 'locations', 'intensity', 'progression', 'regenerate'] as const;

/**
 * Premium — $1/თვე, მხოლოდ კალენდარის პლანერი. დანარჩენი აპი უფასოა.
 * ფასი store-ის ლოკალიზებული სტრიქონიდან, ავტო-განახლების პირობები და
 * აღდგენა ეკრანზეა — App Store 3.1.2 ამას ითხოვს.
 */
export default function PaywallScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { next } = useLocalSearchParams<{ next?: string }>();
  const user = useAuth((s) => s.user);
  const refreshUser = useAuth((s) => s.refreshUser);
  const { isPremium, subscription } = usePremium();

  const [pkg, setPkg] = useState<PurchasesPackage | null>(null);
  const [loadingOffer, setLoadingOffer] = useState(purchasesEnabled);
  const [busy, setBusy] = useState<'buy' | 'restore' | null>(null);

  useEffect(() => {
    if (!purchasesEnabled || !user) return;
    let cancelled = false;

    (async () => {
      try {
        await identify(user.id);
        const found = await monthlyPackage();
        if (!cancelled) setPkg(found);
      } catch {
        // offering-ი არ ჩაიტვირთა — ღილაკი გამორთული რჩება, ეკრანი არ ვარდება
      } finally {
        if (!cancelled) setLoadingOffer(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [user]);

  /** store-ის შემდეგ სერვერს ვკითხავ — webhook-ის დაგვიანება UI-ს არ უნდა ბლოკოს */
  const settle = async () => {
    const state = await billing.sync().catch(() => null);
    await refreshUser();

    if (state?.is_premium) {
      if (next) router.replace(next as never);
      else router.back();
      return true;
    }

    return false;
  };

  const onBuy = async () => {
    if (!pkg) return;
    setBusy('buy');

    try {
      const outcome = await purchase(pkg);
      if (outcome === 'purchased' && !(await settle())) {
        Alert.alert(t('paywall.title'), t('paywall.pending'));
      }
    } catch {
      Alert.alert(t('paywall.title'), t('common.error'));
    } finally {
      setBusy(null);
    }
  };

  const onRestore = async () => {
    setBusy('restore');

    try {
      if (user) await identify(user.id);
      await restore();
      if (!(await settle())) Alert.alert(t('paywall.restore'), t('paywall.nothingToRestore'));
    } catch {
      Alert.alert(t('paywall.restore'), t('common.error'));
    } finally {
      setBusy(null);
    }
  };

  const price = pkg?.product.priceString;

  return (
    <View style={{ flex: 1, backgroundColor: colors.bg }}>
      <Glow color={colors.rank} size={520} opacity={0.3} style={{ top: -260, left: -140 }} />
      <Grain />

      <ScrollView
        contentContainerStyle={{
          paddingTop: insets.top + space.sm,
          paddingBottom: insets.bottom + space.huge,
          paddingHorizontal: gutter,
          gap: space.base,
        }}
        showsVerticalScrollIndicator={false}
      >
        <Reveal from="top" distance={8} style={{ alignSelf: 'flex-start' }}>
          <Tap onPress={() => router.back()}>
            <Text variant="label" tone="muted">
              ← {t('common.back')}
            </Text>
          </Tap>
        </Reveal>

        <Reveal index={1} style={{ gap: space.xs }}>
          <Text variant="overline" tone="rank">
            Kalisteni Premium
          </Text>
          <Text variant="title">{t('paywall.title')}</Text>
          <Text variant="bodySm" tone="secondary">
            {t('paywall.sub')}
          </Text>
        </Reveal>

        <Reveal index={2} zoom>
          <Card accent={colors.rank}>
            <View style={{ gap: space.md }}>
              {FEATURES.map((key) => (
                <View key={key} style={{ flexDirection: 'row', gap: space.md, alignItems: 'flex-start' }}>
                  <View
                    style={{
                      width: 22,
                      height: 22,
                      borderRadius: 11,
                      marginTop: 1,
                      alignItems: 'center',
                      justifyContent: 'center',
                      backgroundColor: `${colors.rank}26`,
                    }}
                  >
                    <Text variant="caption" tone="rank">
                      ✓
                    </Text>
                  </View>
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text variant="subheading">{t(`paywall.feature_${key}`)}</Text>
                    <Text variant="caption" tone="muted">
                      {t(`paywall.feature_${key}_hint`)}
                    </Text>
                  </View>
                </View>
              ))}
            </View>
          </Card>
        </Reveal>

        {isPremium ? (
          <Reveal index={3}>
            <Card accent={colors.success}>
              <Pop trigger={isPremium}>
                <Text variant="heading" tone="success">
                  {t('paywall.active')}
                </Text>
              </Pop>
              {subscription?.expires_at ? (
                <Text variant="bodySm" tone="secondary" style={{ marginTop: space.xs }}>
                  {t(subscription.will_renew ? 'paywall.renewsOn' : 'paywall.endsOn', {
                    date: new Date(subscription.expires_at).toLocaleDateString(),
                  })}
                </Text>
              ) : null}
              <View style={{ gap: space.sm, marginTop: space.base }}>
                <Button title={t('paywall.openPlanner')} onPress={() => router.replace('/plan')} />
                {subscription?.provider === 'revenuecat' ? (
                  <Button
                    title={t('paywall.manage')}
                    variant="ghost"
                    onPress={() => Linking.openURL(manageSubscriptionsUrl)}
                  />
                ) : null}
              </View>
            </Card>
          </Reveal>
        ) : (
          <Reveal index={3} style={{ gap: space.sm }}>
            <View
              style={{
                padding: space.base,
                borderRadius: radius.lg,
                borderWidth: border.thin,
                borderColor: colors.rank,
                backgroundColor: `${colors.rank}14`,
                flexDirection: 'row',
                alignItems: 'center',
                justifyContent: 'space-between',
              }}
            >
              <View style={{ gap: 2 }}>
                <Text variant="subheading">{t('paywall.monthly')}</Text>
                <Text variant="caption" tone="muted">
                  {t('paywall.cancelAnytime')}
                </Text>
              </View>
              {loadingOffer ? (
                <Skeleton width={70} height={26} />
              ) : (
                <Text variant="heading">{price ? `${price}${t('paywall.perMonth')}` : '—'}</Text>
              )}
            </View>

            {purchasesEnabled ? null : (
              <Text variant="caption" tone="muted" center>
                {t('paywall.unavailable')}
              </Text>
            )}

            <Button
              title={t('paywall.subscribe')}
              onPress={onBuy}
              loading={busy === 'buy'}
              disabled={!pkg || busy !== null}
              haptic="success"
            />
            <Button
              title={t('paywall.restore')}
              variant="ghost"
              onPress={onRestore}
              loading={busy === 'restore'}
              disabled={!purchasesEnabled || busy !== null}
            />
          </Reveal>
        )}

        <Reveal index={4} style={{ gap: space.sm }}>
          <Text variant="caption" tone="muted">
            {t('paywall.legal', { price: price ?? '$1' })}
          </Text>
          <View style={{ flexDirection: 'row', gap: space.lg }}>
            {legalUrls.terms ? (
              <Tap onPress={() => Linking.openURL(legalUrls.terms!)}>
                <Text variant="caption" tone="secondary">
                  {t('paywall.terms')}
                </Text>
              </Tap>
            ) : null}
            {legalUrls.privacy ? (
              <Tap onPress={() => Linking.openURL(legalUrls.privacy!)}>
                <Text variant="caption" tone="secondary">
                  {t('paywall.privacy')}
                </Text>
              </Tap>
            ) : null}
          </View>
        </Reveal>
      </ScrollView>
    </View>
  );
}
