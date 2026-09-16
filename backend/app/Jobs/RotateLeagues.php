<?php

namespace App\Jobs;

use App\Services\LeagueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** კვირეული როტაცია: ორშაბათი 00:00 Asia/Tbilisi (სპეც. 7.1) */
class RotateLeagues implements ShouldQueue
{
    use Queueable;

    public function handle(LeagueService $leagues): void
    {
        $previousWeek = $leagues->currentWeekStart()->copy()->subWeek();

        $closed = $leagues->closeWeek($previousWeek);
        $created = $leagues->formWeek();

        Log::info('League rotation', ['closed' => $closed, 'created' => $created]);
    }
}
