<?php

namespace App\Services\Xp;

use App\Models\WorkoutSession;
use App\Models\XpLedger;

/**
 * ერთჯერადი skill-ბონუსები (სპეც. 6.2).
 * პირველი muscle-up = 500 XP, პირველი planche (3 წმ) = 1500 XP და ა.შ.
 *
 * იდემპოტენტურია: ბონუსი გაიცემა მხოლოდ მაშინ, თუ ამ მომხმარებელს
 * ამ სავარჯიშოზე `skill_unlock` ჩანაწერი ჯერ არ აქვს.
 */
class SkillUnlockService
{
    /** @return array<int, array{code: string, exercise_id: int, bonus_xp: int}> */
    public function award(WorkoutSession $session): array
    {
        $session->loadMissing('sets.exercise');

        $candidates = $session->sets
            ->filter(fn ($set) => $set->exercise?->is_skill_unlock && $set->exercise->unlock_bonus_xp > 0)
            ->groupBy(fn ($set) => $set->exercise_id)
            ->map(fn ($sets) => [
                'exercise' => $sets->first()->exercise,
                'best' => (int) $sets->max(fn ($s) => $s->rawValue()),
            ])
            ->filter(fn ($c) => $c['best'] >= $c['exercise']->unlock_threshold);

        if ($candidates->isEmpty()) {
            return [];
        }

        $already = XpLedger::where('user_id', $session->user_id)
            ->where('reason', 'skill_unlock')
            ->whereIn('exercise_id', $candidates->keys())
            ->pluck('exercise_id')
            ->all();

        $awarded = [];

        foreach ($candidates as $exerciseId => $c) {
            if (in_array($exerciseId, $already, true)) {
                continue;
            }

            $bonus = (int) $c['exercise']->unlock_bonus_xp;

            XpLedger::create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'exercise_id' => $exerciseId,
                'reason' => 'skill_unlock',
                'raw_value' => $c['best'],
                'k_snapshot' => $c['exercise']->difficulty_coef,
                'multipliers' => null,
                'xp' => $bonus,
                'league_xp' => $bonus,
                'occurred_at' => $session->completed_at ?? $session->started_at,
                'created_at' => now(),
            ]);

            $awarded[] = [
                'code' => 'first_'.$c['exercise']->slug,
                'exercise_id' => (int) $exerciseId,
                'bonus_xp' => $bonus,
            ];
        }

        return $awarded;
    }
}
