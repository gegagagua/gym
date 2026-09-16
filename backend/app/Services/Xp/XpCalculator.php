<?php

namespace App\Services\Xp;

use App\Enums\VerificationTier;
use App\Models\Exercise;
use App\Models\SessionSet;
use App\Models\WorkoutSession;
use App\Models\XpLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * XP მხოლოდ აქ ითვლება — სერვერზე. კლიენტიდან მოსული `xp` მნიშვნელობა
 * ყოველთვის იგნორირდება (სპეც. 8.2).
 *
 * ფორმულა (სპეც. 6.1):
 *   reps:  base = k × reps
 *   hold:  base = k × (seconds / 5)
 *   final = base × weight × tempo × streak × checkin × diminishing
 */
class XpCalculator
{
    public function __construct(private readonly SkillUnlockService $unlocks) {}

    public function calculate(WorkoutSession $session): XpResult
    {
        $cfg = config('kalisteni.xp');
        $result = new XpResult;

        $session->loadMissing(['sets.exercise', 'user.profile', 'user.streak']);

        $user = $session->user;
        $bodyweight = $user->profile?->effectiveWeightKg() ?? 75.0;
        $streakMod = $user->streak?->multiplier() ?? 1.0;
        $checkinMod = $session->spot_checkin_id ? $cfg['checkin_mod'] : 1.0;

        $day = $this->localDay($session);
        // დღიური ჭერი მოქმედებს ყველა სესიაზე ერთად, არა თითოზე ცალკე
        $remainingCap = max(0, $cfg['daily_cap'] - $this->workoutXpOnDay($user->id, $day, $session->id));
        $dailyReps = $this->repsByExerciseOnDay($user->id, $day, $session->id);

        $rows = [];
        $totalReps = 0;
        $totalSeconds = 0;

        foreach ($session->sets as $set) {
            /** @var Exercise $ex */
            $ex = $set->exercise;
            $k = (float) $ex->difficulty_coef;

            $base = $ex->isTimeBased()
                ? $k * (($set->seconds ?? 0) / $cfg['hold_seconds_per_unit'])
                : $k * ($set->reps ?? 0);

            if ($base <= 0) {
                continue;
            }

            $done = $dailyReps[$ex->id] ?? 0;

            $mods = [
                'weight' => $this->weightMod((float) $set->added_weight_kg, $bodyweight, $cfg['weight_mod_max']),
                'tempo' => $set->tempo === 'slow' ? $cfg['tempo_slow_mod'] : 1.0,
                'streak' => $streakMod,
                'checkin' => $checkinMod,
                'dim' => $done > $cfg['diminishing_reps'] ? $cfg['diminishing_mod'] : 1.0,
            ];

            $xp = (int) round($base * array_product($mods));

            // ჭერი სეტების დონეზე იჭრება, რომ ledger-ის ჯამი ყოველთვის
            // ტოლი იყოს დარიცხულის — წინააღმდეგ შემთხვევაში აუდიტი ირღვევა.
            if ($xp > $remainingCap) {
                $xp = $remainingCap;
                $result->cappedOut = true;
            }
            $remainingCap -= $xp;

            $rows[] = [
                'user_id' => $user->id,
                'session_id' => $session->id,
                'exercise_id' => $ex->id,
                'reason' => 'workout',
                'raw_value' => $set->rawValue(),
                'k_snapshot' => $k,          // ← კრიტიკული: კოეფიციენტის გადაკალიბრება არ ცვლის ისტორიას
                'multipliers' => json_encode($mods),
                'xp' => $xp,
                'league_xp' => 0,            // ქვემოთ, tier-ის ლიმიტის გათვალისწინებით
                'occurred_at' => $set->completed_at ?? $session->completed_at ?? $session->started_at,
                'created_at' => now(),
            ];

            $result->workoutXp += $xp;
            $dailyReps[$ex->id] = $done + (int) $set->rawValue();
            $totalReps += (int) ($set->reps ?? 0);
            $totalSeconds += (int) ($set->seconds ?? 0);

            if ($remainingCap <= 0) {
                $result->cappedOut = true;
            }
        }

        // ლიგის XP — T0 დღეში 300-ით შემოსაზღვრულია, flagged სესია საერთოდ არ ითვლება
        $leagueBudget = $this->leagueBudget($session, $user->id, $day);
        foreach ($rows as $i => $row) {
            $take = min($row['xp'], $leagueBudget);
            $rows[$i]['league_xp'] = $take;
            $leagueBudget -= $take;
            $result->leagueXp += $take;
        }

        DB::transaction(function () use ($rows, $session, $result, $totalReps, $totalSeconds) {
            if ($rows) {
                XpLedger::insert($rows);
            }

            // Skill unlock — ერთჯერადი ბონუსი, ჭერისგან დამოუკიდებელი.
            // ის არ ფარმდება (თითო სავარჯიშოზე სიცოცხლეში ერთხელ), ამიტომ
            // მისი ჩაჭრა მხოლოდ პროდუქტს აზიანებდა.
            $unlocked = $this->unlocks->award($session);
            foreach ($unlocked as $u) {
                $result->bonusXp += $u['bonus_xp'];
                $result->leagueXp += $u['bonus_xp'];
            }
            $result->unlocked = $unlocked;

            $session->forceFill([
                'total_xp' => $result->totalXp(),
                'total_reps' => $totalReps,
                'total_seconds' => $totalSeconds,
            ])->save();
        });

        return $result;
    }

