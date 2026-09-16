<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\SessionSet;
use App\Models\Streak;
use App\Models\WorkoutSession;
use App\Services\LeagueService;
use App\Services\StreakService;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeagueAndStreakTest extends TestCase
{
    use RefreshDatabase;

    private function loggedSession($user, string $date): WorkoutSession
    {
        $session = WorkoutSession::create([
            'user_id' => $user->id,
            'started_at' => $date,
            'completed_at' => $date,
            'duration_ms' => 20 * 60_000,
            'source' => 'freestyle',
            'client_uuid' => Str::uuid(),
            'verification_tier' => 1,
        ]);

        SessionSet::create([
            'session_id' => $session->id,
            'exercise_id' => Exercise::first()?->id ?? 1,
            'set_no' => 1,
            'reps' => 10,
        ]);

        return $session;
    }

    public function test_consecutive_days_extend_the_streak(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = $this->makeAthlete();
        $streaks = app(StreakService::class);

        foreach (['2026-08-10 09:00', '2026-08-11 09:00', '2026-08-12 09:00'] as $date) {
            $streaks->register($this->loggedSession($user, $date));
        }

        $streak = Streak::find($user->id);
        $this->assertSame(3, $streak->current_days);
        $this->assertSame(3, $streak->longest_days);
    }

    public function test_two_sessions_on_the_same_day_count_once(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = $this->makeAthlete();
        $streaks = app(StreakService::class);

        $streaks->register($this->loggedSession($user, '2026-08-10 08:00'));
        $streaks->register($this->loggedSession($user, '2026-08-10 19:00'));

        $this->assertSame(1, Streak::find($user->id)->current_days);
    }

    public function test_a_missed_day_resets_the_streak(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = $this->makeAthlete();
        $streaks = app(StreakService::class);

        $streaks->register($this->loggedSession($user, '2026-08-10 09:00'));
        $streaks->register($this->loggedSession($user, '2026-08-11 09:00'));
        $streaks->register($this->loggedSession($user, '2026-08-14 09:00'));

        $streak = Streak::find($user->id);
        $this->assertSame(1, $streak->current_days);
        $this->assertSame(2, $streak->longest_days, 'საუკეთესო შედეგი რჩება');
    }

    public function test_the_streak_multiplier_follows_the_specification(): void
    {
        $streak = new Streak;

        foreach ([[0, 1.0], [2, 1.0], [3, 1.05], [7, 1.10], [14, 1.15], [30, 1.25], [120, 1.25]] as [$days, $expected]) {
            $streak->current_days = $days;
            $this->assertSame($expected, $streak->multiplier(), "streak {$days} დღე");
        }
    }

    public function test_leagues_stay_closed_until_two_hundred_weekly_athletes(): void
    {
        Sanctum::actingAs($this->makeAthlete());

        $response = $this->getJson('/api/v1/league/current')->assertOk();

        $this->assertFalse($response->json('enabled'));
        $this->assertSame('cold_start', $response->json('reason'));
        $this->assertSame(200, $response->json('required'));
    }

    public function test_no_league_is_formed_below_the_threshold(): void
    {
        $this->assertSame(0, app(LeagueService::class)->formWeek());
    }

    public function test_the_league_week_starts_on_monday_in_tbilisi(): void
    {
        $start = app(LeagueService::class)->currentWeekStart();

        $this->assertSame('Monday', $start->format('l'));
        $this->assertSame('Asia/Tbilisi', $start->timezone->getName());
    }
}
