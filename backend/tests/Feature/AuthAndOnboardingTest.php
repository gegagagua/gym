<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Database\Seeders\ProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_gets_a_token_a_profile_and_a_streak(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', ['device_uuid' => 'device-123'])
            ->assertCreated();

        $user = User::where('device_uuid', 'device-123')->firstOrFail();

        $this->assertNotEmpty($response->json('token'));
        $this->assertTrue($response->json('user.is_guest'));
        $this->assertNotNull($user->profile);
        $this->assertNotNull($user->streak);
    }

    public function test_the_same_device_does_not_create_a_second_guest(): void
    {
        $this->postJson('/api/v1/auth/guest', ['device_uuid' => 'device-123']);
        $this->postJson('/api/v1/auth/guest', ['device_uuid' => 'device-123']);

        $this->assertSame(1, User::count());
    }

    public function test_the_otp_flow_signs_a_user_in(): void
    {
        $issued = $this->postJson('/api/v1/auth/otp/request', ['phone' => '+995555123456'])->assertOk();
        $code = $issued->json('debug_code');

        $this->assertNotNull($code, 'ლოკალურ გარემოში კოდი პასუხშია');

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+995555123456', 'code' => $code])
            ->assertCreated()
            ->assertJsonPath('user.is_guest', false);
    }

    public function test_a_wrong_otp_is_refused(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+995555123456']);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+995555123456', 'code' => '000000'])
            ->assertStatus(422);
    }

    public function test_otp_requests_are_limited_to_three_per_hour_per_number(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => '+995555111222'])->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+995555111222'])->assertStatus(429);
    }

    public function test_a_guest_can_be_upgraded_without_losing_their_data(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest', ['device_uuid' => 'device-abc']);
        $user = User::where('device_uuid', 'device-abc')->firstOrFail();
        Sanctum::actingAs($user);

        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '+995599000111'])->json('debug_code');

        $this->postJson('/api/v1/auth/upgrade', ['phone' => '+995599000111', 'code' => $code])->assertOk();

        $this->assertSame(1, User::count(), 'ახალი ანგარიში არ იქმნება');
        $this->assertSame('phone', $user->fresh()->provider);
    }

    public function test_under_sixteen_disables_social_features(): void
    {
        Sanctum::actingAs($user = $this->makeAthlete());

        $this->patchJson('/api/v1/me/profile', ['birth_year' => (int) date('Y') - 14])->assertOk();

        $this->assertFalse($user->fresh()->social_enabled);
    }

    public function test_body_metrics_are_private_to_the_owner(): void
    {
        $user = $this->makeAthlete();
        Profile::where('user_id', $user->id)->update(['weight_kg' => 82, 'height_cm' => 181]);

        Sanctum::actingAs($user);
        $mine = $this->getJson('/api/v1/me')->assertOk();

        $this->assertSame(82.0, (float) $mine->json('data.profile.weight_kg'));

        // სხვისი პროფილი — ლიდერბორდის რესურსი წონას არასდროს არ ატარებს
        $other = $this->makeAthlete();
        Sanctum::actingAs($other);
        $theirs = $this->getJson('/api/v1/me')->assertOk();

        $this->assertNotSame(82.0, (float) $theirs->json('data.profile.weight_kg'));
    }

    public function test_account_deletion_uses_a_thirty_day_grace_period(): void
    {
        Sanctum::actingAs($user = $this->makeAthlete());

        $this->deleteJson('/api/v1/account')->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNotNull(User::withTrashed()->find($user->id)->purge_after);
    }

    public function test_the_level_test_recommends_a_program(): void
    {
        $this->seed(ExerciseSeeder::class);
        $this->seed(ProgramSeeder::class);

        Sanctum::actingAs($user = $this->makeAthlete());
        $this->patchJson('/api/v1/me/profile', ['equipment' => ['yard'], 'goal' => 'strength']);

        $response = $this->postJson('/api/v1/me/level-test', [
            'pushup' => 22, 'pullup' => 6, 'plank_sec' => 75,
        ])->assertOk();

        $this->assertSame(3, $response->json('level'));
        $this->assertSame('bar', $response->json('recommended_program.track'));
    }
}
