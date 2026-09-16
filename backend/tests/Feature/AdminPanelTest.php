<?php

namespace Tests\Feature;

use App\Filament\Resources\Badges\BadgeResource;
use App\Filament\Resources\Exercises\ExerciseResource;
use App\Filament\Resources\FeatureFlags\FeatureFlagResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\Spots\SpotResource;
use App\Filament\Resources\Trainers\TrainerResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Exercise;
use App\Models\Spot;
use App\Models\User;
use App\Services\SpotService;
use Database\Seeders\ExerciseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = $this->makeAthlete();
        $user->update(['is_admin' => true, 'email' => 'admin@test.ge']);

        return $user->fresh();
    }

    public function test_a_normal_athlete_cannot_reach_the_panel(): void
    {
        $this->assertFalse($this->makeAthlete()->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($this->admin()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_moderators_can_reach_the_panel(): void
    {
        $moderator = $this->makeAthlete();
        $moderator->update(['is_moderator' => true]);

        $this->assertTrue($moderator->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_every_resource_list_page_renders(): void
    {
        $this->seed(ExerciseSeeder::class);
        $this->actingAs($this->admin());

        foreach ([
            ExerciseResource::class,
            ProgramResource::class,
            SpotResource::class,
            TrainerResource::class,
            BadgeResource::class,
            FeatureFlagResource::class,
            UserResource::class,
        ] as $resource) {
            $this->get($resource::getUrl('index'))
                ->assertSuccessful();
        }
    }

    public function test_the_exercise_edit_page_renders_with_translations(): void
    {
        $this->seed(ExerciseSeeder::class);
        $this->actingAs($this->admin());

        $exercise = Exercise::where('slug', 'pull-up')->firstOrFail();

        $this->get(ExerciseResource::getUrl('edit', ['record' => $exercise]))
            ->assertSuccessful()
            ->assertSee('difficulty_coef', escape: false);
    }

    public function test_the_spot_edit_page_exposes_coordinates(): void
    {
        $this->actingAs($this->admin());

        /** @var Spot $spot */
        $spot = app(SpotService::class)->create(
            ['name' => 'ვაკის პარკი', 'type' => 'park', 'status' => 'pending'],
            41.7089,
            44.7573,
        );

        $this->get(SpotResource::getUrl('edit', ['record' => $spot]))->assertSuccessful();
    }
}
