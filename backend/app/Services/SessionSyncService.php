<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\SpotCheckin;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\Xp\XpCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ოფლაინ სინქის ბირთვი (სპეც. 11.3, 12.3).
 *
 * იდემპოტენტურობა `client_uuid`-ზეა: განმეორებითი გაგზავნა დუბლიკატს
 * არ ქმნის და უბრუნებს იმავე შედეგს. სესიები immutable არიან —
 * კონფლიქტი პრინციპულად შეუძლებელია.
 */
class SessionSyncService
{
    public function __construct(
        private readonly XpCalculator $xp,
        private readonly AntiCheatService $antiCheat,
        private readonly PersonalRecordService $records,
        private readonly StreakService $streaks,
        private readonly LeagueService $leagues,
    ) {}

    /**
     * @param  array<int, array>  $payload  კლიენტის სესიების პარტია
     * @return array<int, array>
     */
    public function sync(User $user, array $payload): array
    {
        $results = [];

        foreach ($payload as $raw) {
            $results[] = $this->syncOne($user, $raw);
        }

        return $results;
    }

    /**
     * ერთი სესია სრულად ერთ ტრანზაქციაშია: ან ჩაჯდა სესია + სეტები +
     * ledger + რეკორდები, ან არაფერი. შუაზე გაწყვეტილი დამუშავება
     * `client_uuid`-ს „დაკავებულად" დატოვებდა და სესია სამუდამოდ
     * 0 XP-ით დარჩებოდა — თავიდან გაგზავნა მას ვეღარ გაასწორებდა.
     */
    private function syncOne(User $user, array $raw): array
    {
        $clientUuid = $raw['client_uuid'];

        // იდემპოტენტურობა — უკვე დამუშავებული სესია იმავე პასუხს აბრუნებს
        if ($existing = WorkoutSession::where('client_uuid', $clientUuid)->first()) {
            return $this->describe($existing, [], true);
        }

        $checkin = $this->resolveCheckin($user, $raw['spot_checkin_id'] ?? null);
        $startedAt = Carbon::parse($raw['started_at']);
        $completedAt = isset($raw['completed_at']) ? Carbon::parse($raw['completed_at']) : null;

        return DB::transaction(function () use ($user, $raw, $clientUuid, $checkin, $startedAt, $completedAt) {
            $session = WorkoutSession::create([
                'user_id' => $user->id,
                'program_day_id' => $raw['program_day_id'] ?? null,
                'spot_checkin_id' => $checkin?->id,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'duration_ms' => $this->duration($raw, $startedAt, $completedAt),
                'source' => $raw['source'] ?? 'freestyle',
                'device_clock_offset_ms' => (int) ($raw['device_clock_offset_ms'] ?? 0),
                'client_uuid' => $clientUuid,
                'external_sync_id' => $raw['external_sync_id'] ?? null,
                'status' => 'completed',
                'verification_tier' => 1,
            ]);

            $this->insertSets($session, $raw['sets'] ?? []);
            $session->load('sets.exercise');

            // მინიმალური სესია — 3 წთ და 2 სავარჯიშო (ცარიელი სესიების ფილტრი)
            if ($this->isTooThin($session)) {
                $session->update(['status' => 'rejected', 'flags' => ['FLAG_TOO_THIN']]);

                return $this->describe($session, ['FLAG_TOO_THIN']);
            }

            $flags = $this->antiCheat->inspect($session);
            $tier = $this->antiCheat->resolveTier($session, $flags, $checkin);

            $session->update([
                'flags' => $flags ?: null,
                'verification_tier' => $tier->value,
                'status' => $this->antiCheat->shouldFlag($flags) ? 'flagged' : 'completed',
            ]);

            $session->refresh()->load('sets.exercise');

            $xpResult = $this->xp->calculate($session);
            $newRecords = $this->records->sync($session);
            $this->streaks->register($session);
            $this->leagues->addXp($user, $xpResult->leagueXp);

            return $this->describe($session->refresh(), $flags, false, $xpResult->unlocked, $newRecords);
        });
    }

    private function insertSets(WorkoutSession $session, array $sets): void
    {
        if (! $sets) {
            return;
        }

        // ერთი მოთხოვნა exercise_id-ების გასავალიდირებლად — N+1-ის თავიდან ასაცილებლად
        $validIds = Exercise::whereIn('id', array_column($sets, 'exercise_id'))->pluck('id')->all();

        $rows = [];
        foreach ($sets as $i => $set) {
            if (! in_array($set['exercise_id'], $validIds)) {
                continue;
            }

            $rows[] = [
                'session_id' => $session->id,
                'exercise_id' => $set['exercise_id'],
                'set_no' => $set['set_no'] ?? $i + 1,
                'reps' => $set['reps'] ?? null,
                'seconds' => $set['seconds'] ?? null,
                'added_weight_kg' => $set['added_weight_kg'] ?? 0,
                'tempo' => $set['tempo'] ?? 'normal',
                'rest_after_ms' => $set['rest_after_ms'] ?? null,
                'started_at' => isset($set['started_at']) ? Carbon::parse($set['started_at']) : null,
                'completed_at' => isset($set['completed_at']) ? Carbon::parse($set['completed_at']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            DB::table('session_sets')->insert($rows);
        }
    }

    /** check-in მხოლოდ საკუთარი და ჯერ აქტიური შეიძლება იყოს */
    private function resolveCheckin(User $user, ?int $checkinId): ?SpotCheckin
    {
        if (! $checkinId) {
            return null;
        }

        return SpotCheckin::with('spot')
            ->where('id', $checkinId)
            ->where('user_id', $user->id)
            ->first();
    }

    private function duration(array $raw, Carbon $startedAt, ?Carbon $completedAt): int
    {
        $ms = isset($raw['duration_ms'])
            ? (int) $raw['duration_ms']
            : ($completedAt ? ($completedAt->getTimestamp() - $startedAt->getTimestamp()) * 1000 : 0);

        // ტაიმერის დატოვების დეტექცია — ჭერი 150 წუთი
        return min($ms, config('kalisteni.session.max_minutes') * 60_000);
    }

    private function isTooThin(WorkoutSession $session): bool
    {
        $cfg = config('kalisteni.session');

        $minutes = $session->duration_ms / 60_000;
        $exercises = $session->sets->pluck('exercise_id')->unique()->count();

        return $minutes < $cfg['min_minutes'] || $exercises < $cfg['min_exercises'];
    }

    private function describe(
        WorkoutSession $session,
        array $flags = [],
        bool $duplicate = false,
        array $unlocked = [],
        array $newRecords = [],
    ): array {
        return [
            'client_uuid' => $session->client_uuid,
            'session_id' => $session->id,
            'status' => $session->status,
            'verification_tier' => $session->verification_tier->value ?? (int) $session->verification_tier,
            'xp_awarded' => (int) $session->total_xp,
            'flags' => $flags ?: ($session->flags ?? []),
            'duplicate' => $duplicate,
            'new_records' => array_map(fn ($r) => [
                'exercise_id' => $r['exercise_id'],
                'metric' => $r['metric'],
                'value' => $r['value'],
            ], $newRecords),
            'unlocked' => $unlocked,
        ];
    }
}
