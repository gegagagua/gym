<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\WorkoutSession;
use App\Models\XpLedger;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        $pushUp = Exercise::where('slug', 'push-up')->value('id');
        $squat = Exercise::where('slug', 'squat')->value('id');

        return array_replace_recursive([
            'client_uuid' => (string) Str::uuid(),
            'started_at' => now()->subMinutes(30)->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'source' => 'freestyle',
            'device_clock_offset_ms' => 120,
            'sets' => [
                ['exercise_id' => $pushUp, 'set_no' => 1, 'reps' => 15,
                    'started_at' => now()->subMinutes(28)->toIso8601String(),
                    'completed_at' => now()->subMinutes(28)->addSeconds(30)->toIso8601String(),
                    'rest_after_ms' => 90_000],
                ['exercise_id' => $squat, 'set_no' => 1, 'reps' => 20,
                    'started_at' => now()->subMinutes(24)->toIso8601String(),
                    'completed_at' => now()->subMinutes(24)->addSeconds(45)->toIso8601String(),
                    'rest_after_ms' => 60_000],
            ],
        ], $overrides);
    }

    public function test_it_calculates_xp_on_the_server_and_ignores_the_client(): void
    {
        Sanctum::actingAs($this->makeAthlete());

        $payload = $this->payload();
        $payload['total_xp'] = 999_999;   // კლიენტის ტყუილი

        $response = $this->postJson('/api/v1/sessions/sync', ['sessions' => [$payload]]);

        $response->assertOk();
        // push-up 15 × 1.0 + squat 20 × 0.8 = 15 + 16 = 31
        $this->assertSame(31, $response->json('results.0.xp_awarded'));
    }

    public function test_resending_the_same_client_uuid_is_idempotent(): void
    {
        Sanctum::actingAs($this->makeAthlete());
        $payload = $this->payload();

        $first = $this->postJson('/api/v1/sessions/sync', ['sessions' => [$payload]]);
        $second = $this->postJson('/api/v1/sessions/sync', ['sessions' => [$payload]]);

        $this->assertFalse($first->json('results.0.duplicate'));
        $this->assertTrue($second->json('results.0.duplicate'));
        $this->assertSame($first->json('results.0.session_id'), $second->json('results.0.session_id'));
        $this->assertSame(1, WorkoutSession::count());
        $this->assertSame(
            $first->json('user_totals.xp_total'),
            $second->json('user_totals.xp_total'),
            'განმეორებითი გაგზავნა XP-ს არ ამრავლებს',
        );
    }

    public function test_a_session_shorter_than_three_minutes_is_rejected(): void
    {
        Sanctum::actingAs($this->makeAthlete());

        $response = $this->postJson('/api/v1/sessions/sync', ['sessions' => [$this->payload([
            'started_at' => now()->subMinute()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
        ])]]);

        $this->assertSame('rejected', $response->json('results.0.status'));
        $this->assertSame(0, $response->json('results.0.xp_awarded'));
    }

    public function test_clock_skew_drops_the_session_to_tier_zero(): void
    {
        Sanctum::actingAs($this->makeAthlete());

        $response = $this->postJson('/api/v1/sessions/sync', ['sessions' => [$this->payload([
            'device_clock_offset_ms' => 20 * 60_000,   // 20 წუთი
        ])]]);

        $this->assertSame(0, $response->json('results.0.verification_tier'));
        $this->assertContains('FLAG_CLOCK_SKEW', $response->json('results.0.flags'));
    }

    public function test_impossible_rep_rate_and_no_rest_flag_the_session(): void
    {
        Sanctum::actingAs($this->makeAthlete());
        $pushUp = Exercise::where('slug', 'push-up')->value('id');
        $squat = Exercise::where('slug', 'squat')->value('id');

        $sets = [];
        foreach (range(1, 4) as $i) {
            $sets[] = [
                'exercise_id' => $i % 2 ? $pushUp : $squat,
                'set_no' => $i,
                'reps' => 50,
                // 50 გამეორება 5 წამში = 0.1 წმ/გამეორება
                'started_at' => now()->subMinutes(20 - $i)->toIso8601String(),
                'completed_at' => now()->subMinutes(20 - $i)->addSeconds(5)->toIso8601String(),
                'rest_after_ms' => 1000,
            ];
        }

        $response = $this->postJson('/api/v1/sessions/sync', ['sessions' => [
            $this->payload() + [],
        ]]);
        $response = $this->postJson('/api/v1/sessions/sync', ['sessions' => [
            array_replace($this->payload(), ['sets' => $sets]),
        ]]);

        $flags = $response->json('results.1.flags') ?? $response->json('results.0.flags');

        $this->assertContains('FLAG_IMPOSSIBLE_RATE', $flags);
        $this->assertContains('FLAG_NO_REST', $flags);
        $this->assertSame('flagged', $response->json('results.0.status'));
    }

    public function test_a_flagged_session_counts_personally_but_not_for_the_league(): void
    {
        $user = $this->makeAthlete();
        Sanctum::actingAs($user);
        $pushUp = Exercise::where('slug', 'push-up')->value('id');

        $sets = [];
        foreach (range(1, 4) as $i) {
            $sets[] = [
                'exercise_id' => $pushUp,
                'set_no' => $i,
                'reps' => 50,
                'started_at' => now()->subMinutes(20 - $i)->toIso8601String(),
                'completed_at' => now()->subMinutes(20 - $i)->addSeconds(5)->toIso8601String(),
                'rest_after_ms' => 1000,
            ];
        }
        // მეორე სავარჯიშო, რომ „too thin" ფილტრში არ ჩავარდეს
        $sets[] = [
            'exercise_id' => Exercise::where('slug', 'squat')->value('id'),
            'set_no' => 5, 'reps' => 20,
            'started_at' => now()->subMinutes(15)->toIso8601String(),
            'completed_at' => now()->subMinutes(15)->addSeconds(45)->toIso8601String(),
            'rest_after_ms' => 60_000,
        ];

        $this->postJson('/api/v1/sessions/sync', ['sessions' => [
            array_replace($this->payload(), ['sets' => $sets]),
        ]])->assertOk();

        $this->assertGreaterThan(0, XpLedger::where('user_id', $user->id)->sum('xp'));
        $this->assertSame(0, (int) XpLedger::where('user_id', $user->id)->sum('league_xp'));
    }

    public function test_it_refuses_batches_larger_than_the_configured_maximum(): void
    {
        Sanctum::actingAs($this->makeAthlete());

        $sessions = array_map(fn () => $this->payload(), range(1, 25));

        $this->postJson('/api/v1/sessions/sync', ['sessions' => $sessions])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sessions');
    }
}
