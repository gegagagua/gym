import type { PlanLocation } from '@/api/types';

/** 'YYYY-MM-DD' → ლოკალური თარიღი (UTC-ის ცვლის გარეშე) */
export function parseDay(value: string): Date {
  const [y, m, d] = value.split('-').map(Number);
  return new Date(y, m - 1, d);
}

export const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const;

export const LOCATIONS: PlanLocation[] = ['home', 'yard', 'gym'];

export const LOCATION_ICON: Record<PlanLocation, string> = {
  home: '⌂',
  yard: '⊓',
  gym: '▣',
};

/** პროფილის წვდომის დონე (none|bar|yard|gym) → დღის ნაგულისხმევი ლოკაცია */
export function defaultLocation(equipment: string[] = []): PlanLocation {
  if (equipment.includes('gym')) return 'gym';
  if (equipment.includes('yard') || equipment.includes('bar')) return 'yard';
  return 'home';
}