    /** weight_mod = 1 + added/bodyweight, ჩაჭრილი [1.0, 2.0] დიაპაზონში */
    private function weightMod(float $added, float $bodyweight, float $max): float
    {
        if ($added <= 0 || $bodyweight <= 0) {
            return 1.0;
        }

        return min($max, 1 + ($added / $bodyweight));
    }

    /**
     * ლიგაში ჩასათვლელი ბიუჯეტი ამ სესიისთვის.
     * flagged სესია პირად სტატისტიკაში ითვლება, ლიგაში — არა (სპეც. 8.1).
     */
    private function leagueBudget(WorkoutSession $session, int $userId, Carbon $day): int
    {
        if ($session->status !== 'completed') {
            return 0;
        }

        $tier = $session->verification_tier instanceof VerificationTier
            ? $session->verification_tier
            : VerificationTier::from((int) $session->verification_tier);

        if (! $tier->countsForLeague()) {
            $cap = config('kalisteni.xp.daily_cap_t0_league');

            return max(0, $cap - $this->leagueXpOnDay($userId, $day, $session->id));
        }

        return PHP_INT_MAX;
    }

    /** მომხმარებლის ლოკალური დღე — ჭერი კალენდარულ დღეზეა მიბმული, არა UTC-ზე */
    private function localDay(WorkoutSession $session): Carbon
    {
        $tz = $session->user->timezone ?: config('kalisteni.league.timezone');

        return ($session->started_at ?? now())->copy()->setTimezone($tz)->startOfDay();
    }

    private function dayBounds(Carbon $day, string $tz): array
    {
        return [
            $day->copy()->setTimezone($tz)->startOfDay()->utc(),
            $day->copy()->setTimezone($tz)->endOfDay()->utc(),
        ];
    }

    private function workoutXpOnDay(int $userId, Carbon $day, int $exceptSessionId): int
    {
        [$from, $to] = $this->dayBounds($day, $day->timezone->getName());

        return (int) XpLedger::where('user_id', $userId)
            ->where('reason', 'workout')
            ->where('session_id', '!=', $exceptSessionId)
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('xp');
    }

    private function leagueXpOnDay(int $userId, Carbon $day, int $exceptSessionId): int
    {
        [$from, $to] = $this->dayBounds($day, $day->timezone->getName());

        return (int) XpLedger::where('user_id', $userId)
            ->where('session_id', '!=', $exceptSessionId)
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('league_xp');
    }

    /**
     * იმავე დღეს იმავე სავარჯიშოზე უკვე შესრულებული მოცულობა —
     * diminishing returns-ის საფუძველი. სხვა სესიებსაც ითვლის,
     * თორემ 100-იანი ზღვარი სესიების დაქუცმაცებით შემოივლებოდა.
     */
    private function repsByExerciseOnDay(int $userId, Carbon $day, int $exceptSessionId): array
    {
        [$from, $to] = $this->dayBounds($day, $day->timezone->getName());

        return SessionSet::query()
            ->join('workout_sessions as ws', 'ws.id', '=', 'session_sets.session_id')
            ->where('ws.user_id', $userId)
            ->where('ws.id', '!=', $exceptSessionId)
            ->whereBetween('ws.started_at', [$from, $to])
            ->groupBy('session_sets.exercise_id')
            ->selectRaw('session_sets.exercise_id, SUM(COALESCE(session_sets.reps, session_sets.seconds, 0)) as total')
            ->pluck('total', 'exercise_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
