<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Program;
use App\Models\User;

/**
 * დონის ტესტი (სპეც. 5.1).
 *
 * საბოლოო დონე = round(avg(pushup, pullup, plank)), მაგრამ pull-up
 * ჭრის ზედა ზღვარს: final = min(avg, level_pullup + 1). ეს განზრახაა —
 * 50 push-up და 0 pull-up კალისთენიკაში მე-4 დონეს არ ნიშნავს.
 */
class LevelTestService
{
    private const PUSHUP = [[5, 1], [15, 2], [30, 3], [50, 4]];

    private const PULLUP = [[0, 1], [3, 2], [8, 3], [15, 4]];

    private const PLANK = [[20, 1], [45, 2], [90, 3], [150, 4]];

    public function score(int $pushup, int $pullup, int $plankSec): array
    {
        $levels = [
            'pushup' => $this->bucket($pushup, self::PUSHUP),
            'pullup' => $this->bucket($pullup, self::PULLUP),
            'plank' => $this->bucket($plankSec, self::PLANK),
        ];

        $avg = (int) round(array_sum($levels) / count($levels));
        $final = max(1, min($avg, $levels['pullup'] + 1, 5));

        return ['levels' => $levels, 'avg' => $avg, 'level' => $final];
    }

    public function apply(User $user, array $input): array
    {
        $result = $this->score($input['pushup'], $input['pullup'], $input['plank_sec']);

        $profile = Profile::firstOrCreate(['user_id' => $user->id]);
        $profile->update([
            'level' => $result['level'],
            'level_test' => $input + ['taken_at' => now()->toIso8601String()],
        ]);

        return $result + ['recommended_program' => $this->recommend($profile)];
    }

    /** ტრეკი ინვენტარიდან, დონე ტესტიდან, tie-breaker მიზნიდან */
    public function recommend(Profile $profile): ?array
    {
        $equipment = $profile->equipment ?? [];
        $track = match (true) {
            in_array('yard', $equipment, true) || in_array('gym', $equipment, true) => 'bar',
            in_array('bar', $equipment, true) => 'bar',
            default => 'home',
        };

        if ($profile->goal === 'skills' && $track === 'bar') {
            $track = 'skills';
        }

        $program = Program::query()
            ->where('is_active', true)
            ->where('track', $track)
            ->where('level', '<=', $profile->level)
            ->orderByDesc('level')
            ->first()
            ?? Program::where('is_active', true)->orderBy('level')->first();

        return $program ? ['id' => $program->id, 'slug' => $program->slug, 'track' => $program->track, 'level' => $program->level] : null;
    }

    private function bucket(int $value, array $thresholds): int
    {
        foreach ($thresholds as [$max, $level]) {
            if ($value <= $max) {
                return $level;
            }
        }

        return 5;
    }
}
