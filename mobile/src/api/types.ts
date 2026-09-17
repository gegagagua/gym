export type Locale = 'ka' | 'ru' | 'en';
export type ForceKey = 'push' | 'pull' | 'static' | 'legs' | 'core';
export type Unit = 'reps' | 'seconds';
export type Tempo = 'normal' | 'slow';
export type Zone =
  | 'chest'
  | 'back'
  | 'shoulders'
  | 'arms'
  | 'core'
  | 'legs'
  | 'pelvic_floor'
  | 'full_body'
  | 'mobility';
export type SessionSource = 'program' | 'plan' | 'kegel' | 'freestyle' | 'test';
export type VerificationTier = 0 | 1 | 2 | 3;

export interface ExerciseMediaItem {
  type: 'loop' | 'thumbnail' | 'video' | 'anatomy';
  url: string;
  width?: number | null;
  height?: number | null;
  duration_ms?: number | null;
  /** own | cc-by | cc-by-sa | public-domain | licensed — ატრიბუციის ეკრანი ამით იგება */
  license?: string;
  attribution_text?: string | null;
  source_url?: string | null;
}

export interface Exercise {
  id: number;
  slug: string;
  name: string;
  short_desc: string | null;
  category: string;
  zone: Zone | null;
  force: ForceKey;
  mechanic: 'compound' | 'isolation';
  unit: Unit;
  difficulty_coef: number;
  level_min: number;
  level_max: number;
  equipment: string[];
  primary_muscles: string[];
  secondary_muscles: string[];
  skill_group: string | null;
  is_skill_unlock: boolean;
  unlock_bonus_xp: number;
  unlock_threshold: number;
  progression_from_id: number | null;
  progression_to_id: number | null;
  instructions: string[] | Record<string, string>;
  common_mistakes: string[] | Record<string, string>;
  media?: ExerciseMediaItem[];
  updated_at: string | null;
}

export interface Program {
  id: number;
  slug: string;
  title: string;
  description: string | null;
  track: 'home' | 'bar' | 'skills';
  level: number;
  duration_weeks: number;
  days_per_week: number;
  goals: string[];
  equipment: string[];
  is_premium: boolean;
}

export interface PlannedExercise {
  exercise_id: number;
  sets: number;
  target_reps: number | null;
  target_seconds: number | null;
  rest_seconds: number;
  tempo: Tempo;
  exercise: {
    id: number;
    slug: string;
    name: string | null;
    unit: Unit;
    zone?: Zone | null;
    force: ForceKey;
    difficulty_coef: number;
    media: ExerciseMediaItem[];
  } | null;
}

export interface ProgramToday {
  enrollment: { id: number; program_id: number; current_week: number; current_day: number } | null;
  program?: { data: Program };
  progress?: { done_days: number; total_days: number; percent: number };
  today: {
    id: number;
    week_no: number;
    day_no: number;
    type: 'workout' | 'rest' | 'test';
    est_minutes: number | null;
    exercises: PlannedExercise[];
  } | null;
}

export interface MeResponse {
  data: {
    id: number;
    username: string | null;
    display_name: string | null;
    avatar_url: string | null;
    locale: Locale;
    timezone: string;
    country_code: string;
    is_guest: boolean;
    social_enabled: boolean;
    subscription?: SubscriptionState;
    phone?: string | null;
    email?: string | null;
    profile: {
      level: number;
      goal: string | null;
      equipment: string[];
      city_id: number | null;
      is_public: boolean;
      birth_year: number | null;
      gender: string | null;
      height_cm?: number | null;
      weight_kg?: number | null;
      level_test?: Record<string, number> | null;
    } | null;
  };
}

export interface Attribution {
  license: string;
  attribution_text: string;
  source_url: string | null;
}

export interface Stats {
  period: string;
  xp: number;
  xp_total: number;
  xp_today: number;
  daily_cap: number;
  sessions: number;
  minutes: number;
  reps: number;
  hold_seconds: number;
  streak_days: number;
  longest_streak: number;
  level: number;
  muscle_load: Record<string, number>;
}

export interface NearbySpot {
  id: number;
  name: string;
  type: string;
  access: string;
  condition_rating: number;
  has_lighting: boolean;
  checkin_count: number;
  lat: number;
  lng: number;
  distance_m: number;
  active_now: number;
  photo_url: string | null;
  equipment: string[];
}

