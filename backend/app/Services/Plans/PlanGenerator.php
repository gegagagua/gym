<?php

namespace App\Services\Plans;

use App\Http\Controllers\Api\V1\ExerciseController;
use App\Models\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanDay;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * კალენდარის პლანერი: კვირის დღეები × ლოკაცია × ინტენსიობა → განრიგი.
 *
 * დეტერმინისტულია — იმავე შეყვანაზე იმავე სავარჯიშოებს აბრუნებს, ასე
 * ტესტირდება და progressive overload-ი ბლოკის შიგნით იმავე მოძრაობებზე
 * ხდება. ჯანმრთელობის ლიმიტები (სპეც. 18) აქ ჩაშენებულია: კვირაში
 * მინიმუმ ერთი დასვენების დღე, სესია ≤ session.max_minutes, 4+ კვირაზე
 * ბოლო კვირა deload-ია, level 1-ზე intense 3 სეტს არ აჭარბებს.
 */
class PlanGenerator
{
    public const MAX_TRAINING_DAYS = 6;

    /** ლოკაცია → პროფილის წვდომის დონე (თარგმანი ExerciseController-შია) */
    private const LOCATION_ACCESS = ['home' => 'none', 'yard' => 'yard', 'gym' => 'gym'];

    private const INTENSITY = [
        'light' => ['sets' => 2, 'reps' => [8, 12], 'gym_reps' => 12, 'hold' => 20, 'rest' => 90, 'per_day' => 4, 'level_shift' => -1],
        'moderate' => ['sets' => 3, 'reps' => [8, 12], 'gym_reps' => 10, 'hold' => 30, 'rest' => 75, 'per_day' => 6, 'level_shift' => 0],
        'intense' => ['sets' => 4, 'reps' => [6, 10], 'gym_reps' => 8, 'hold' => 40, 'rest' => 60, 'per_day' => 7, 'level_shift' => 0],
    ];

    private const SPLITS = [
        'full' => ['legs', 'chest', 'back', 'core', 'shoulders'],
        'upper' => ['chest', 'back', 'shoulders', 'arms'],
        'lower' => ['legs', 'core'],
        'push' => ['chest', 'shoulders', 'arms'],
        'pull' => ['back', 'arms', 'core'],
        'legs' => ['legs', 'core'],
    ];

    /** სავარჯიშოს level_min → hold-ის მაქსიმუმი წამებში */
    private const HOLD_CAP = [1 => 60, 2 => 45, 3 => 25, 4 => 20, 5 => 15];

    private const SECONDS_PER_REP = 3;

    private const WARMUP_MINUTES = 5;

    /**
     * @param  array{schedule: list<array{weekday:int, location:string}>, intensity:string, weeks?:int, starts_on?:string, level?:int}  $input
     */
    public function generate(User $user, array $input): TrainingPlan
    {
        $tz = $user->timezone ?: config('kalisteni.league.timezone');
        $intensity = $input['intensity'];
        $level = (int) ($input['level'] ?? $user->profile?->level ?? 1);
        $weeks = (int) ($input['weeks'] ?? 4);
        $startsOn = isset($input['starts_on']) ? Carbon::parse($input['starts_on'], $tz) : now($tz);
        $startsOn = $startsOn->startOfDay();

        $schedule = collect($input['schedule'])
            ->map(fn ($d) => ['weekday' => (int) $d['weekday'], 'location' => $d['location']])
            ->unique('weekday')
            ->sortBy('weekday')
            ->values();

        $splits = $this->splitsFor($schedule->count(), $level);
        $splitByWeekday = $schedule->mapWithKeys(fn ($d, $i) => [$d['weekday'] => [
            'split' => $splits[$i],
            'location' => $d['location'],
        ]]);

        $library = Exercise::active()->whereNotNull('zone')->get();

        return DB::transaction(function () use ($user, $intensity, $level, $weeks, $startsOn, $schedule, $splitByWeekday, $library) {
            TrainingPlan::where('user_id', $user->id)->where('status', 'active')->update(['status' => 'archived']);

            $plan = TrainingPlan::create([
                'user_id' => $user->id,
                'status' => 'active',
                'intensity' => $intensity,
                'level' => $level,
                'goal' => $user->profile?->goal,
                'schedule' => $schedule->all(),
                'weeks' => $weeks,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->copy()->addDays($weeks * 7 - 1)->toDateString(),
            ]);

            for ($offset = 0; $offset < $weeks * 7; $offset++) {
                $date = $startsOn->copy()->addDays($offset);
                $weekNo = intdiv($offset, 7) + 1;
                $slot = $splitByWeekday[$date->isoWeekday()] ?? null;

                $day = $plan->days()->create([
                    'date' => $date->toDateString(),
                    'week_no' => $weekNo,
                    'weekday' => $date->isoWeekday(),
                    'type' => $slot ? 'workout' : 'rest',
                    'split' => $slot['split'] ?? null,
                    'location' => $slot['location'] ?? null,
                    'focus' => $slot ? self::SPLITS[$slot['split']] : null,
                    'is_deload' => $slot !== null && $weeks >= 4 && $weekNo === $weeks,
                ]);

                if ($slot) {
                    $this->fillDay($day, $plan, $library, $user->id);
                }
            }

            return $plan->load('days.exercises.exercise');
        });
    }

