import { sqliteTable, text, integer, real, index } from 'drizzle-orm/sqlite-core';

/**
 * ლოკალური ბაზა — ვარჯიშის პლეიერი სრულად ოფლაინ მუშაობს.
 *
 * ორი როლი აქვს:
 *  1. `localSessions` / `localSets` — ჩაწერის რიგი, სანამ სერვერი მიუწვდომელია
 *  2. `cachedExercises` / `cachedProgramDays` — კონტენტის ქეში,
 *     რომ ბიბლიოთეკა და დღევანდელი ვარჯიში ქსელის გარეშეც გაიხსნას
 */

export const localSessions = sqliteTable(
  'local_sessions',
  {
    clientUuid: text('client_uuid').primaryKey(),
    programDayId: integer('program_day_id'),
    spotCheckinId: integer('spot_checkin_id'),
    startedAt: text('started_at').notNull(),
    completedAt: text('completed_at'),
    durationMs: integer('duration_ms').notNull().default(0),
    source: text('source', { enum: ['program', 'freestyle', 'test'] }).notNull(),
    deviceClockOffsetMs: integer('device_clock_offset_ms').notNull().default(0),

    /** pending → synced | rejected. სესია immutable-ია, კონფლიქტი შეუძლებელია. */
    status: text('status', { enum: ['pending', 'syncing', 'synced', 'rejected'] })
      .notNull()
      .default('pending'),

    /** კლიენტის ესტიმაცია — UI-ში ვიზუალურად აღნიშნულია როგორც სავარაუდო */
    estimatedXp: integer('estimated_xp').notNull().default(0),
    /** სერვერის ნამდვილი მნიშვნელობა სინქის შემდეგ */
    serverXp: integer('server_xp'),
    serverSessionId: integer('server_session_id'),
    verificationTier: integer('verification_tier'),
    syncAttempts: integer('sync_attempts').notNull().default(0),
    lastError: text('last_error'),
    createdAt: text('created_at').notNull(),
  },
  (table) => [index('idx_local_sessions_status').on(table.status)],
);

export const localSets = sqliteTable(
  'local_sets',
  {
    id: integer('id').primaryKey({ autoIncrement: true }),
    clientUuid: text('client_uuid').notNull(),
    exerciseId: integer('exercise_id').notNull(),
    setNo: integer('set_no').notNull(),
    reps: integer('reps'),
    seconds: integer('seconds'),
    addedWeightKg: real('added_weight_kg').notNull().default(0),
    tempo: text('tempo', { enum: ['normal', 'slow'] }).notNull().default('normal'),
    restAfterMs: integer('rest_after_ms'),
    startedAt: text('started_at'),
    completedAt: text('completed_at'),
  },
  (table) => [index('idx_local_sets_session').on(table.clientUuid)],
);

export const cachedExercises = sqliteTable('cached_exercises', {
  id: integer('id').primaryKey(),
  locale: text('locale').notNull(),
  payload: text('payload').notNull(), // სრული JSON — ბიბლიოთეკა ოფლაინშიც იხსნება
  updatedAt: text('updated_at'),
});

export const cachedProgramDays = sqliteTable('cached_program_days', {
  id: integer('id').primaryKey(),
  locale: text('locale').notNull(),
  payload: text('payload').notNull(),
  cachedAt: text('cached_at').notNull(),
});

export const keyValue = sqliteTable('key_value', {
  key: text('key').primaryKey(),
  value: text('value').notNull(),
});

export type LocalSession = typeof localSessions.$inferSelect;
export type LocalSet = typeof localSets.$inferSelect;
export type NewLocalSession = typeof localSessions.$inferInsert;
export type NewLocalSet = typeof localSets.$inferInsert;
