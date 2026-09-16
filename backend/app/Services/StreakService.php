<?php

namespace App\Services;

use App\Models\Streak;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Carbon;

/**
 * Streak-ის ლოგიკა (სპეც. 6.4).
 *
 * დასვენების დღე **არ წყვეტს** streak-ს, თუ mobility/stretch დაილოგება —
 * ანუ ნებისმიერი დალოგილი სესია დღეს ინარჩუნებს ჯაჭვს. აღდგენის
 * წახალისება უფრო მნიშვნელოვანია, ვიდრე ყოველდღიური დატვირთვა.
 */
class StreakService
{
    public function register(WorkoutSession $session): Streak
    {
        $user = $session->user;
        $tz = $user->timezone ?: config('kalisteni.league.timezone');

        /**
         * შედარება კალენდარულ თარიღზეა, არა Carbon-ის მომენტზე.
         * `last_activity_date` DB-დან UTC შუაღამით ბრუნდება, სესიის
         * თარიღი კი მომხმარებლის ზონაშია — ინსტანტების შედარება
         * ჯაჭვს ყოველთვის გაწყვეტდა.
         */
        $date = ($session->completed_at ?? $session->started_at)->copy()->setTimezone($tz)->toDateString();

        $streak = Streak::firstOrCreate(['user_id' => $user->id], [
            'current_days' => 0,
            'longest_days' => 0,
        ]);

        $last = $streak->last_activity_date?->toDateString();

        if ($last === $date) {
            return $streak; // დღეს უკვე დაფარულია — ორი სესია ერთ დღეს ერთხელ ითვლება
        }

        $yesterday = Carbon::parse($date)->subDay()->toDateString();
        $dayBefore = Carbon::parse($date)->subDays(2)->toDateString();

        if ($last === $yesterday) {
            $streak->current_days++;
        } elseif ($last === $dayBefore && $streak->freeze_tokens > 0) {
            // ერთი გამოტოვებული დღე freeze-ით იფარება
            $streak->freeze_tokens--;
            $streak->current_days++;
        } else {
            $streak->current_days = 1;
        }

        $streak->longest_days = max($streak->longest_days, $streak->current_days);
        $streak->last_activity_date = $date;
        $streak->save();

        return $streak;
    }

    /** დღეს რისკზეა? (20:00-ის push-ისთვის) */
    public function isAtRisk(User $user): bool
    {
        $streak = $user->streak;
        if (! $streak || $streak->current_days < 3) {
            return false;
        }

        $tz = $user->timezone ?: config('kalisteni.league.timezone');

        return $streak->last_activity_date?->toDateString() !== Carbon::now($tz)->toDateString();
    }
}