    /** @return list<string> */
    public function splitsFor(int $days, int $level): array
    {
        if ($level <= 2) {
            return $days <= 3
                ? array_fill(0, $days, 'full')
                : array_map(fn ($i) => $i % 2 ? 'lower' : 'upper', range(0, $days - 1));
        }

        return match ($days) {
            1, 2 => array_fill(0, $days, 'full'),
            3 => ['push', 'pull', 'legs'],
            4 => ['upper', 'lower', 'upper', 'lower'],
            5 => ['push', 'pull', 'legs', 'upper', 'lower'],
            default => ['push', 'pull', 'legs', 'push', 'pull', 'legs'],
        };
    }

    private function fillDay(TrainingPlanDay $day, TrainingPlan $plan, Collection $library, int $seed): void
    {
        $cfg = self::INTENSITY[$plan->intensity];
        $effLevel = max(1, min(5, $plan->level + $cfg['level_shift']));
        $allowed = ExerciseController::expandEquipment([self::LOCATION_ACCESS[$day->location]]);
        // ბლოკი = 2 კვირა: ბლოკის შიგნით იგივე მოძრაობები (progressive overload), მერე ვარიაცია
        $block = intdiv($day->week_no - 1, 2);

        $picked = [];
        $zones = $day->focus;

        for ($slot = 0; count($picked) < $cfg['per_day'] && $slot < $cfg['per_day'] * 3; $slot++) {
            $zone = $zones[$slot % count($zones)];
            $exercise = $this->pick($library, $zone, $day, $effLevel, $allowed, $picked, "{$seed}|{$day->weekday}|{$block}");

            if ($exercise) {
                $picked[$exercise->id] = $exercise;
            }
        }

        $maxMinutes = config('kalisteni.session.max_minutes');
        $rows = [];
        $minutes = self::WARMUP_MINUTES;

        foreach (array_values($picked) as $i => $exercise) {
            $row = $this->prescribe($exercise, $plan, $day, $cfg);
            $work = $row['target_seconds'] ?? ($row['target_reps'] * self::SECONDS_PER_REP);
            $cost = $row['sets'] * ($work + $row['rest_seconds']) / 60;

            if ($minutes + $cost > $maxMinutes) {
                break;
            }

            $minutes += $cost;
            $rows[] = $row + ['exercise_id' => $exercise->id, 'sort_order' => $i];
        }

        $day->exercises()->createMany($rows);
        $day->update(['est_minutes' => (int) ceil($minutes)]);
    }

