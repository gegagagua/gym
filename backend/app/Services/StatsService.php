<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ჯამური XP არასდროს ინახება ცვალებად ველად — ის ყოველთვის
 * ledger-იდან აგრეგირდება და Redis-ში ქეშირდება (სპეც. 10.3).
 */
class StatsService
{
    private const TTL = 300;

    public function totalXp(User $user): int
    {
        return Cache::remember("xp:total:{$user->id}", self::TTL, fn () => (int) DB::table('xp_ledger')
            ->where('user_id', $user->id)
            ->sum('xp'));
    }

    /** v2-ის ფასდაკლების ვალუტისთვის: დარიცხული − დახარჯული − ვადაგასული */
    public function spendableXp(User $user): int
    {
        $months = config('kalisteni.xp.expiry_months');

        $earned = (int) DB::table('xp_ledger')
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', now()->subMonths($months))
            ->sum('xp');

        $spent = (int) DB::table('xp_spent')->where('user_id', $user->id)->sum('xp');

        return max(0, $earned - $spent);
    }

    public function xpToday(User $user): int
    {
        $tz = $user->timezone ?: config('kalisteni.league.timezone');

        return (int) DB::table('xp_ledger')
            ->where('user_id', $user->id)
            ->whereBetween('occurred_at', [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc()])
            ->sum('xp');
    }

    public function summary(User $user, string $period = 'week'): array
    {
        $from = match ($period) {
            'month' => now()->subMonth(),
            'all' => now()->subYears(10),
            default => now()->subWeek(),
        };

        $sessions = DB::table('workout_sessions')
            ->where('user_id', $user->id)
            ->where('status', '!=', 'rejected')
            ->where('started_at', '>=', $from)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(duration_ms),0) as duration_ms, COALESCE(SUM(total_reps),0) as reps, COALESCE(SUM(total_seconds),0) as seconds')
            ->first();

        return [
            'period' => $period,
            'xp' => (int) DB::table('xp_ledger')->where('user_id', $user->id)->where('occurred_at', '>=', $from)->sum('xp'),
            'xp_total' => $this->totalXp($user),
            'xp_today' => $this->xpToday($user),
            'daily_cap' => config('kalisteni.xp.daily_cap'),
            'sessions' => (int) $sessions->count,
            'minutes' => (int) round($sessions->duration_ms / 60_000),
            'reps' => (int) $sessions->reps,
            'hold_seconds' => (int) $sessions->seconds,
            'streak_days' => (int) ($user->streak?->current_days ?? 0),
            'longest_streak' => (int) ($user->streak?->longest_days ?? 0),
            'level' => (int) ($user->profile?->level ?? 1),
            'muscle_load' => $this->muscleLoad($user, $from),
        ];
    }

    /**
     * კუნთების რუკა — რომელი ჯგუფი რამდენად დაიტვირთა.
     * წყარო: სესიების სეტები × exercises.primary_muscles.
     */
    public function muscleLoad(User $user, $from): array
    {
        $rows = DB::table('session_sets as ss')
            ->join('workout_sessions as ws', 'ws.id', '=', 'ss.session_id')
            ->join('exercises as e', 'e.id', '=', 'ss.exercise_id')
            ->where('ws.user_id', $user->id)
            ->where('ws.status', '!=', 'rejected')
            ->where('ws.started_at', '>=', $from)
            ->selectRaw('e.primary_muscles, e.difficulty_coef, COALESCE(ss.reps, ss.seconds / 5.0, 0) as volume')
            ->get();

        $load = [];
        foreach ($rows as $row) {
            $muscles = json_decode($row->primary_muscles, true) ?: [];
            $weight = (float) $row->difficulty_coef * (float) $row->volume;
            foreach ($muscles as $muscle) {
                $load[$muscle] = ($load[$muscle] ?? 0) + $weight;
            }
        }

        if (! $load) {
            return [];
        }

        // 0..1 ნორმალიზაცია — UI-ს აბსოლუტური რიცხვები არ სჭირდება
        $max = max($load);
        arsort($load);

        return array_map(fn ($v) => round($v / $max, 3), $load);
    }

    public function forget(User $user): void
    {
        Cache::forget("xp:total:{$user->id}");
    }
}
