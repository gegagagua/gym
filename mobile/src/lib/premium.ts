import { useAuth } from '@/store/auth';

/** premium-ის ერთადერთი წყარო კლიენტზე — სერვერის `/me.subscription` */
export function usePremium() {
  const subscription = useAuth((s) => s.user?.subscription);

  return {
    isPremium: subscription?.is_premium ?? false,
    subscription: subscription ?? null,
  };
}
