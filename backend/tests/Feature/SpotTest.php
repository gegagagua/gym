<?php

namespace Tests\Feature;

use App\Models\Spot;
use App\Models\SpotEquipment;
use App\Models\XpLedger;
use App\Services\SpotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpotTest extends TestCase
{
    use RefreshDatabase;

    private function vakePark(array $overrides = []): Spot
    {
        // $overrides მარცხნივ — `+` მარცხენა მხარეს ანიჭებს პრიორიტეტს
        return app(SpotService::class)->create($overrides + [
            'name' => 'ვაკის პარკი',
            'type' => 'park',
            'status' => 'verified',
        ], 41.7089, 44.7573);
    }

    public function test_nearby_returns_distance_in_metres_and_respects_the_radius(): void
    {
        $this->vakePark();
        app(SpotService::class)->create(['name' => 'გლდანი', 'type' => 'yard', 'status' => 'verified'], 41.7981, 44.8127);

        $near = app(SpotService::class)->nearby(41.7089, 44.7573, 1000);
        $far = app(SpotService::class)->nearby(41.7089, 44.7573, 20000);

        $this->assertCount(1, $near);
        $this->assertCount(2, $far);
        $this->assertSame(0, $near->first()->distance_m);
        // გლდანი ვაკედან ~11 კმ-ია
        $this->assertGreaterThan(9000, $far->last()->distance_m);
    }

    public function test_pending_spots_stay_off_the_map(): void
    {
        $this->vakePark(['status' => 'pending']);

        $this->assertCount(0, app(SpotService::class)->nearby(41.7089, 44.7573, 1000));
    }

    public function test_check_in_requires_being_within_a_hundred_metres(): void
    {
        $user = $this->makeAthlete();
        $spot = $this->vakePark();

        $close = app(SpotService::class)->checkIn($user, $spot, 41.70895, 44.75735);
        $this->assertTrue($close['ok']);

        // ~600 მეტრით მოშორებით
        $far = app(SpotService::class)->checkIn($user, $spot, 41.7143, 44.7573);
        $this->assertFalse($far['ok']);
        $this->assertGreaterThan(100, $far['distance_m']);
    }

    public function test_repeat_check_in_reuses_the_active_one(): void
    {
        $user = $this->makeAthlete();
        $spot = $this->vakePark();

        $first = app(SpotService::class)->checkIn($user, $spot, 41.7089, 44.7573)['checkin'];
        $second = app(SpotService::class)->checkIn($user, $spot, 41.7089, 44.7573)['checkin'];

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $spot->fresh()->checkin_count);
    }

    public function test_a_duplicate_within_fifty_metres_is_detected(): void
    {
        $this->vakePark();

        $this->assertNotNull(app(SpotService::class)->findDuplicate(41.70895, 44.75735));
        $this->assertNull(app(SpotService::class)->findDuplicate(41.7143, 44.7573));
    }

    public function test_submitting_a_duplicate_spot_returns_a_conflict(): void
    {
        Sanctum::actingAs($this->makeAthlete());
        $this->vakePark();

        $this->postJson('/api/v1/spots', [
            'name' => 'ვაკე — იგივე ადგილი',
            'lat' => 41.70893,
            'lng' => 44.75731,
            'type' => 'park',
            'equipment' => ['pull_up_bar'],
        ])->assertStatus(409)->assertJsonPath('error', 'duplicate');
    }

    public function test_verifying_a_user_submitted_spot_awards_a_hundred_xp_once(): void
    {
        $author = $this->makeAthlete();
        $moderator = $this->makeAthlete();

        $spot = $this->vakePark(['status' => 'pending', 'created_by_user_id' => $author->id]);

        app(SpotService::class)->verify($spot, $moderator);
        app(SpotService::class)->verify($spot->fresh(), $moderator);

        $this->assertSame(100, (int) XpLedger::where('user_id', $author->id)->where('reason', 'spot_added')->sum('xp'));
    }

    public function test_equipment_filter_requires_every_requested_tag(): void
    {
        $full = $this->vakePark(['name' => 'სრული']);
        $bare = app(SpotService::class)->create(['name' => 'მხოლოდ ტურნიკი', 'type' => 'yard', 'status' => 'verified'], 41.7090, 44.7574);

        foreach (['pull_up_bar', 'parallel_bars'] as $tag) {
            SpotEquipment::create(['spot_id' => $full->id, 'equipment_tag' => $tag]);
        }
        SpotEquipment::create(['spot_id' => $bare->id, 'equipment_tag' => 'pull_up_bar']);

        $both = app(SpotService::class)->nearby(41.7089, 44.7573, 1000, ['pull_up_bar', 'parallel_bars']);

        $this->assertCount(1, $both);
        $this->assertSame($full->id, $both->first()->id);
    }
}
