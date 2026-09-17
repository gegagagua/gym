import { create } from 'zustand';
import * as Crypto from 'expo-crypto';
import { db } from '@/db';
import { localSessions, localSets } from '@/db/schema';
import { estimateSetXp } from '@/lib/xp';
import { resolveLoop, type ResolvedLoop } from '@/lib/media';
import type { PlannedExercise, SessionSource, Tempo } from '@/api/types';

export type Phase = 'idle' | 'ready' | 'work' | 'rest' | 'summary';

export interface PlayerExercise {
  exerciseId: number;
  name: string;
  slug: string;
  unit: 'reps' | 'seconds';
  difficultyCoef: number;
  force: string;
  loop: ResolvedLoop | null;
  sets: number;
  targetReps: number | null;
  targetSeconds: number | null;
  restSeconds: number;
  tempo: Tempo;
}

export interface LoggedSet {
  exerciseId: number;
  setNo: number;
  reps: number | null;
  seconds: number | null;
  addedWeightKg: number;
  tempo: Tempo;
  restAfterMs: number | null;
  startedAt: string;
  completedAt: string;
  estimatedXp: number;
}

interface PlayerState {
  clientUuid: string | null;
  programDayId: number | null;
  planDayId: number | null;
  spotCheckinId: number | null;
  source: SessionSource;
  bodyweightKg: number;

  exercises: PlayerExercise[];
  logged: LoggedSet[];

  index: number;      // მიმდინარე სავარჯიშო
  setNo: number;      // მიმდინარე სეტი (1-იდან)
  phase: Phase;
  restRemaining: number;
  startedAt: string | null;
  setStartedAt: string | null;
  painStop: boolean;

  start: (config: {
    exercises: PlayerExercise[];
    programDayId?: number | null;
    planDayId?: number | null;
    spotCheckinId?: number | null;
    source?: SessionSource;
    bodyweightKg?: number;
  }) => void;

  /** გაიდ-პლეიერი (კეგელი) სეტებს თავად აგროვებს — პირდაპირ შეჯამებაზე */
  completeGuided: (config: {
    exercises: PlayerExercise[];
    logged: LoggedSet[];
    startedAt: string;
    source: SessionSource;
    painStop?: boolean;
  }) => void;

  beginWork: () => void;
  logSet: (value: { reps?: number; seconds?: number; addedWeightKg?: number }) => void;
  tickRest: () => void;
  skipRest: () => void;
  jumpTo: (index: number) => void;
  reportPain: () => void;
  endEarly: () => void;
  finish: () => Promise<{ clientUuid: string; estimatedXp: number; durationMs: number } | null>;
  reset: () => void;

  current: () => PlayerExercise | null;
  estimatedXp: () => number;
  totalSets: () => number;
  completedSets: () => number;
}

const initial = {
  clientUuid: null,
  programDayId: null,
  planDayId: null,
  spotCheckinId: null,
  source: 'freestyle' as SessionSource,
  bodyweightKg: 75,
  exercises: [],
  logged: [],
  index: 0,
  setNo: 1,
  phase: 'idle' as Phase,
  restRemaining: 0,
  startedAt: null,
  setStartedAt: null,
  painStop: false,
};

