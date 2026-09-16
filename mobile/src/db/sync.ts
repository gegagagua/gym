import { and, asc, eq, inArray, lt } from 'drizzle-orm';
import { db } from './index';
import { localSessions, localSets, type LocalSession } from './schema';
import { sessions as sessionsApi } from '@/api/endpoints';
import { ApiError } from '@/api/client';
import type { SessionSyncResult, SyncSessionPayload } from '@/api/types';

const BATCH_MAX = 20;
const MAX_ATTEMPTS = 8;

export async function pendingCount(): Promise<number> {
  const rows = await db.select({ id: localSessions.clientUuid }).from(localSessions)
    .where(inArray(localSessions.status, ['pending', 'syncing']));
  return rows.length;
}

export async function listLocalSessions(limit = 30): Promise<LocalSession[]> {
  return db.select().from(localSessions).orderBy(asc(localSessions.startedAt)).limit(limit);
}

/**
 * ოფლაინ რიგის გაგზავნა (სპეც. 12.3).
 *
 * კლიენტი ნედლ მონაცემებს აგზავნის; XP მხოლოდ სერვერზე ითვლება.
 * განსხვავება ჩვენს ესტიმაციასა და სერვერის პასუხს შორის ჩუმად
 * სწორდება — ბანერი აქ მომხმარებელს მხოლოდ დააბნევდა.
 */
export async function flushQueue(): Promise<{ synced: number; failed: number } | null> {
  const batch = await db
    .select()
    .from(localSessions)
    .where(and(eq(localSessions.status, 'pending'), lt(localSessions.syncAttempts, MAX_ATTEMPTS)))
    .orderBy(asc(localSessions.startedAt))
    .limit(BATCH_MAX);

  if (batch.length === 0) return null;

  const uuids = batch.map((s) => s.clientUuid);
  await db.update(localSessions).set({ status: 'syncing' }).where(inArray(localSessions.clientUuid, uuids));

  const payload: SyncSessionPayload[] = [];

  for (const session of batch) {
    const sets = await db.select().from(localSets).where(eq(localSets.clientUuid, session.clientUuid));

    payload.push({
      client_uuid: session.clientUuid,
      program_day_id: session.programDayId,
      spot_checkin_id: session.spotCheckinId,
      started_at: session.startedAt,
      completed_at: session.completedAt,
      duration_ms: session.durationMs,
      source: session.source,
      device_clock_offset_ms: session.deviceClockOffsetMs,
      sets: sets.map((s) => ({
        exercise_id: s.exerciseId,
        set_no: s.setNo,
        reps: s.reps,
        seconds: s.seconds,
        added_weight_kg: s.addedWeightKg,
        tempo: s.tempo,
        rest_after_ms: s.restAfterMs,
        started_at: s.startedAt,
        completed_at: s.completedAt,
      })),
    });
  }

  try {
    const response = await sessionsApi.sync({ sessions: payload });
    await applyResults(response.results);

    return { synced: response.results.length, failed: 0 };
  } catch (error) {
    const message = error instanceof ApiError ? `${error.status}: ${error.message}` : String(error);

    // ვალიდაციის შეცდომა (422) მეორედ გაგზავნითაც არ გასწორდება —
    // ასეთი სესია მკვდარ მარყუჟში არ უნდა დარჩეს.
    const permanent = error instanceof ApiError && error.status === 422;

    for (const session of batch) {
      await db
        .update(localSessions)
        .set({
          status: permanent ? 'rejected' : 'pending',
          syncAttempts: session.syncAttempts + 1,
          lastError: message,
        })
        .where(eq(localSessions.clientUuid, session.clientUuid));
    }

    return { synced: 0, failed: batch.length };
  }
}

async function applyResults(results: SessionSyncResult[]) {
  for (const result of results) {
    await db
      .update(localSessions)
      .set({
        status: result.status === 'rejected' ? 'rejected' : 'synced',
        serverXp: result.xp_awarded,
        serverSessionId: result.session_id,
        verificationTier: result.verification_tier,
        lastError: null,
      })
      .where(eq(localSessions.clientUuid, result.client_uuid));
  }
}

/** დასინქრონებული სესიების გასუფთავება — ისტორია სერვერზეა */
export async function pruneSynced(keepDays = 14) {
  const cutoff = new Date(Date.now() - keepDays * 86_400_000).toISOString();

  const stale = await db
    .select({ uuid: localSessions.clientUuid })
    .from(localSessions)
    .where(and(eq(localSessions.status, 'synced'), lt(localSessions.startedAt, cutoff)));

  if (stale.length === 0) return 0;

  const uuids = stale.map((s) => s.uuid);
  await db.delete(localSets).where(inArray(localSets.clientUuid, uuids));
  await db.delete(localSessions).where(inArray(localSessions.clientUuid, uuids));

  return uuids.length;
}
