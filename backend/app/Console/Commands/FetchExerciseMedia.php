<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Services\Media\ExerciseMediaFetcher;
use Illuminate\Console\Command;
use Throwable;

/**
 * სავარჯიშოს ლუპების ჩამოტვირთვა ღია წყაროებიდან.
 *
 *   php artisan exercises:fetch-media
 *   php artisan exercises:fetch-media --only=push-up,burpee --force
 *
 * წყაროების რუკა: database/data/exercise_media_sources.json
 */
class FetchExerciseMedia extends Command
{
    protected $signature = 'exercises:fetch-media
        {--only= : მძიმით გამოყოფილი slug-ები}
        {--force : არსებულის თავიდან ჩამოტვირთვა}';

    protected $description = 'სავარჯიშოს GIF-ლუპების აწყობა ღია წყაროებიდან (free-exercise-db, Wikimedia Commons)';

    public function handle(ExerciseMediaFetcher $fetcher): int
    {
        $query = Exercise::query()->orderBy('id');

        if ($only = $this->option('only')) {
            $query->whereIn('slug', array_filter(array_map('trim', explode(',', $only))));
        }

        $exercises = $query->get();

        if ($exercises->isEmpty()) {
            $this->error('სავარჯიშოები ვერ მოიძებნა — ჯერ `php artisan db:seed` გაუშვი.');

            return self::FAILURE;
        }

        $synced = $skipped = $pending = $failed = 0;
        $bytes = 0;

        $bar = $this->output->createProgressBar($exercises->count());
        $bar->start();

        foreach ($exercises as $exercise) {
            try {
                $result = $fetcher->sync($exercise, (bool) $this->option('force'));

                match ($result['status']) {
                    'synced' => [$synced++, $bytes += $result['bytes'] ?? 0],
                    'skipped' => $skipped++,
                    default => $pending++,
                };
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("{$exercise->slug}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['ჩამოტვირთული', 'გამოტოვებული', 'საკუთარ გადაღებას ელოდება', 'შეცდომა', 'ზომა'],
            [[$synced, $skipped, $pending, $failed, round($bytes / 1048576, 1).' MB']],
        );

        if ($pending > 0 && ! $this->option('only')) {
            $this->line('');
            $this->comment('ღია წყაროს გარეშე დარჩა (სპეც. 13.3 — საკუთარი გადაღება):');

            foreach ($fetcher->pending() as $slug => $reason) {
                $this->line("  · {$slug} — {$reason}");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