export interface SpotDetail {
  id: number;
  name: string;
  description: string | null;
  lat: number | null;
  lng: number | null;
  type: string;
  access: string;
  condition_rating: number;
  has_lighting: boolean;
  status: string;
  checkin_count: number;
  equipment?: string[];
  photos?: { url: string; thumb_url: string | null }[];
  trainers?: {
    id: number;
    name: string;
    photo_url: string | null;
    bio: string | null;
    is_verified: boolean;
    listing_tier: string;
    contacts: { instagram: string | null; phone: string | null; telegram: string | null } | null;
  }[];
}

export interface LeagueMemberRow {
  rank: number;
  xp: number;
  is_me: boolean;
  zone: 'promotion' | 'demotion' | 'stay';
  user: { id: number; username: string | null; display_name: string | null; avatar_url: string | null } | null;
}

export interface LeagueResponse {
  enabled: boolean;
  reason?: string;
  weekly_active?: number;
  required?: number;
  joined?: boolean;
  division?: 1 | 2 | 3 | 4 | 5;
  division_key?: string;
  week_start?: string;
  ends_at?: string;
  promote_count?: number;
  demote_count?: number;
  my_rank?: number | null;
  members?: LeagueMemberRow[];
}

export interface ConfigResponse {
  country: string;
  flags: Record<string, { enabled: boolean; config: Record<string, unknown> | null }>;
  limits: {
    daily_xp_cap: number;
    max_session_minutes: number;
    checkin_radius_m: number;
    sync_batch_max: number;
  };
  server_time: string;
}

export interface SyncSetPayload {
  exercise_id: number;
  set_no: number;
  reps?: number | null;
  seconds?: number | null;
  added_weight_kg?: number;
  tempo?: Tempo;
  rest_after_ms?: number | null;
  started_at?: string | null;
  completed_at?: string | null;
}

export interface SyncSessionPayload {
  client_uuid: string;
  program_day_id?: number | null;
  plan_day_id?: number | null;
  spot_checkin_id?: number | null;
  started_at: string;
  completed_at?: string | null;
  duration_ms?: number;
  source: SessionSource;
  device_clock_offset_ms?: number;
  sets: SyncSetPayload[];
}

export interface SessionSyncRequest {
  sessions: SyncSessionPayload[];
}

export interface SessionSyncResult {
  client_uuid: string;
  session_id: number;
  status: 'completed' | 'flagged' | 'rejected';
  verification_tier: VerificationTier;
  xp_awarded: number;
  flags: string[];
  duplicate: boolean;
  new_records: { exercise_id: number; metric: string; value: number }[];
  unlocked: { code: string; exercise_id: number; bonus_xp: number }[];
}

export interface SessionSyncResponse {
  results: SessionSyncResult[];
  user_totals: { xp_total: number; xp_today: number; streak_days: number; level: number };
}

/* ---- premium ($1/თვე) — სტატუსი მხოლოდ სერვერიდან ---- */

export interface SubscriptionState {
  is_premium: boolean;
  status: 'active' | 'cancelled' | 'billing_issue' | 'expired' | null;
  provider: 'revenuecat' | 'manual' | null;
  product_id: string | null;
  expires_at: string | null;
  will_renew: boolean;
}

/* ---- კალენდარის პლანერი ---- */

export type PlanIntensity = 'light' | 'moderate' | 'intense';
export type PlanLocation = 'home' | 'yard' | 'gym';
export type PlanSplit = 'full' | 'upper' | 'lower' | 'push' | 'pull' | 'legs';

export interface PlanScheduleItem {
  /** ISO: 1 = ორშაბათი … 7 = კვირა */
  weekday: number;
  location: PlanLocation;
}

export interface PlanDay {
  id: number;
  date: string;
  week_no: number;
  weekday: number;
  type: 'workout' | 'rest';
  split: PlanSplit | null;
  location: PlanLocation | null;
  focus: Zone[];
  est_minutes: number | null;
  is_deload: boolean;
  completed_at: string | null;
  exercises: PlannedExercise[];
}

export interface TrainingPlan {
  id: number;
  intensity: PlanIntensity;
  level: number;
  schedule: PlanScheduleItem[];
  weeks: number;
  starts_on: string;
  ends_on: string;
  today: string;
  stats: { workouts: number; completed: number };
  days: PlanDay[];
}

export interface PlanCreateRequest {
  schedule: PlanScheduleItem[];
  intensity: PlanIntensity;
  weeks: number;
  starts_on?: string;
}
