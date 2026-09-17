import * as SQLite from 'expo-sqlite';
import { drizzle } from 'drizzle-orm/expo-sqlite';
import * as schema from './schema';

const sqlite = SQLite.openDatabaseSync('kalisteni.db', { enableChangeListener: true });

export const db = drizzle(sqlite, { schema });

/**
 * სქემას პირდაპირ SQL-ით ვქმნით და არა drizzle-kit-ის მიგრაციებით.
 *
 * მიზეზი: ლოკალური ბაზა მხოლოდ რიგი და ქეშია — მისი დაკარგვა
 * მონაცემს არ კარგავს (სერვერი ჭეშმარიტების წყაროა). სანაცვლოდ
 * ვიღებთ ერთ ფაილს დამატებითი build-ნაბიჯების გარეშე. Drizzle
 * აქ ტიპიზებული queries-ისთვისაა, არა მიგრაციებისთვის.
 */
export function initDatabase() {
  sqlite.execSync(`
    PRAGMA journal_mode = WAL;
    PRAGMA foreign_keys = ON;

    CREATE TABLE IF NOT EXISTS local_sessions (
      client_uuid TEXT PRIMARY KEY NOT NULL,
      program_day_id INTEGER,
      plan_day_id INTEGER,
      spot_checkin_id INTEGER,
      started_at TEXT NOT NULL,
      completed_at TEXT,
      duration_ms INTEGER NOT NULL DEFAULT 0,
      source TEXT NOT NULL,
      device_clock_offset_ms INTEGER NOT NULL DEFAULT 0,
      status TEXT NOT NULL DEFAULT 'pending',
      estimated_xp INTEGER NOT NULL DEFAULT 0,
      server_xp INTEGER,
      server_session_id INTEGER,
      verification_tier INTEGER,
      sync_attempts INTEGER NOT NULL DEFAULT 0,
      last_error TEXT,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_local_sessions_status ON local_sessions (status);

    CREATE TABLE IF NOT EXISTS local_sets (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      client_uuid TEXT NOT NULL,
      exercise_id INTEGER NOT NULL,
      set_no INTEGER NOT NULL,
      reps INTEGER,
      seconds INTEGER,
      added_weight_kg REAL NOT NULL DEFAULT 0,
      tempo TEXT NOT NULL DEFAULT 'normal',
      rest_after_ms INTEGER,
      started_at TEXT,
      completed_at TEXT
    );
    CREATE INDEX IF NOT EXISTS idx_local_sets_session ON local_sets (client_uuid);

    CREATE TABLE IF NOT EXISTS cached_exercises (
      id INTEGER PRIMARY KEY NOT NULL,
      locale TEXT NOT NULL,
      payload TEXT NOT NULL,
      updated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS cached_program_days (
      id INTEGER PRIMARY KEY NOT NULL,
      locale TEXT NOT NULL,
      payload TEXT NOT NULL,
      cached_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS key_value (
      key TEXT PRIMARY KEY NOT NULL,
      value TEXT NOT NULL
    );
  `);

  // v1.1 — პლანის დღე. CREATE IF NOT EXISTS ძველ ინსტალაციაზე სვეტს
  // არ დაამატებს, რიგში მყოფი სესიები კი დაკარგვის ღირსი არ არის
  const columns = sqlite.getAllSync<{ name: string }>('PRAGMA table_info(local_sessions)');
  if (!columns.some((column) => column.name === 'plan_day_id')) {
    sqlite.execSync('ALTER TABLE local_sessions ADD COLUMN plan_day_id INTEGER');
  }
}

export { schema };
