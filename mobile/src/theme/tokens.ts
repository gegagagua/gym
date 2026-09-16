import { Platform } from 'react-native';
import { Easing } from 'react-native-reanimated';

/** 4pt ბადე. ყველა ჰორიზონტალური padding gutter-ის ჯერადია. */
export const space = {
  none: 0,
  xxs: 2,
  xs: 4,
  sm: 8,
  md: 12,
  base: 16,
  lg: 20,
  xl: 24,
  xxl: 32,
  xxxl: 44,
  huge: 64,
} as const;

export const gutter = space.lg; // 20 — ეკრანის გვერდითი ველი

export const radius = {
  xs: 6,
  sm: 10,
  md: 14,
  lg: 20,
  xl: 28,
  pill: 999,
} as const;

export const border = {
  hair: Platform.select({ ios: 0.5, default: 1 })!,
  thin: 1,
  thick: 2,
} as const;

/**
 * ტიპოგრაფია ორ ოჯახზეა აწყობილი:
 *  - display: Unbounded — მხოლოდ ციფრები/ლათინური ჰედლაინები (XP, რანგი, ტაიმერი)
 *  - text:    Noto Sans Georgian — ყველა წინადადება (ka/en; ru გლიფები სისტემიდან იძებნება)
 *
 * ქართული სიტყვები საშუალოდ 25–40%-ით გრძელია რუსულ/ინგლისურ ეკვივალენტზე,
 * ამიტომ ღილაკის ტექსტი არასდროს არ არის `display` და ყოველთვის numberOfLines={1}
 * + adjustsFontSizeToFit-ით იჭრება (იხ. components/Button).
 */
export const font = {
  display: 'Unbounded_700Bold',
  displayBlack: 'Unbounded_900Black',
  text: 'NotoSansGeorgian_400Regular',
  textMedium: 'NotoSansGeorgian_500Medium',
  textBold: 'NotoSansGeorgian_700Bold',
  mono: Platform.select({ ios: 'Menlo', default: 'monospace' })!,
} as const;

export const type = {
  hero: { fontFamily: font.displayBlack, fontSize: 56, lineHeight: 58, letterSpacing: -2 },
  display: { fontFamily: font.display, fontSize: 38, lineHeight: 42, letterSpacing: -1.4 },
  title: { fontFamily: font.textBold, fontSize: 24, lineHeight: 32, letterSpacing: -0.4 },
  heading: { fontFamily: font.textBold, fontSize: 19, lineHeight: 26, letterSpacing: -0.2 },
  subheading: { fontFamily: font.textMedium, fontSize: 16, lineHeight: 23 },
  body: { fontFamily: font.text, fontSize: 15, lineHeight: 23 },
  bodySm: { fontFamily: font.text, fontSize: 13, lineHeight: 19 },
  label: { fontFamily: font.textMedium, fontSize: 13, lineHeight: 17, letterSpacing: 0.1 },
  caption: { fontFamily: font.text, fontSize: 11.5, lineHeight: 15 },
  /** ჩუმი, სპაციირებული ეტიკეტი სექციების თავზე */
  overline: {
    fontFamily: font.textMedium,
    fontSize: 11,
    lineHeight: 14,
    letterSpacing: 1.4,
    textTransform: 'uppercase' as const,
  },
  /** ციფრები, რომლებიც სვეტში უნდა დაჯდეს (ლიდერბორდი, ტაიმერი) */
  numeric: { fontFamily: font.display, fontSize: 17, fontVariant: ['tabular-nums' as const] },
  timer: { fontFamily: font.displayBlack, fontSize: 88, letterSpacing: -4, fontVariant: ['tabular-nums' as const] },
} as const;

/**
 * ჩრდილს თითქმის არ ვიყენებთ — შავ ფონზე ის უხილავია.
 * სიღრმეს ქმნის hairline ბორდერი + ფერადი glow.
 */
export const glow = (color: string, radiusPx = 24) => ({
  shadowColor: color,
  shadowOpacity: Platform.OS === 'ios' ? 0.9 : 1,
  shadowRadius: radiusPx,
  shadowOffset: { width: 0, height: 0 },
  elevation: 0,
});

export const duration = {
  instant: 90,
  fast: 160,
  base: 240,
  slow: 380,
  lazy: 620,
} as const;

/**
 * მოძრაობის ენა.
 *
 * ერთი წესი: ინტერფეისი შემოდის სწრაფად და ჩერდება რბილად (`out`),
 * გადის სწრაფად და მკვეთრად (`in`). „ხტუნვა“ მხოლოდ იქ, სადაც
 * მიღწევაა — XP, რეკორდი, skill unlock. ვარჯიშის ეკრანზე მოძრაობა
 * არასდროს არ არის დეკორატიული: ის ან სტატუსს აჩვენებს, ან რიტმს იჭერს.
 */
export const easing = {
  /** expo-out — შემოსვლა, ჩერდება ხანგრძლივად და რბილად */
  out: Easing.bezier(0.16, 1, 0.3, 1),
  /** ორმხრივი — მდგომარეობის შეცვლა ადგილზე */
  inOut: Easing.bezier(0.65, 0, 0.35, 1),
  /** გასვლა — სწრაფად ეხსნება ეკრანს */
  in: Easing.bezier(0.7, 0, 0.84, 0),
  linear: Easing.linear,
} as const;

/** კასკადის ბიჯი — ბლოკები რიგრიგობით შემოდის, არა ერთდროულად */
export const stagger = 55;

/** ერთი spring ყველგან — ინტერფეისი ერთნაირად „წონიანია“ */
export const spring = { damping: 18, stiffness: 190, mass: 0.9 } as const;
export const springSnappy = { damping: 14, stiffness: 320, mass: 0.6 } as const;
/** მძიმე და მშვიდი — დიდი ზედაპირები, ფურცლები */
export const springSoft = { damping: 24, stiffness: 130, mass: 1 } as const;
/** მიღწევის ხტუნვა — XP, რეკორდი, unlock. სხვაგან არ გამოიყენება. */
export const springBouncy = { damping: 10, stiffness: 240, mass: 0.65 } as const;

export const hitSlop = { top: 10, bottom: 10, left: 10, right: 10 } as const;
