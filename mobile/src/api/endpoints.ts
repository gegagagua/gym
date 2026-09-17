import { api } from './client';
import type {
  ConfigResponse,
  Exercise,
  LeagueResponse,
  MeResponse,
  NearbySpot,
  Program,
  ProgramToday,
  SessionSyncRequest,
  SessionSyncResponse,
  SpotDetail,
  Stats,
  SubscriptionState,
  TrainingPlan,
  PlanCreateRequest,
} from './types';

export const auth = {
  guest: (device_uuid: string) =>
    api<{ token: string; is_new: boolean; user: MeResponse['data'] }>('/auth/guest', {
      method: 'POST',
      auth: false,
      body: { device_uuid },
    }),

  requestOtp: (phone: string) =>
    api<{ expires_in: number; debug_code?: string }>('/auth/otp/request', {
      method: 'POST',
      auth: false,
      body: { phone },
    }),

  verifyOtp: (phone: string, code: string, device_uuid?: string) =>
    api<{ token: string; is_new: boolean; user: MeResponse['data'] }>('/auth/otp/verify', {
      method: 'POST',
      auth: false,
      body: { phone, code, device_uuid },
    }),

  social: (provider: 'google' | 'apple', provider_id: string, email?: string, display_name?: string) =>
    api<{ token: string; is_new: boolean; user: MeResponse['data'] }>('/auth/social', {
      method: 'POST',
      auth: false,
      body: { provider, provider_id, email, display_name },
    }),

  upgrade: (phone: string, code: string) => api('/auth/upgrade', { method: 'POST', body: { phone, code } }),
  logout: () => api('/auth/logout', { method: 'DELETE' }),
  deleteAccount: () => api<{ purge_after: string }>('/account', { method: 'DELETE' }),
};

export const me = {
  get: () => api<MeResponse>('/me'),
  update: (body: Record<string, unknown>) => api<MeResponse>('/me', { method: 'PATCH', body }),
  updateProfile: (body: Record<string, unknown>) => api<MeResponse>('/me/profile', { method: 'PATCH', body }),
  levelTest: (body: { pushup: number; pullup: number; plank_sec: number }) =>
    api<{ level: number; levels: Record<string, number>; recommended_program: { id: number; slug: string } | null }>(
      '/me/level-test',
      { method: 'POST', body },
    ),
  stats: (period: 'week' | 'month' | 'all' = 'week') => api<Stats>('/me/stats', { query: { period } }),
  records: () => api<{ data: any[] }>('/me/records'),
  checkin: () => api<{ checkin: any | null }>('/me/checkin'),
};

export const content = {
  config: () => api<ConfigResponse>('/config', { auth: false }),
  cities: () => api<{ data: any[] }>('/cities', { auth: false }),
  attributions: () => api<{ data: any[] }>('/attributions', { auth: false }),
  manifest: (since?: string) => api('/content/manifest', { auth: false, query: { since } }),
};

export const exercises = {
  list: (query: Record<string, any> = {}) => api<{ data: Exercise[] }>('/exercises', { auth: false, query }),
  get: (id: number) => api<{ data: Exercise }>(`/exercises/${id}`, { auth: false }),
};

export const programs = {
  list: (query: Record<string, any> = {}) => api<{ data: Program[] }>('/programs', { auth: false, query }),
  get: (id: number) => api<{ data: Program }>(`/programs/${id}`, { auth: false }),
  enroll: (id: number) => api(`/programs/${id}/enroll`, { method: 'POST' }),
  current: () => api<ProgramToday>('/me/program'),
  advance: () => api('/me/program/advance', { method: 'POST' }),
};

export const sessions = {
  sync: (body: SessionSyncRequest) => api<SessionSyncResponse>('/sessions/sync', { method: 'POST', body }),
  list: (limit = 30) => api<{ data: any[] }>('/sessions', { query: { limit } }),
};

export const spots = {
  nearby: (lat: number, lng: number, radius = 2000, equipment: string[] = []) =>
    api<{ data: NearbySpot[] }>('/spots/nearby', { auth: false, query: { lat, lng, radius, equipment } }),
  get: (id: number) => api<{ data: SpotDetail }>(`/spots/${id}`, { auth: false }),
  create: (form: FormData) => api('/spots', { method: 'POST', body: form }),
  checkIn: (id: number, lat: number, lng: number, accuracy_m?: number) =>
    api<{ checkin_id: number; expires_at: string; distance_m: number; xp_multiplier: number }>(
      `/spots/${id}/checkin`,
      { method: 'POST', body: { lat, lng, accuracy_m } },
    ),
  rate: (id: number, condition_rating: number) =>
    api(`/spots/${id}/rating`, { method: 'POST', body: { condition_rating } }),
};

export const league = {
  current: () => api<LeagueResponse>('/league/current'),
  friends: () => api<{ data: any[] }>('/leaderboard/friends'),
  spot: (id: number, period: 'week' | 'month' = 'week') =>
    api<{ spot: { id: number; name: string }; data: any[] }>(`/leaderboard/spot/${id}`, { query: { period } }),
  city: (id: number) => api<{ data: any[] }>(`/leaderboard/city/${id}`),
  records: (exerciseId: number) => api<{ data: any[] }>(`/leaderboard/records/${exerciseId}`),
};

export const trainers = {
  list: (query: Record<string, any> = {}) => api<{ data: any[] }>('/trainers', { auth: false, query }),
};

export const share = {
  create: (type: 'session' | 'streak' | 'league' | 'record', session_id?: number) =>
    api<{ card: { id: number; status: string } }>('/share/card', { method: 'POST', body: { type, session_id } }),
  get: (id: number) => api<{ card: { id: number; status: string; url: string | null } }>(`/share/card/${id}`),
};

export const billing = {
  get: () => api<SubscriptionState>('/me/subscription'),
  /** ყიდვის/აღდგენის შემდეგ — სერვერი RevenueCat-ს პირდაპირ ეკითხება */
  sync: () => api<SubscriptionState>('/me/subscription/sync', { method: 'POST' }),
};

export const plan = {
  /** 402 `premium_required` → paywall */
  get: () => api<{ plan: TrainingPlan | null }>('/me/plan'),
  create: (body: PlanCreateRequest) => api<{ plan: TrainingPlan }>('/me/plan', { method: 'POST', body }),
  cancel: () => api<{ plan: null }>('/me/plan', { method: 'DELETE' }),
};