    private function pick(Collection $library, string $zone, TrainingPlanDay $day, int $level, array $allowed, array $picked, string $seed): ?Exercise
    {
        $base = $library->filter(fn (Exercise $e) => $e->zone === $zone
            && ! isset($picked[$e->id])
            && array_diff($e->equipment ?? [], $allowed) === []
            && $this->armsMatchSplit($e, $day->split));

        // ჯერ ზუსტი დონე; თუ ზონაში ცარიელია — ყველა, რაც დონეს არ აჭარბებს
        $candidates = $base->filter(fn ($e) => $e->level_min <= $level && $e->level_max >= $level);
        if ($candidates->isEmpty()) {
            $candidates = $base->filter(fn ($e) => $e->level_min <= $level);
        }

        // ზონის პირველი მოძრაობა — compound (squat, არა calf raise); იზოლაცია მეორე სლოტიდან
        $firstInZone = collect($picked)->where('zone', $zone)->isEmpty();

        return $candidates
            ->sortBy([
                fn ($a, $b) => $this->locationScore($b, $day->location) <=> $this->locationScore($a, $day->location),
                fn ($a, $b) => $firstInZone ? ($b->mechanic === 'compound') <=> ($a->mechanic === 'compound') : 0,
                fn ($a, $b) => crc32("{$seed}|{$a->slug}") <=> crc32("{$seed}|{$b->slug}"),
            ])
            ->first();
    }

    /** push-დღეს ტრიცეპსი, pull-დღეს ბიცეპსი — თორემ „მკლავები" split-ს ლაუწავს */
    private function armsMatchSplit(Exercise $e, ?string $split): bool
    {
        if ($e->zone !== 'arms') {
            return true;
        }

        return match ($split) {
            'push' => $e->force === 'push',
            'pull' => $e->force === 'pull',
            default => true,
        };
    }

    /** დარბაზის დღეს დარბაზის ინვენტარი, ეზოს დღეს ტურნიკი — თორემ gym-ში push-up-ებს მიიღებდა */
    private function locationScore(Exercise $e, string $location): int
    {
        $equipment = $e->equipment ?? [];

        return match ($location) {
            'gym' => array_intersect($equipment, Exercise::GYM_TAGS) ? 2 : ($equipment ? 1 : 0),
            'yard' => $equipment ? 1 : 0,
            default => 0,
        };
    }

    /** @return array{sets:int, target_reps:?int, target_seconds:?int, rest_seconds:int} */
    private function prescribe(Exercise $exercise, TrainingPlan $plan, TrainingPlanDay $day, array $cfg): array
    {
        $sets = $cfg['sets'];
        if ($plan->level <= 1) {
            $sets = min($sets, 3);
        }
        if ($day->is_deload) {
            $sets = max(1, $sets - 1);
        }

        // ბლოკის შიგნით კვირიდან კვირაზე +1 გამეორება / +5 წამი; deload-ზე ზრდა არ არის
        $step = $day->is_deload ? 0 : ($day->week_no - 1) % 2;

        if ($exercise->unit === 'seconds') {
            $levelScale = [1 => 0.6, 2 => 0.8, 3 => 1.0, 4 => 1.2, 5 => 1.4][$plan->level];
            $target = (int) (round($cfg['hold'] * $levelScale / 5) * 5) + $step * 5;

            return [
                'sets' => $sets,
                'target_reps' => null,
                // dragon flag-ი ან planche 55 წმ ფიზიკურად საშიშია — რთული hold-ის ჭერი
                'target_seconds' => min($target, self::HOLD_CAP[$exercise->level_min] ?? 60),
                'rest_seconds' => $cfg['rest'],
            ];
        }

        $isLoaded = array_intersect($exercise->equipment ?? [], Exercise::GYM_TAGS) !== [];
        [$low, $high] = $cfg['reps'];
        // მომხმარებლის დონის ზღვარზე მდგომი მოძრაობა — დიაპაზონის ქვედა ბოლო
        $reps = $isLoaded ? $cfg['gym_reps'] : ($exercise->level_min >= $plan->level ? $low : $high);

        return [
            'sets' => $sets,
            'target_reps' => $reps + $step,
            'target_seconds' => null,
            'rest_seconds' => $isLoaded ? $cfg['rest'] + 30 : $cfg['rest'],
        ];
    }
}
