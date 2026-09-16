<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\ProgramDayExercise;
use App\Models\ProgramTranslation;
use Illuminate\Database\Seeder;

/**
 * 3 ტრეკი × 3 დონე, 8-კვირიანი ციკლები (სპეც. 4.1 / M4).
 *
 * მოცულობა კვირიდან კვირაზე იზრდება, მე-4 და მე-8 კვირა deload-ია.
 * დასვენების დღეები პროგრამაშია ჩაშენებული და streak-ს არ წყვეტს (სპეც. 18).
 */
class ProgramSeeder extends Seeder
{
    /** ტრეკი → დონე → სავარჯიშოების ბლოკი [slug, sets, reps|null, seconds|null, rest] */
    private array $blueprints = [
        'home' => [
            1 => [
                ['knee-push-up', 3, 8, null, 75],
                ['squat', 3, 12, null, 60],
                ['glute-bridge', 3, 12, null, 60],
                ['plank', 3, null, 20, 60],
            ],
            2 => [
                ['push-up', 4, 10, null, 90],
                ['squat', 4, 18, null, 75],
                ['lunge', 3, 10, null, 60],
                ['side-plank', 3, null, 30, 45],
                ['hollow-body-hold', 3, null, 20, 60],
            ],
            3 => [
                ['diamond-push-up', 4, 12, null, 90],
                ['archer-push-up', 3, 6, null, 105],
                ['bulgarian-split-squat', 4, 10, null, 90],
                ['hollow-body-hold', 4, null, 35, 60],
                ['dragon-flag', 3, null, 10, 120],
            ],
        ],
        'bar' => [
            1 => [
                ['australian-row', 4, 8, null, 90],
                ['dead-hang', 3, null, 20, 60],
                ['incline-push-up', 3, 10, null, 75],
                ['squat', 3, 15, null, 60],
                ['plank', 3, null, 30, 60],
            ],
            2 => [
                ['pull-up', 4, 5, null, 120],
                ['dip', 4, 6, null, 105],
                ['push-up', 3, 15, null, 75],
                ['hanging-knee-raise', 3, 10, null, 75],
                ['lunge', 3, 12, null, 60],
            ],
            3 => [
                ['pull-up', 5, 8, null, 120],
                ['wide-pull-up', 3, 6, null, 120],
                ['dip', 4, 12, null, 105],
                ['hanging-leg-raise', 4, 10, null, 90],
                ['pistol-squat', 3, 6, null, 105],
            ],
        ],
        'skills' => [
            1 => [
                ['hollow-body-hold', 4, null, 25, 75],
                ['australian-row', 4, 10, null, 90],
                ['pseudo-planche-push-up', 3, 6, null, 105],
                ['dead-hang', 3, null, 30, 60],
                ['shoulder-dislocates', 2, 12, null, 45],
            ],
            2 => [
                ['tuck-front-lever', 5, null, 12, 120],
                ['tuck-planche', 5, null, 10, 120],
                ['l-sit', 4, null, 15, 90],
                ['pull-up', 4, 6, null, 120],
                ['handstand-hold', 4, null, 20, 90],
            ],
            3 => [
                ['front-lever', 5, null, 8, 150],
                ['planche', 5, null, 5, 150],
                ['muscle-up', 4, 3, null, 180],
                ['handstand-push-up', 4, 5, null, 150],
                ['back-lever', 4, null, 10, 150],
            ],
        ],
    ];

    private array $titles = [
        'home' => [
            'ka' => ['სახლის ბაზისი', 'სახლის ძალა', 'სახლი — მოწინავე'],
            'ru' => ['Домашняя база', 'Домашняя сила', 'Дом — продвинутый'],
            'en' => ['Home foundation', 'Home strength', 'Home advanced'],
        ],
        'bar' => [
            'ka' => ['ტურნიკის ბაზისი', 'ტურნიკის ძალა', 'ტურნიკი — მოწინავე'],
            'ru' => ['База на турнике', 'Сила на турнике', 'Турник — продвинутый'],
            'en' => ['Bar foundation', 'Bar strength', 'Bar advanced'],
        ],
        'skills' => [
            'ka' => ['Skills — შესავალი', 'Skills — სტატიკა', 'Skills — ელიტა'],
            'ru' => ['Skills — введение', 'Skills — статика', 'Skills — элита'],
            'en' => ['Skills — intro', 'Skills — statics', 'Skills — elite'],
        ],
    ];

