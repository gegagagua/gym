import { Platform } from 'react-native';
import Constants from 'expo-constants';
import Purchases, { PURCHASES_ERROR_CODE, type PurchasesPackage } from 'react-native-purchases';

/**
 * RevenueCat — $1/თვე premium (კალენდარის პლანერი).
 *
 * აპ-მომხმარებლის id = ჩვენი users.id: webhook ამით პოულობს ვის
 * მიენიჭოს premium. ყიდვის შემდეგ კლიენტის `CustomerInfo`-ს არ ვენდობით —
 * წვდომას სერვერი ადგენს (`/me/subscription/sync`).
 */
const extra = Constants.expoConfig?.extra as
  | { revenuecat?: { iosApiKey?: string; androidApiKey?: string }; legal?: { termsUrl?: string; privacyUrl?: string } }
  | undefined;

const apiKey =
  Platform.select({ ios: extra?.revenuecat?.iosApiKey, android: extra?.revenuecat?.androidApiKey }) || null;

export const purchasesEnabled = Boolean(apiKey);
export const legalUrls = {
  terms: extra?.legal?.termsUrl || null,
  privacy: extra?.legal?.privacyUrl || null,
};

let identifiedAs: string | null = null;

export async function identify(userId: number) {
  if (!apiKey) return;
  const id = String(userId);

  if (identifiedAs === null) {
    Purchases.configure({ apiKey, appUserID: id });
  } else if (identifiedAs !== id) {
    await Purchases.logIn(id);
  }

  identifiedAs = id;
}

/** მიმდინარე offering-ის თვიური პაკეტი — ფასი store-ის ლოკალიზებული სტრიქონია */
export async function monthlyPackage(): Promise<PurchasesPackage | null> {
  const offerings = await Purchases.getOfferings();
  const current = offerings.current;

  return current?.monthly ?? current?.availablePackages[0] ?? null;
}

export async function purchase(pkg: PurchasesPackage): Promise<'purchased' | 'cancelled'> {
  try {
    await Purchases.purchasePackage(pkg);
    return 'purchased';
  } catch (error) {
    if ((error as { code?: string }).code === PURCHASES_ERROR_CODE.PURCHASE_CANCELLED_ERROR) return 'cancelled';
    throw error;
  }
}

export async function restore() {
  await Purchases.restorePurchases();
}

export const manageSubscriptionsUrl = Platform.select({
  ios: 'https://apps.apple.com/account/subscriptions',
  default: 'https://play.google.com/store/account/subscriptions',
});
