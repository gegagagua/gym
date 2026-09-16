<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** 30-დღიანი grace გავიდა → hard delete (Apple-ის მოთხოვნა, სპეც. 17) */
class PurgeDeletedAccounts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        User::onlyTrashed()
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->each(fn (User $user) => $user->forceDelete());
    }
}
