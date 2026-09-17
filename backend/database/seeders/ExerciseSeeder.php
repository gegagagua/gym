<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseTranslation;
use Illuminate\Database\Seeder;

/**
 * კოეფიციენტები კალიბრირებულია ისე, რომ 1 კლასიკური push-up = 1.0 XP
 * (სპეც. 6.3). გადაკალიბრება პირველ 3 თვეში გარდაუვალია — ისტორია
 * არ ზიანდება, რადგან xp_ledger.k_snapshot ინახავს ძველ მნიშვნელობებს.
 */
class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/exercises.json')), true);

        foreach ($data as $row) {
            $exercise = Exercise::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category' => $row['category'],
                    'zone' => $row['zone'] ?? null,
                    'force' => $row['force'],
                    'mechanic' => $row['mechanic'],
                    'unit' => $row['unit'],
                    'difficulty_coef' => $row['difficulty_coef'],
                    'level_min' => $row['level_min'],
                    'level_max' => $row['level_max'],
                    'equipment' => $row['equipment'],
                    'primary_muscles' => $row['primary_muscles'],
                    'secondary_muscles' => $row['secondary_muscles'],
                    'skill_group' => $row['skill_group'],
                    'is_skill_unlock' => $row['is_skill_unlock'],
                    'unlock_bonus_xp' => $row['unlock_bonus_xp'],
                    'unlock_threshold' => $row['unlock_threshold'],
                    'is_active' => true,
                ],
            );

            foreach ($row['translations'] as $locale => $t) {
                ExerciseTranslation::updateOrCreate(
                    ['exercise_id' => $exercise->id, 'locale' => $locale],
                    [
                        'name' => $t['name'],
                        'short_desc' => $t['short_desc'],
                        'instructions' => $t['instructions'],
                        'common_mistakes' => $t['common_mistakes'],
                    ],
                );
            }
        }

        // პროგრესიის ჯაჭვი მეორე გავლაზე — ყველა id უკვე არსებობს
        foreach ($data as $row) {
            if (! $row['progression_to']) {
                continue;
            }

            $from = Exercise::where('slug', $row['slug'])->first();
            $to = Exercise::where('slug', $row['progression_to'])->first();

            if ($from && $to) {
                $from->update(['progression_to_id' => $to->id]);
                $to->update(['progression_from_id' => $from->id]);
            }
        }

        $this->command->info('Exercises: '.Exercise::count());
    }
}
