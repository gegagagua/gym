<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Subscription;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ყოველ ზონაზე სამი ლოკაციის ვარიანტი — პლანერს შერჩევა უნდა ჰქონდეს
        $variants = [
            'home' => [[], 'push'],
            'yard' => [['pull_up_bar'], 'pull'],
            'gym' => [['barbell', 'bench'], 'push'],
            'gym2' => [['dumbbell'], 'pull'],
        ];

        foreach (['chest', 'back', 'shoulders', 'arms', 'core', 'legs'] as $zone) {
            foreach ($variants as $loc => [$equipment, $force]) {
                foreach ([1, 3] as $levelMin) {
                    Exercise::create([
                        'slug' => "{$zone}-{$loc}-{$levelMin}",
                        'category' => $loc === 'home' ? 'push' : 'gym',
                        'zone' => $zone,
                        'force' => $force,
                        'mechanic' => 'compound',
                        'unit' => $zone === 'core' && $loc === 'home' ? 'seconds' : 'reps',
                        'difficulty_coef' => 1,
                        'level_min' => $levelMin,
                        'level_max' => 5,
                        'equipment' => $equipment,
                        'primary_muscles' => [$zone],
                    ]);
                }
            }
        }
    }

    private function premiumAthlete(array $profile = []): User
    {
        $user = $this->makeAthlete($profile);
        Subscription::create([
            'user_id' => $user->id,
            'provider' => 'manual',
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        return $user;
    }

    private function schedule(): array
    {
        return [
            ['weekday' => 1, 'location' => 'gym'],
            ['weekday' => 3, 'location' => 'home'],
            ['weekday' => 5, 'location' => 'yard'],
        ];
    }

    public function test_the_planner_is_behind_the_paywall(): void
    {
        Sanctum::actingAs($this->makeAthlete());

        $this->postJson('/api/v1/me/plan', ['schedule' => $this->schedule(), 'intensity' => 'moderate'])
            ->assertStatus(402)
            ->assertJsonPath('code', 'premium_required');

        $this->getJson('/api/v1/me/plan')->assertStatus(402);
    }

    public function test_an_expired_subscription_does_not_unlock_the_planner(): void
    {
        $user = $this->makeAthlete();
        Subscription::create([
            'user_id' => $user->id, 'provider' => 'revenuecat', 'status' => 'cancelled',
            'expires_at' => now()->subMinute(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/plan', ['schedule' => $this->schedule(), 'intensity' => 'light'])->assertStatus(402);
    }

    public function test_it_generates_a_calendar_matching_days_locations_and_intensity(): void
    {
        Sanctum::actingAs($this->premiumAthlete(['level' => 3]));

        $response = $this->postJson('/api/v1/me/plan', [
            'schedule' => $this->schedule(),
            'intensity' => 'moderate',
            'weeks' => 4,
            'starts_on' => '2026-09-21',   // ორშაბათი
        ])->assertCreated();

        $days = collect($response->json('plan.days'));

        $this->assertCount(28, $days);
        $this->assertSame(12, $days->where('type', 'workout')->count());
        $this->assertSame('2026-09-21', $days->first()['date']);

        $monday = $days->firstWhere('date', '2026-09-21');
        $this->assertSame('gym', $monday['location']);
        $this->assertSame('push', $monday['split']);   // level 3, 3 დღე → push/pull/legs
        $this->assertNotEmpty($monday['exercises']);
        $this->assertLessThanOrEqual(config('kalisteni.session.max_minutes'), $monday['est_minutes']);

        // gym დღეს დარბაზის ინვენტარი, home დღეს — არაფერი
        $gymEquipment = collect($monday['exercises'])
            ->flatMap(fn ($e) => Exercise::find($e['exercise_id'])->equipment)->all();
        $this->assertNotEmpty(array_intersect($gymEquipment, Exercise::GYM_TAGS));

        $wednesday = $days->firstWhere('date', '2026-09-23');
        foreach ($wednesday['exercises'] as $e) {
            $this->assertSame([], Exercise::find($e['exercise_id'])->equipment);
        }

        // push დღეს მკლავების სავარჯიშო — მხოლოდ push
        foreach ($monday['exercises'] as $e) {
            if ($e['exercise']['zone'] === 'arms') {
                $this->assertSame('push', $e['exercise']['force']);
            }
        }

        $this->assertSame('rest', $days->firstWhere('date', '2026-09-22')['type']);

        // ბოლო კვირა deload — ნაკლები სეტი
        $lastMonday = $days->firstWhere('date', '2026-10-12');
        $this->assertTrue($lastMonday['is_deload']);
        $this->assertSame(3, $monday['exercises'][0]['sets']);
        $this->assertSame(2, $lastMonday['exercises'][0]['sets']);
    }

    public function test_intensity_changes_volume(): void
    {
        $light = $this->premiumAthlete(['level' => 3]);
        $intense = $this->premiumAthlete(['level' => 3]);
        $body = fn ($i) => ['schedule' => [['weekday' => 2, 'location' => 'gym']], 'intensity' => $i, 'weeks' => 1, 'starts_on' => '2026-09-22'];

        Sanctum::actingAs($light);
        $l = $this->postJson('/api/v1/me/plan', $body('light'))->json('plan.days.0');
        Sanctum::actingAs($intense);
        $h = $this->postJson('/api/v1/me/plan', $body('intense'))->json('plan.days.0');

        $this->assertLessThan(count($h['exercises']), count($l['exercises']));
        $this->assertLessThan($h['exercises'][0]['sets'], $l['exercises'][0]['sets']);
        $this->assertLessThan($h['est_minutes'], $l['est_minutes']);
    }

    public function test_hard_holds_are_capped_regardless_of_level_and_intensity(): void
    {
        Exercise::create([
            'slug' => 'dragon-flag-test', 'category' => 'core', 'zone' => 'core', 'force' => 'static',
            'mechanic' => 'compound', 'unit' => 'seconds', 'difficulty_coef' => 5, 'level_min' => 5, 'level_max' => 5,
            'equipment' => [], 'primary_muscles' => ['core'],
        ]);
        Exercise::where('zone', 'core')->where('slug', '!=', 'dragon-flag-test')->update(['is_active' => false]);

        Sanctum::actingAs($this->premiumAthlete(['level' => 5]));
        $days = collect($this->postJson('/api/v1/me/plan', [
            'schedule' => [['weekday' => 2, 'location' => 'home']], 'intensity' => 'intense', 'weeks' => 2,
        ])->assertCreated()->json('plan.days'))->where('type', 'workout');

        $holds = $days->flatMap(fn ($d) => $d['exercises'])->where('exercise.slug', 'dragon-flag-test');
        $this->assertNotEmpty($holds);
        $this->assertTrue($holds->every(fn ($e) => $e['target_seconds'] <= 15));
    }

    public function test_at_least_one_rest_day_is_required(): void
    {
        Sanctum::actingAs($this->premiumAthlete());

        $all = array_map(fn ($d) => ['weekday' => $d, 'location' => 'home'], range(1, 7));

        $this->postJson('/api/v1/me/plan', ['schedule' => $all, 'intensity' => 'intense'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule');
    }

    public function test_generating_again_archives_the_previous_plan(): void
    {
        $user = $this->premiumAthlete();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/plan', ['schedule' => $this->schedule(), 'intensity' => 'light'])->assertCreated();
        $this->postJson('/api/v1/me/plan', ['schedule' => $this->schedule(), 'intensity' => 'intense'])->assertCreated();

        $this->assertSame(1, TrainingPlan::where('user_id', $user->id)->where('status', 'active')->count());
        $this->assertSame('intense', $this->getJson('/api/v1/me/plan')->json('plan.intensity'));
    }

    public function test_a_synced_session_completes_only_the_owners_plan_day(): void
    {
        $owner = $this->premiumAthlete();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/me/plan', [
            'schedule' => [['weekday' => now('Asia/Tbilisi')->isoWeekday(), 'location' => 'home']],
            'intensity' => 'light',
            'weeks' => 1,
        ])->assertCreated();

        $day = TrainingPlanDay::where('type', 'workout')->firstOrFail();
        [$a, $b] = $day->exercises()->take(2)->pluck('exercise_id')->all();

        $payload = fn () => ['sessions' => [[
            'client_uuid' => (string) Str::uuid(),
            'plan_day_id' => $day->id,
            'source' => 'plan',
            'started_at' => now()->subMinutes(20)->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'sets' => [
                ['exercise_id' => $a, 'set_no' => 1, 'reps' => 10, 'seconds' => 30, 'rest_after_ms' => 60_000],
                ['exercise_id' => $b, 'set_no' => 1, 'reps' => 10, 'seconds' => 30, 'rest_after_ms' => 60_000],
            ],
        ]]];

        // სხვის დღე არ ჩაითვლება
        Sanctum::actingAs($this->makeAthlete());
        $this->postJson('/api/v1/sessions/sync', $payload())->assertOk();
        $this->assertNull($day->fresh()->completed_at);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/sessions/sync', $payload())->assertOk();
        $this->assertNotNull($day->fresh()->completed_at);
        $this->assertSame(1, $this->getJson('/api/v1/me/plan')->json('plan.stats.completed'));
    }

    public function test_cancelling_the_plan_does_not_need_premium(): void
    {
        $user = $this->premiumAthlete();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/plan', ['schedule' => $this->schedule(), 'intensity' => 'light'])->assertCreated();

        Subscription::where('user_id', $user->id)->update(['status' => 'expired']);

        $this->deleteJson('/api/v1/me/plan')->assertOk();
        $this->assertSame(0, TrainingPlan::where('status', 'active')->count());
    }
}
