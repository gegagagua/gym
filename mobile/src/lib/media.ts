import type { ExerciseMediaItem } from '@/api/types';

/**
 * ღია წყაროებიდან აწყობილი ლუპები ანიმირებული GIF-ია, საკუთარი გადაღება
 * კი mp4 იქნება. არასწორი რენდერერი GIF-ს უძრავად აჩვენებს და შეცდომას
 * არ აგდებს — ამიტომ არჩევანი ერთ ადგილას ზის.
 */
const VIDEO_PATTERN = /\.(mp4|m4v|mov|webm)(\?|$)/i;

export interface ResolvedLoop {
  url: string;
  kind: 'video' | 'image';
  /** true — სტატიკური კადრია და არა მოძრაობა (planche, front lever) */
  still: boolean;
  license: string | null;
  credit: string | null;
  sourceUrl: string | null;
}

/** `loop` სჯობს; თუ არ არის, სტატიკური კადრიც სჯობია ცარიელ კვადრატს */
export function resolveLoop(media?: ExerciseMediaItem[] | null): ResolvedLoop | null {
  if (!media?.length) return null;

  const loop = media.find((item) => item.type === 'loop');
  const chosen = loop ?? media.find((item) => item.type === 'thumbnail');

  if (!chosen) return null;

  return {
    url: chosen.url,
    kind: VIDEO_PATTERN.test(chosen.url) ? 'video' : 'image',
    still: !loop,
    license: chosen.license ?? null,
    credit: chosen.attribution_text ?? null,
    sourceUrl: chosen.source_url ?? null,
  };
}

/** ატრიბუციის ჩვენება მხოლოდ იქ, სადაც ლიცენზია ამას ითხოვს (სპეც. 13.1) */
export function requiresAttribution(loop: ResolvedLoop | null): boolean {
  return !!loop?.credit && !!loop.license && !['own', 'public-domain'].includes(loop.license);
}