    private array $descriptions = [
        'home' => [
            'ka' => 'ინვენტარის გარეშე. 3 ვარჯიში კვირაში, 8 კვირა. მე-4 და მე-8 კვირა მსუბუქია — აღდგენა პროგრამის ნაწილია.',
            'ru' => 'Без инвентаря. 3 тренировки в неделю, 8 недель. 4-я и 8-я недели лёгкие — восстановление часть плана.',
            'en' => 'No equipment. Three sessions a week for eight weeks. Weeks four and eight are light — recovery is part of the plan.',
        ],
        'bar' => [
            'ka' => 'ტურნიკი და ბრუსები. მოზიდვისა და დიპის პროგრესია, კვირაში 4 ვარჯიში.',
            'ru' => 'Турник и брусья. Прогрессия подтягиваний и брусьев, 4 тренировки в неделю.',
            'en' => 'Bar and parallel bars. Pull-up and dip progression, four sessions a week.',
        ],
        'skills' => [
            'ka' => 'სტატიკური ელემენტები: front lever, planche, handstand. მოითხოვს მყარ ბაზისს — ნუ დაიწყებ, თუ 8 სუფთა მოზიდვა არ გაქვს.',
            'ru' => 'Статические элементы: front lever, planche, handstand. Нужна крепкая база — не начинай без 8 чистых подтягиваний.',
            'en' => 'Static skills: front lever, planche, handstand. Requires a solid base — do not start without eight strict pull-ups.',
        ],
    ];

    public function run(): void
    {
        $daysPerWeek = ['home' => 3, 'bar' => 4, 'skills' => 4];

        foreach ($this->blueprints as $track => $levels) {
            foreach ($levels as $level => $block) {
                $program = Program::updateOrCreate(
                    ['slug' => "{$track}-l{$level}"],
                    [
                        'track' => $track,
                        'level' => $level,
                        'duration_weeks' => 8,
                        'days_per_week' => $daysPerWeek[$track],
                        'goals' => $this->goalsFor($track),
                        'equipment' => $this->equipmentFor($track),
                        'is_premium' => false,
                        'is_active' => true,
                    ],
                );

                foreach (['ka', 'ru', 'en'] as $locale) {
                    ProgramTranslation::updateOrCreate(
                        ['program_id' => $program->id, 'locale' => $locale],
                        [
                            'title' => $this->titles[$track][$locale][$level - 1],
                            'description' => $this->descriptions[$track][$locale],
                        ],
                    );
                }

                $this->buildDays($program, $block);
            }
        }

        $this->command->info('Programs: '.Program::count().', days: '.ProgramDay::count());
    }

    private function buildDays(Program $program, array $block): void
    {
        ProgramDay::where('program_id', $program->id)->delete();

        for ($week = 1; $week <= $program->duration_weeks; $week++) {
            // deload კვირები — მოცულობა ეცემა, ეს გეგმის ნაწილია და არა ჩავარდნა
            $isDeload = in_array($week, [4, 8], true);
            $volumeScale = $isDeload ? 0.6 : 1 + ($week - 1) * 0.06;

            for ($day = 1; $day <= $program->days_per_week; $day++) {
                // ბოლო დღე ყოველ მე-4 კვირაში ტესტია — პროგრესის გაზომვა
                $type = ($isDeload && $day === $program->days_per_week) ? 'test' : 'workout';

                $programDay = ProgramDay::create([
                    'program_id' => $program->id,
                    'week_no' => $week,
                    'day_no' => $day,
                    'type' => $type,
                    'title_key' => "program.day.{$type}",
                    'est_minutes' => $type === 'test' ? 25 : 35 + count($block) * 3,
                ]);

                foreach ($block as $i => [$slug, $sets, $reps, $seconds, $rest]) {
                    $exercise = Exercise::where('slug', $slug)->first();
                    if (! $exercise) {
                        continue;
                    }

                    ProgramDayExercise::create([
                        'program_day_id' => $programDay->id,
                        'exercise_id' => $exercise->id,
                        'sort_order' => $i,
                        'sets' => $type === 'test' ? 1 : $sets,
                        'target_reps' => $reps ? max(1, (int) round($reps * $volumeScale)) : null,
                        'target_seconds' => $seconds ? max(5, (int) round($seconds * $volumeScale)) : null,
                        'rest_seconds' => $rest,
                        // ნელი ტემპი (tempo_mod ×1.2) მე-2 კვირიდან ერთ სავარჯიშოზე
                        'tempo' => ($week >= 2 && $i === 0 && ! $isDeload) ? 'slow' : 'normal',
                    ]);
                }
            }
        }
    }

    private function goalsFor(string $track): array
    {
        return match ($track) {
            'home' => ['health', 'weight_loss', 'muscle'],
            'bar' => ['strength', 'muscle'],
            default => ['skills', 'strength'],
        };
    }

    private function equipmentFor(string $track): array
    {
        return match ($track) {
            'home' => ['none'],
            'bar' => ['bar', 'yard', 'gym'],
            default => ['bar', 'yard'],
        };
    }
}
