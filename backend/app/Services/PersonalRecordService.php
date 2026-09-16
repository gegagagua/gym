<?php

namespace App\Services;

use App\Enums\VerificationTier;
use App\Models\PersonalRecord;
use App\Models\WorkoutSession;

class PersonalRecordService
{
    /**
     * სესიიდან ახალი PR-ების ამოღება.
     *
     * @return array<int, array{exercise_id: int, metric: string, value: float, previous: float|null}>
     */
    public function sync(WorkoutSession $session): array
    {
        $session->loadMissing('sets.exercise');

        // rejected სესია რეკორდს ვერ ქმნის; flagged — მხოლოდ პირად სტატისტიკაში
        if ($session->status === 'rejected') {
            return [];
        }

        $best = [];

        foreach ($session->sets as $set) {
            $ex = $set->exercise;
            if (! $ex) {
                continue;
            }

            $metric = $ex->isTimeBased() ? 'max_seconds' : 'max_reps';
            $value = (float) ($ex->isTimeBased() ? ($set->seconds ?? 0) : ($set->reps ?? 0));

            if ($value <= 0) {
                continue;
            }

            $key = $ex->id.'|'.$metric;
            if (! isset($best[$key]) || $value > $best[$key]['value']) {
                $best[$key] = ['exercise_id' => $ex->id, 'metric' => $metric, 'value' => $value];
            }

            // დამატებითი წონა ცალკე მეტრიკაა — dip +20 კგ ≠ dip max reps
            if ($set->added_weight_kg > 0) {
                $wKey = $ex->id.'|max_weight';
                $w = (float) $set->added_weight_kg;
                if (! isset($best[$wKey]) || $w > $best[$wKey]['value']) {
                    $best[$wKey] = ['exercise_id' => $ex->id, 'metric' => 'max_weight', 'value' => $w];
                }
            }
        }

        $tier = $session->verification_tier instanceof VerificationTier
            ? $session->verification_tier->value
            : (int) $session->verification_tier;

        $new = [];

        foreach ($best as $candidate) {
            $existing = PersonalRecord::where('user_id', $session->user_id)
                ->where('exercise_id', $candidate['exercise_id'])
                ->where('metric', $candidate['metric'])
                ->first();

            if ($existing && $existing->value >= $candidate['value']) {
                continue;
            }

            PersonalRecord::updateOrCreate(
                [
                    'user_id' => $session->user_id,
                    'exercise_id' => $candidate['exercise_id'],
                    'metric' => $candidate['metric'],
                ],
                [
                    'value' => $candidate['value'],
                    'session_id' => $session->id,
                    'achieved_at' => $session->completed_at ?? $session->started_at,
                    'verification_tier' => $tier,
                ],
            );

            $new[] = $candidate + ['previous' => $existing?->value];
        }

        return $new;
    }
}
