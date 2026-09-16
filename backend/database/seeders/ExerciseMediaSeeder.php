<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Services\Media\ExerciseMediaFetcher;
use Illuminate\Database\Seeder;

/**
 * მედიის რიგები უკვე ჩამოტვირთული ფაილებიდან.
 *
 * სიდერი ქსელში არ დადის — `migrate:fresh --seed` ოფლაინაც უნდა მუშაობდეს.
 * ფაილების ჩამოტვირთვა ცალკე ბრძანებაა: `php artisan exercises:fetch-media`.
 */
class ExerciseMediaSeeder extends Seeder
{
    public function run(): void
    {
        $fetcher = app(ExerciseMediaFetcher::class);
        $registered = 0;

        foreach (Exercise::all() as $exercise) {
            if ($fetcher->registerExisting($exercise)) {
                $registered++;
            }
        }

        $this->command->info("Exercise media: {$registered}");

        if ($registered === 0) {
            $this->command->comment('ლუპები ჯერ არ არის — გაუშვი: php artisan exercises:fetch-media');
        }
    }
}
