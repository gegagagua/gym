<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\SessionSet;
use App\Models\WorkoutSession;
use App\Models\XpLedger;
use App\Services\SpotService;
use App\Services\Xp\XpCalculator;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class XpCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseSeeder::class);
    }

    private function sessionWith(array $sets, array $overrides = []): WorkoutSession
    {
        $user = $overrides['user'] ?? $this->makeAthlete();
        unset($overrides['user']);

        $session = WorkoutSession::create([
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(40),
            'completed_at' => now(),
            'duration_ms' => 40 * 60_000,
            'source' => 'freestyle',
            'client_uuid' => Str::uuid(),
            'verification_tier' => 1,
        ] + $overrides);

        foreach ($sets as $i => $set) {
            SessionSet::create([
                'session_id' => $session->id,
                'exercise_id' => Exercise::where('slug', $set['slug'])->value('id'),
                'set_no' => $i + 1,
                'reps' => $set['reps'] ?? null,
                'seconds' => $set['seconds'] ?? null,
                'added_weight_kg' => $set['weight'] ?? 0,
                'tempo' => $set['tempo'] ?? 'normal',
            ]);
        }

        return $session->fresh();
    }

    public function test_baseline_push_up_is_one_xp_per_rep(): void
    {
        $session = $this->sessionWith([['slug' => 'push-up', 'reps' => 20]]);

        $result = app(XpCalculator::class)->calculate($session);

        $this->assertSame(20, $result->workoutXp);
    }

    public function test_hold_exercises_score_per_five_seconds(): void
    {
        // plank k=1.0, 60 წმ → 60/5 = 12 XP
        $session = $this->sessionWith([['slug' => 'plank', 'seconds' => 60]]);

        $this->assertSame(12, app(XpCalculator::class)->calculate($session)->workoutXp);
    }

    public function test_slow_tempo_applies_a_twenty_percent_bonus(): void
    {
        $session = $this->sessionWith([['slug' => 'push-up', 'reps' => 10, 'tempo' => 'slow']]);

        $this->assertSame(12, app(XpCalculator::class)->calculate($session)->workoutXp);
    }

    public function test_added_weight_scales_by_bodyweight(): void
    {
        // 80 კგ სხეული + 20 კგ = ×1.25; dip k=2.0, 10 გამეორება → 25
        $session = $this->sessionWith([['slug' => 'dip', 'reps' => 10, 'weight' => 20]]);

        $this->assertSame(25, app(XpCalculator::class)->calculate($session)->workoutXp);
    }

    public function test_weight_multiplier_is_capped_at_two(): void
    {
        // 200 კგ დამატებით 80 კგ სხეულზე იქნებოდა ×3.5 — ჭერი ×2.0-ია
        $session = $this->sessionWith([['slug' => 'push-up', 'reps' => 10, 'weight' => 200]]);

        $this->assertSame(20, app(XpCalculator::class)->calculate($session)->workoutXp);
    }

    public function test_check_in_adds_fifteen_percent(): void
    {
        $user = $this->makeAthlete();

        $spot = app(SpotService::class)->create([
            'name' => 'ვაკის პარკი',
            'type' => 'park',
            'status' => 'verified',
        ], 41.7089, 44.7573);

        $checkin = app(SpotService::class)
            ->checkIn($user, $spot, 41.7089, 44.7573)['checkin'];

        $session = $this->sessionWith([['slug' => 'push-up', 'reps' => 100]], ['user' => $user]);
        $session->update(['spot_checkin_id' => $checkin->id]);

        $result = app(XpCalculator::class)->calculate($session->fresh());

        $this->assertSame(115, $result->workoutXp);
    }

    public function test_daily_cap_stops_further_xp(): void
    {
        $user = $this->makeAthlete();

        // 1800 XP ჭერი: muscle-up k=8.0 → 250 გამეორება = 2000 XP ნედლი
        $session = $this->sessionWith([['slug' => 'muscle-up', 'reps' => 250]], ['user' => $user]);

        $result = app(XpCalculator::class)->calculate($session);

        $this->assertSame(1800, $result->workoutXp);
        $this->assertTrue($result->cappedOut);
        // ledger-ის ჯამი ყოველთვის ტოლია დარიცხულის — აუდიტი არ ირღვევა
        $this->assertSame(1800, (int) XpLedger::where('user_id', $user->id)->where('reason', 'workout')->sum('xp'));
    }

    public function test_skill_unlock_is_awarded_once(): void
    {
        $user = $this->makeAthlete();

        $first = $this->sessionWith([['slug' => 'pull-up', 'reps' => 3]], ['user' => $user]);
        $result = app(XpCalculator::class)->calculate($first);

        $this->assertSame(200, $result->bonusXp);
        $this->assertCount(1, $result->unlocked);

        $second = $this->sessionWith([['slug' => 'pull-up', 'reps' => 5]], ['user' => $user]);
        $again = app(XpCalculator::class)->calculate($second);

        $this->assertSame(0, $again->bonusXp, 'ბონუსი ერთჯერადია');
    }

    public function test_diminishing_returns_after_hundred_reps_in_a_day(): void
    {
        $user = $this->makeAthlete();

        $earlier = $this->sessionWith([['slug' => 'push-up', 'reps' => 120]], ['user' => $user]);
        app(XpCalculator::class)->calculate($earlier);

        $later = $this->sessionWith([['slug' => 'push-up', 'reps' => 50]], ['user' => $user]);
        $result = app(XpCalculator::class)->calculate($later);

        // 50 × 1.0 × 0.3 = 15
        $this->assertSame(15, $result->workoutXp);
    }

    public function test_the_ledger_snapshots_the_coefficient(): void
    {
        $session = $this->sessionWith([['slug' => 'pull-up', 'reps' => 5]]);
        app(XpCalculator::class)->calculate($session);

        $entry = XpLedger::where('session_id', $session->id)->where('reason', 'workout')->first();

        $this->assertSame(3.0, (float) $entry->k_snapshot);

        // კოეფიციენტის ცვლილება ისტორიას არ ეხება
        Exercise::where('slug', 'pull-up')->update(['difficulty_coef' => 5.0]);

        $this->assertSame(3.0, (float) $entry->fresh()->k_snapshot);
    }

    public function test_the_ledger_rejects_updates(): void
    {
        $session = $this->sessionWith([['slug' => 'push-up', 'reps' => 5]]);
        app(XpCalculator::class)->calculate($session);

        $entry = XpLedger::where('session_id', $session->id)->first();

        $this->expectException(\LogicException::class);
        $entry->update(['xp' => 9999]);
    }
}
