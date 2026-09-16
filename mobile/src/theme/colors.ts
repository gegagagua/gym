/**
 * NEON CONCRETE — Kalisteni design system.
 *
 * ქუჩის მოედნის ესთეტიკა: ბეტონის ცივი ნაცრისფერი ფონი,
 * მჟავე-ლაიმის ენერგია აქცენტში, ელექტრო-იისფერი რანგისთვის,
 * ცეცხლოვანი ქარვისფერი streak-ისთვის.
 *
 * აპი dark-first-ია. light თემა v1-ში არ არსებობს — ვარჯიში
 * ხდება გარეთ, ეკრანი უნდა იყოს კონტრასტული და არა თეთრი.
 */

export const palette = {
  // ბეტონი — ფონები
  void: '#06070A',
  asphalt: '#0C0E13',
  concrete: '#13161D',
  concreteHi: '#1A1E27',
  steel: '#242935',
  hairline: '#2C3240',

  // ტექსტი
  chalk: '#F4F6FA',
  ash: '#A7AEBC',
  smoke: '#6B7280',
  ghost: '#454C5A',

  // ენერგია
  lime: '#D7FF3E',
  limeDim: '#A8C92C',
  limeGlow: 'rgba(215, 255, 62, 0.16)',

  violet: '#8B5CFF',
  violetDim: '#6B44D9',
  violetGlow: 'rgba(139, 92, 255, 0.16)',

  ember: '#FF6B35',
  emberDim: '#D14D1D',
  emberGlow: 'rgba(255, 107, 53, 0.16)',

  cyan: '#3EE8FF',
  cyanGlow: 'rgba(62, 232, 255, 0.14)',

  // სტატუსი
  go: '#34D07F',
  warn: '#FFB020',
  stop: '#FF4D5E',
} as const;

export const colors = {
  bg: palette.void,
  bgElevated: palette.asphalt,
  surface: palette.concrete,
  surfaceHi: palette.concreteHi,
  surfacePressed: palette.steel,
  border: palette.hairline,
  borderStrong: palette.steel,

  text: palette.chalk,
  textSecondary: palette.ash,
  textMuted: palette.smoke,
  textDisabled: palette.ghost,

  accent: palette.lime,
  accentDim: palette.limeDim,
  accentGlow: palette.limeGlow,
  onAccent: palette.void,

  rank: palette.violet,
  rankDim: palette.violetDim,
  rankGlow: palette.violetGlow,

  streak: palette.ember,
  streakDim: palette.emberDim,
  streakGlow: palette.emberGlow,

  info: palette.cyan,
  infoGlow: palette.cyanGlow,

  success: palette.go,
  warning: palette.warn,
  danger: palette.stop,

  overlay: 'rgba(6, 7, 10, 0.82)',
  scrim: 'rgba(6, 7, 10, 0.55)',
} as const;

/** ლიგის დივიზიონები — 1..5 (ბრინჯაო → ელიტა) */
export const divisionTheme = {
  1: { key: 'bronze', tint: '#C88B52', glow: 'rgba(200,139,82,0.18)' },
  2: { key: 'silver', tint: '#BFC8D6', glow: 'rgba(191,200,214,0.18)' },
  3: { key: 'gold', tint: '#FFC94A', glow: 'rgba(255,201,74,0.18)' },
  4: { key: 'platinum', tint: '#7FE7E0', glow: 'rgba(127,231,224,0.18)' },
  5: { key: 'elite', tint: '#C77DFF', glow: 'rgba(199,125,255,0.20)' },
} as const;

/** ვერიფიკაციის დონე T0..T3 */
export const tierTheme = {
  0: { tint: palette.smoke, label: 'T0' },
  1: { tint: palette.chalk, label: 'T1' },
  2: { tint: palette.cyan, label: 'T2' },
  3: { tint: palette.lime, label: 'T3' },
} as const;

/** ძალის ვექტორი — ბიბლიოთეკის ფილტრების ფერადი კოდი */
export const forceTheme = {
  push: palette.lime,
  pull: palette.violet,
  static: palette.cyan,
  legs: palette.ember,
  core: '#FFD166',
} as const;

export type DivisionId = keyof typeof divisionTheme;
export type ForceKey = keyof typeof forceTheme;
