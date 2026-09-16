<?php

namespace App\Jobs;

use App\Models\League;
use App\Services\LeagueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** ledger-იდან გადათვლა ყოველ 5 წუთში — Redis ინკრემენტების fallback */
class RecomputeLeagueStandings implements ShouldQueue
{
    use Queueable;

    public function handle(LeagueService $leagues): void
    {
        League::where('status', 'active')->pluck('id')->each(
            fn ($id) => $leagues->recomputeFromLedger($id)
        );
    }
}
