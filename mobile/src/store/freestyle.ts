import { create } from 'zustand';
import { resolveLoop } from '@/lib/media';
import type { Exercise } from '@/api/types';
import type { PlayerExercise } from './player';

/**
 * თავისუფალი ვარჯიშის კალათა.
 *
 * სესია სერვერზე უარყოფილია, თუ 2 სავარჯიშოზე ან 3 წუთზე ნაკლებია
 * (SessionSyncService::isTooThin). ამიტომ „ერთი სავარჯიშო → დაწყება“
 * არ გვაქვს — მომხმარებელი ჯერ კალათას აგროვებს და ეს ლიმიტი UI-შივე ჩანს.
 */
export const FREESTYLE_MIN_EXERCISES = 2;

const DEFAULT_SETS = 3;
const DEFAULT_REPS = 10;
const DEFAULT_SECONDS = 30;
const DEFAULT_REST_SECONDS = 90;

interface FreestyleState {
  items: PlayerExercise[];
  toggle: (exercise: Exercise) => void;
  remove: (exerciseId: number) => void;
  clear: () => void;
  has: (exerciseId: number) => boolean;
  ready: () => boolean;
}

/** ბიბლიოთეკის სავარჯიშო → პლეიერის ფორმატი, ნაგულისხმევი მიზნებით */
export function toFreestyleExercise(exercise: Exercise): PlayerExercise {
  return {
    exerciseId: exercise.id,
    name: exercise.name,
    slug: exercise.slug,
    unit: exercise.unit,
    difficultyCoef: exercise.difficulty_coef,
    force: exercise.force,
    loop: resolveLoop(exercise.media),
    sets: DEFAULT_SETS,
    targetReps: exercise.unit === 'reps' ? DEFAULT_REPS : null,
    targetSeconds: exercise.unit === 'seconds' ? DEFAULT_SECONDS : null,
    restSeconds: DEFAULT_REST_SECONDS,
    tempo: 'normal',
  };
}

export const useFreestyle = create<FreestyleState>((set, get) => ({
  items: [],

  toggle(exercise) {
    const items = get().items;
    const exists = items.some((item) => item.exerciseId === exercise.id);

    set({
      items: exists
        ? items.filter((item) => item.exerciseId !== exercise.id)
        : [...items, toFreestyleExercise(exercise)],
    });
  },

  remove(exerciseId) {
    set({ items: get().items.filter((item) => item.exerciseId !== exerciseId) });
  },

  clear() {
    set({ items: [] });
  },

  has(exerciseId) {
    return get().items.some((item) => item.exerciseId === exerciseId);
  },

  ready() {
    return get().items.length >= FREESTYLE_MIN_EXERCISES;
  },
}));
