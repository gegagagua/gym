import type { Tempo } from '@/api/types';

/**
 * კლიენტის XP ესტიმაცია — მხოლოდ ვიზუალური უკუკავშირისთვის
 * სესიის მიმდინარეობისას.
 *
 * ⚠️ ეს არ არის ჭეშმარიტება. სერვერი თავიდან ითვლის ყველაფერს და
 * კლიენტიდან მოსულ მნიშვნელობას იგნორირებს (სპეც. 8.2). აქ არ არის
 * streak-ის, ჭერისა და diminishing returns-ის ლოგიკა — ისინი
 * მთელი დღის კონტექსტს საჭიროებენ, რომელიც კლიენტს არ აქვს.
 */
export interface EstimateInput {
  difficultyCoef: number;
  unit: 'reps' | 'seconds';
  reps?: number | null;
  seconds?: number | null;
  addedWeightKg?: number;
  bodyweightKg?: number;
  tempo?: Tempo;
  hasCheckin?: boolean;
}

export const HOLD_SECONDS_PER_UNIT = 5;
export const CHECKIN_MOD = 1.15;
export const TEMPO_SLOW_MOD = 1.2;
export const WEIGHT_MOD_MAX = 2.0;

export function estimateSetXp(input: EstimateInput): number {
  const {
    difficultyCoef,
    unit,
    reps,
    seconds,
    addedWeightKg = 0,
    bodyweightKg = 75,
    tempo = 'normal',
    hasCheckin = false,
  } = input;

  const base =
    unit === 'seconds'
      ? difficultyCoef * ((seconds ?? 0) / HOLD_SECONDS_PER_UNIT)
      : difficultyCoef * (reps ?? 0);

  if (base <= 0) return 0;

  const weightMod =
    addedWeightKg > 0 && bodyweightKg > 0
      ? Math.min(WEIGHT_MOD_MAX, 1 + addedWeightKg / bodyweightKg)
      : 1;

  const tempoMod = tempo === 'slow' ? TEMPO_SLOW_MOD : 1;
  const checkinMod = hasCheckin ? CHECKIN_MOD : 1;

  return Math.round(base * weightMod * tempoMod * checkinMod);
}

export function formatXp(value: number): string {
  return value >= 10000 ? `${(value / 1000).toFixed(1)}k` : String(value);
}

export function formatDuration(ms: number): string {
  const totalSeconds = Math.floor(ms / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

export function formatClock(seconds: number): string {
  const m = Math.floor(Math.max(0, seconds) / 60);
  const s = Math.max(0, seconds) % 60;

  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