export const usePlayer = create<PlayerState>((set, get) => ({
  ...initial,

  start({ exercises, programDayId = null, planDayId = null, spotCheckinId = null, source = 'freestyle', bodyweightKg = 75 }) {
    set({
      ...initial,
      clientUuid: Crypto.randomUUID(),
      exercises,
      programDayId,
      planDayId,
      spotCheckinId,
      source,
      bodyweightKg,
      phase: 'ready',
      startedAt: new Date().toISOString(),
      setStartedAt: new Date().toISOString(),
    });
  },

  completeGuided({ exercises, logged, startedAt, source, painStop = false }) {
    set({
      ...initial,
      clientUuid: Crypto.randomUUID(),
      exercises,
      logged,
      source,
      painStop,
      startedAt,
      phase: 'summary',
    });
  },

  /** 3-2-1 ათვლის შემდეგ — სესიის ტაიმერი აქედან ითვლება */
  beginWork() {
    set({ phase: 'work', startedAt: new Date().toISOString(), setStartedAt: new Date().toISOString() });
  },

  logSet({ reps, seconds, addedWeightKg = 0 }) {
    const state = get();
    const exercise = state.exercises[state.index];
    if (!exercise) return;

    const now = new Date().toISOString();

    const entry: LoggedSet = {
      exerciseId: exercise.exerciseId,
      setNo: state.setNo,
      reps: exercise.unit === 'reps' ? (reps ?? 0) : null,
      seconds: exercise.unit === 'seconds' ? (seconds ?? 0) : null,
      addedWeightKg,
      tempo: exercise.tempo,
      restAfterMs: exercise.restSeconds * 1000,
      startedAt: state.setStartedAt ?? now,
      completedAt: now,
      estimatedXp: estimateSetXp({
        difficultyCoef: exercise.difficultyCoef,
        unit: exercise.unit,
        reps,
        seconds,
        addedWeightKg,
        bodyweightKg: state.bodyweightKg,
        tempo: exercise.tempo,
        hasCheckin: state.spotCheckinId !== null,
      }),
    };

    const isLastSet = state.setNo >= exercise.sets;
    const isLastExercise = state.index >= state.exercises.length - 1;

    if (isLastSet && isLastExercise) {
      set({ logged: [...state.logged, entry], phase: 'summary' });
      return;
    }

    set({
      logged: [...state.logged, entry],
      phase: 'rest',
      restRemaining: exercise.restSeconds,
      // კურსორი დასვენების დროსვე გადადის — ეკრანი უკვე შემდეგს აჩვენებს
      index: isLastSet ? state.index + 1 : state.index,
      setNo: isLastSet ? 1 : state.setNo + 1,
    });
  },

  tickRest() {
    const { restRemaining, phase } = get();
    if (phase !== 'rest') return;

    if (restRemaining <= 1) {
      set({ phase: 'work', restRemaining: 0, setStartedAt: new Date().toISOString() });
    } else {
      set({ restRemaining: restRemaining - 1 });
    }
  },

  skipRest() {
    set({ phase: 'work', restRemaining: 0, setStartedAt: new Date().toISOString() });
  },

  jumpTo(index) {
    if (index < 0 || index >= get().exercises.length) return;
    set({ index, setNo: 1, phase: 'work', restRemaining: 0, setStartedAt: new Date().toISOString() });
  },

  /**
   * „ტკივილი მაქვს" — სესია ჩერდება და პირდაპირ შეჯამებაზე გადადის.
   * დალოგილი სეტები ინახება: შესრულებული სამუშაო არ იკარგება (სპეც. 18).
   */
  reportPain() {
    set({ painStop: true, phase: 'summary' });
  },

  /** ჩვეულებრივი ვადამდე დასრულება — ტკივილის დროშის გარეშე */
  endEarly() {
    set({ phase: 'summary' });
  },

  async finish() {
    const state = get();
    if (!state.clientUuid || !state.startedAt || state.logged.length === 0) return null;

    const completedAt = new Date();
    const durationMs = completedAt.getTime() - new Date(state.startedAt).getTime();
    const estimatedXp = state.estimatedXp();

    await db.insert(localSessions).values({
      clientUuid: state.clientUuid,
      programDayId: state.programDayId,
      planDayId: state.planDayId,
      spotCheckinId: state.spotCheckinId,
      startedAt: state.startedAt,
      completedAt: completedAt.toISOString(),
      durationMs,
      source: state.source,
      deviceClockOffsetMs: 0,
      status: 'pending',
      estimatedXp,
      createdAt: completedAt.toISOString(),
    });

    await db.insert(localSets).values(
      state.logged.map((entry) => ({
        clientUuid: state.clientUuid!,
        exerciseId: entry.exerciseId,
        setNo: entry.setNo,
        reps: entry.reps,
        seconds: entry.seconds,
        addedWeightKg: entry.addedWeightKg,
        tempo: entry.tempo,
        restAfterMs: entry.restAfterMs,
        startedAt: entry.startedAt,
        completedAt: entry.completedAt,
      })),
    );

    return { clientUuid: state.clientUuid, estimatedXp, durationMs };
  },

  reset() {
    set({ ...initial });
  },

  current() {
    return get().exercises[get().index] ?? null;
  },

  estimatedXp() {
    return get().logged.reduce((sum, entry) => sum + entry.estimatedXp, 0);
  },

  totalSets() {
    return get().exercises.reduce((sum, exercise) => sum + exercise.sets, 0);
  },

  completedSets() {
    return get().logged.length;
  },
}));

/** API-ს დაგეგმილი სავარჯიშოები → პლეიერის ფორმატი */
export function toPlayerExercises(planned: PlannedExercise[]): PlayerExercise[] {
  return planned
    .filter((item) => item.exercise)
    .map((item) => ({
      exerciseId: item.exercise_id,
      name: item.exercise!.name ?? item.exercise!.slug,
      slug: item.exercise!.slug,
      unit: item.exercise!.unit,
      difficultyCoef: item.exercise!.difficulty_coef,
      force: item.exercise!.force,
      loop: resolveLoop(item.exercise!.media),
      sets: item.sets,
      targetReps: item.target_reps,
      targetSeconds: item.target_seconds,
      restSeconds: item.rest_seconds,
      tempo: item.tempo ?? 'normal',
    }));
}
