<?php

namespace App\Services;

use App\Enums\Flag;
use App\Enums\VerificationTier;
use App\Models\SpotCheckin;
use App\Models\WorkoutSession;
use App\Models\XpLedger;
use Illuminate\Support\Facades\DB;

/**
 * ევრისტიკები სპეც. 8.1-იდან. მიზანი არ არის ჩიტერის დაბლოკვა —
 * მიზანია ლიდერბორდის სისუფთავე. ეჭვქვეშ მყოფი სესია ინახება,
 * პირად სტატისტიკაში ითვლება, ლიგაში კი არა.
 */
class AntiCheatService
{
    /** @return array<int, string> აღმოჩენილი ფლაგები */
    public function inspect(WorkoutSession $session): array
    {
        $cfg = config('kalisteni.anticheat');
        $session->loadMissing('sets');
        $flags = [];

        if ($this->hasImpossibleRate($session, (float) $cfg['min_seconds_per_rep'])) {
            $flags[] = Flag::ImpossibleRate->value;
        }

        if ($this->hasNoRest($session, (int) $cfg['min_rest_seconds'], (int) $cfg['no_rest_streak'])) {
            $flags[] = Flag::NoRest->value;
        }

        if ($this->hasRoundNumbers($session)) {
            $flags[] = Flag::RoundNumbers->value;
        }

        if (abs((int) $session->device_clock_offset_ms) > $cfg['clock_skew_minutes'] * 60_000) {
            $flags[] = Flag::ClockSkew->value;
        }

        if ($this->hasGpsJump($session, (int) $cfg['gps_jump_minutes'], (float) $cfg['gps_jump_km'])) {
            $flags[] = Flag::GpsJump->value;
        }

        if ($this->hasVelocitySpike($session)) {
            $flags[] = Flag::Velocity->value;
        }

        return array_values(array_unique($flags));
    }

    /** 2+ ფლაგი ერთ სესიაზე → flagged */
    public function shouldFlag(array $flags): bool
    {
        return count($flags) >= config('kalisteni.anticheat.flags_to_flag_session');
    }

    /**
     * საბოლოო tier. საათის მანიპულაცია ავტომატურად ჩამოაგდებს T0-მდე —
     * ტაიმსტემპებს ვეღარ ვენდობით (სპეც. 12.3).
     */
    public function resolveTier(WorkoutSession $session, array $flags, ?SpotCheckin $checkin): VerificationTier
    {
        if (in_array(Flag::ClockSkew->value, $flags, true)) {
            return VerificationTier::Manual;
        }

        if ($checkin && $checkin->is_valid && $checkin->spot?->status === 'verified') {
            return VerificationTier::Checkin;
        }

        return $session->completed_at && $session->sets->isNotEmpty()
            ? VerificationTier::InApp
            : VerificationTier::Manual;
    }

    private function hasImpossibleRate(WorkoutSession $session, float $minPerRep): bool
    {
        foreach ($session->sets as $set) {
            if (! $set->reps || ! $set->started_at || ! $set->completed_at) {
                continue;
            }
            $seconds = $set->completed_at->getTimestamp() - $set->started_at->getTimestamp();
            if ($seconds > 0 && ($seconds / $set->reps) < $minPerRep) {
                return true;
            }
        }

        return false;
    }

    private function hasNoRest(WorkoutSession $session, int $minRest, int $streak): bool
    {
        $run = 0;
        foreach ($session->sets as $set) {
            if ($set->rest_after_ms !== null && $set->rest_after_ms < $minRest * 1000) {
                if (++$run >= $streak) {
                    return true;
                }
            } else {
                $run = 0;
            }
        }

        return false;
    }

    /** ყველა სეტი ზუსტად 20/50/100 ვარიაციის გარეშე — ხელით შევსების ხელწერა */
    private function hasRoundNumbers(WorkoutSession $session): bool
    {
        $values = $session->sets
            ->map(fn ($s) => (int) $s->rawValue())
            ->filter(fn ($v) => $v > 0)
            ->values();

        if ($values->count() < 3) {
            return false;
        }

        $allRound = $values->every(fn ($v) => $v % 10 === 0 && $v >= 20);

        return $allRound && $values->unique()->count() === 1;
    }

    /** 2 მოედანზე check-in < 10 წთ ინტერვალით და > 3 კმ დაშორებით */
    private function hasGpsJump(WorkoutSession $session, int $minutes, float $km): bool
    {
        if (! $session->spot_checkin_id || DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $checkin = SpotCheckin::find($session->spot_checkin_id);
        if (! $checkin) {
            return false;
        }

        $neighbours = SpotCheckin::query()
            ->where('user_id', $checkin->user_id)
            ->where('id', '!=', $checkin->id)
            ->whereBetween('checked_in_at', [
                $checkin->checked_in_at->copy()->subMinutes($minutes),
                $checkin->checked_in_at->copy()->addMinutes($minutes),
            ])
            ->pluck('spot_id');

        if ($neighbours->isEmpty()) {
            return false;
        }

        $maxDistance = DB::table('spots as a')
            ->join('spots as b', fn ($join) => $join->whereRaw('true'))
            ->where('a.id', $checkin->spot_id)
            ->whereIn('b.id', $neighbours)
            ->selectRaw('MAX(ST_Distance(a.location, b.location)) as d')
            ->value('d');

        return (float) $maxDistance > $km * 1000;
    }

    /** დღიური XP > 95-ე პროცენტილზე 3 დღე ზედიზედ */
    private function hasVelocitySpike(WorkoutSession $session): bool
    {
        $tz = $session->user?->timezone ?: config('kalisteni.league.timezone');
        $since = now($tz)->subDays(3)->startOfDay()->utc();

        $perDay = DB::table('xp_ledger')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('user_id, DATE(occurred_at) as d, SUM(xp) as daily')
            ->groupBy('user_id', 'd');

        $p95 = DB::query()
            ->fromSub($perDay, 'per_day')
            ->selectRaw('percentile_cont(0.95) WITHIN GROUP (ORDER BY daily) as p95')
            ->value('p95');

        if (! $p95 || $p95 <= 0) {
            return false;
        }

        $daysAbove = XpLedger::where('user_id', $session->user_id)
            ->where('occurred_at', '>=', $since)
            ->selectRaw('DATE(occurred_at) as d, SUM(xp) as daily')
            ->groupBy('d')
            ->having(DB::raw('SUM(xp)'), '>', $p95)
            ->get()
            ->count();

        return $daysAbove >= 3;
    }
}
