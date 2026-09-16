<?php

namespace Tests\Feature;

use App\Jobs\RenderShareCard;
use App\Models\ShareCard;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShareCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_rendered_card_lands_on_a_disk_that_can_serve_it(): void
    {
        Storage::fake('public');

        $user = $this->makeAthlete();

        $card = ShareCard::create([
            'user_id' => $user->id,
            'type' => 'streak',
            'status' => 'queued',
            'expires_at' => now()->addDays(7),
        ]);

        (new RenderShareCard($card->id))->handle(app(StatsService::class));

        $card->refresh();

        $this->assertSame('ready', $card->status);
        $this->assertNotNull($card->url);
        Storage::disk('public')->assertExists("share/{$card->id}.jpg");

        // კლიენტი სტატუსს ეკითხება, სანამ ბარათს აჩვენებს
        $this->actingAs($user)
            ->getJson("/api/v1/share/card/{$card->id}")
            ->assertOk()
            ->assertJsonPath('card.status', 'ready');
    }

    public function test_another_athletes_card_is_not_readable(): void
    {
        $owner = $this->makeAthlete();
        $stranger = $this->makeAthlete();

        $card = ShareCard::create(['user_id' => $owner->id, 'type' => 'session', 'status' => 'ready']);

        $this->actingAs($stranger)->getJson("/api/v1/share/card/{$card->id}")->assertForbidden();
    }
}
