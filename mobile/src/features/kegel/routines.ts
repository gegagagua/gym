/**
 * კეგელის გაიდ-რუტინები.
 *
 * pelvic floor გარედან არ ჩანს, ამიტომ ვიდეოს ნაცვლად რიტმი ჩვენდება:
 * ყოველი ნაბიჯი = ნიშანი (cue) + ხანგრძლივობა + შეკუმშვის სამიზნე 0..1.
 * ყოველი ბლოკი სერვერზე ერთ სეტად ჩაიწერება — ასე სესია 2 სავარჯიშოს
 * და 3 წუთის ზღვარს (SessionSyncService::isTooThin) ბუნებრივად გადის.
 */

export type Cue = 'contract' | 'hold' | 'release' | 'relax' | 'inhale' | 'exhale' | 'floor1' | 'floor2' | 'floor3';

export interface Step {
  cue: Cue;
  seconds: number;
  /** 0 = მოდუნებული, 1 = სრული შეკუმშვა */
  level: number;
}

export interface Block {
  slug: string;
  cycle: Step[];
  repeat: number;
  /** დასვენება ბლოკის შემდეგ, წამები */
  restAfter: number;
}

export interface Routine {
  key: 'beginner' | 'intermediate' | 'advanced';
  blocks: Block[];
}

const hold = (seconds: number, relax = seconds): Step[] => [
  { cue: 'contract', seconds: 1, level: 1 },
  { cue: 'hold', seconds, level: 1 },
  { cue: 'release', seconds: 1, level: 0 },
  { cue: 'relax', seconds: relax, level: 0 },
];

const flick: Step[] = [
  { cue: 'contract', seconds: 1, level: 1 },
  { cue: 'release', seconds: 1, level: 0 },
];

const breath: Step[] = [
  { cue: 'inhale', seconds: 4, level: 0 },
  { cue: 'exhale', seconds: 4, level: 0 },
];

const elevator: Step[] = [
  { cue: 'floor1', seconds: 2, level: 0.33 },
  { cue: 'floor2', seconds: 2, level: 0.66 },
  { cue: 'floor3', seconds: 2, level: 1 },
  { cue: 'hold', seconds: 2, level: 1 },
  { cue: 'floor2', seconds: 2, level: 0.66 },
  { cue: 'floor1', seconds: 2, level: 0.33 },
  { cue: 'release', seconds: 2, level: 0 },
  { cue: 'relax', seconds: 4, level: 0 },
];

const bridge: Step[] = [
  { cue: 'contract', seconds: 2, level: 1 },
  { cue: 'hold', seconds: 3, level: 1 },
  { cue: 'release', seconds: 2, level: 0 },
  { cue: 'relax', seconds: 2, level: 0 },
];

export const ROUTINES: Routine[] = [
  {
    key: 'beginner',
    blocks: [
      { slug: 'kegel-reverse-relax', cycle: breath, repeat: 6, restAfter: 10 },
      { slug: 'kegel-basic-hold', cycle: hold(3), repeat: 10, restAfter: 30 },
      { slug: 'kegel-quick-flick', cycle: flick, repeat: 10, restAfter: 30 },
      { slug: 'kegel-basic-hold', cycle: hold(3), repeat: 10, restAfter: 20 },
      { slug: 'kegel-reverse-relax', cycle: breath, repeat: 4, restAfter: 0 },
    ],
  },
  {
    key: 'intermediate',
    blocks: [
      { slug: 'kegel-reverse-relax', cycle: breath, repeat: 6, restAfter: 10 },
      { slug: 'kegel-basic-hold', cycle: hold(5), repeat: 10, restAfter: 30 },
      { slug: 'kegel-quick-flick', cycle: flick, repeat: 15, restAfter: 30 },
      { slug: 'kegel-long-hold', cycle: hold(8), repeat: 6, restAfter: 30 },
      { slug: 'glute-bridge-kegel', cycle: bridge, repeat: 10, restAfter: 30 },
      { slug: 'kegel-reverse-relax', cycle: breath, repeat: 4, restAfter: 0 },
    ],
  },
  {
    key: 'advanced',
    blocks: [
      { slug: 'kegel-reverse-relax', cycle: breath, repeat: 6, restAfter: 10 },
      { slug: 'kegel-long-hold', cycle: hold(10), repeat: 8, restAfter: 30 },
      { slug: 'kegel-elevator', cycle: elevator, repeat: 5, restAfter: 30 },
      { slug: 'kegel-quick-flick', cycle: flick, repeat: 20, restAfter: 30 },
      { slug: 'kegel-basic-hold', cycle: hold(5), repeat: 10, restAfter: 20 },
      { slug: 'glute-bridge-kegel', cycle: bridge, repeat: 12, restAfter: 30 },
      { slug: 'kegel-reverse-relax', cycle: breath, repeat: 4, restAfter: 0 },
    ],
  },
];

export interface TimelineStep extends Step {
  blockIndex: number;
  /** null — დასვენება ბლოკებს შორის */
  rep: number | null;
  startsAt: number;
}

/** რუტინა → წრფივი ტაიმლაინი (წამები); ტაიმერი მხოლოდ ინდექსს ეძებს */
export function buildTimeline(routine: Routine): { steps: TimelineStep[]; total: number } {
  const steps: TimelineStep[] = [];
  let t = 0;

  routine.blocks.forEach((block, blockIndex) => {
    for (let rep = 1; rep <= block.repeat; rep++) {
      for (const step of block.cycle) {
        steps.push({ ...step, blockIndex, rep, startsAt: t });
        t += step.seconds;
      }
    }

    if (block.restAfter > 0) {
      steps.push({ cue: 'relax', seconds: block.restAfter, level: 0, blockIndex, rep: null, startsAt: t });
      t += block.restAfter;
    }
  });

  return { steps, total: t };
}

/** ბლოკის ნამდვილი დატვირთვა: შეკუმშვის წამები × გამეორებები */
export function contractedSeconds(block: Block): number {
  return block.cycle.filter((s) => s.level > 0).reduce((sum, s) => sum + s.seconds, 0) * block.repeat;
}

export function routineMinutes(routine: Routine): number {
  return Math.round(buildTimeline(routine).total / 60);
}
