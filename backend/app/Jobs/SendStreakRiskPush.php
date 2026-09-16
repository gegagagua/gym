<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PushService;
use App\Services\StreakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 20:00 ლოკალურად: streak ≥ 3 და დღეს სესია არ არის.
 * ლიმიტი — მაქს. 2 push დღეში (სპეც. 15).
 */
class SendStreakRiskPush implements ShouldQueue
{
    use Queueable;

    public function handle(StreakService $streaks, PushService $push): void
    {
        User::query()
            ->whereHas('streak', fn ($q) => $q->where('current_days', '>=', 3))
            ->whereNull('deleted_at')
            ->chunkById(200, function ($users) use ($streaks, $push) {
                foreach ($users as $user) {
                    if (now($user->timezone ?: 'Asia/Tbilisi')->hour !== config('kalisteni.push.streak_risk_hour')) {
                        continue;
                    }

                    if ($streaks->isAtRisk($user)) {
                        $push->send($user, 'streak_risk', [
                            'days' => $user->streak->current_days,
                        ]);
                    }
                }
            });
    }
}
