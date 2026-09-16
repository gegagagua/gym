import { create } from 'zustand';

/**
 * ონბორდინგის დროებითი მდგომარეობა. სერვერზე ერთი მოთხოვნით
 * იგზავნება ბოლოს — შუალედური PATCH-ები ქსელის გარეშე ონბორდინგს
 * გატეხავდა.
 */
interface Draft {
  goal: string | null;
  equipment: string[];
  birthYear: number | null;
  gender: string | null;
  heightCm: number | null;
  weightKg: number | null;
  pushup: number;
  pullup: number;
  plankSec: number;
  level: number | null;
  recommendedProgramId: number | null;

  set: (patch: Partial<Omit<Draft, 'set' | 'reset'>>) => void;
  reset: () => void;
}

const initial = {
  goal: null,
  equipment: [] as string[],
  birthYear: null,
  gender: null,
  heightCm: null,
  weightKg: null,
  pushup: 10,
  pullup: 3,
  plankSec: 45,
  level: null,
  recommendedProgramId: null,
};

export const useOnboardingDraft = create<Draft>((set) => ({
  ...initial,
  set: (patch) => set(patch),
  reset: () => set(initial),
}));
