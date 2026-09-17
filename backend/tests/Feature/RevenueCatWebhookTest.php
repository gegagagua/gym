<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RevenueCatWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.revenuecat.webhook_auth' => 'rc-secret', 'services.revenuecat.secret_key' => null]);
    }

    private function event(User $user, string $type, array $overrides = []): array
    {
        return ['api_version' => '1.0', 'event' => $overrides + [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'app_user_id' => (string) $user->id,
            'original_app_user_id' => '$RCAnonymousID:abc',
            'aliases' => ['$RCAnonymousID:abc', (string) $user->id],
            'product_id' => 'kalisteni_premium_monthly',
            'entitlement_ids' => ['premium'],
            'period_type' => 'NORMAL',
            'store' => 'APP_STORE',
            'environment' => 'SANDBOX',
            'event_timestamp_ms' => now()->getTimestampMs(),
            'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
        ]];
    }

    private function send(array $payload, string $auth = 'rc-secret')
    {
        return $this->postJson('/api/v1/webhooks/revenuecat', $payload, ['Authorization' => $auth]);
    }

    public function test_it_rejects_a_wrong_secret(): void
    {
        $this->send($this->event($this->makeAthlete(), 'INITIAL_PURCHASE'), 'nope')->assertUnauthorized();
        $this->assertSame(0, Subscription::count());
    }

    public function test_it_is_unavailable_until_configured(): void
    {
        config(['services.revenuecat.webhook_auth' => null]);

        $this->send($this->event($this->makeAthlete(), 'INITIAL_PURCHASE'))->assertStatus(503);
    }

    public function test_a_purchase_grants_premium_and_replays_are_ignored(): void
    {
        $user = $this->makeAthlete();
        $payload = $this->event($user, 'INITIAL_PURCHASE');

        $first = $this->send($payload)->json();
        $second = $this->send($payload)->json();
        $this->assertSame(['ok' => true, 'duplicate' => false], $first, json_encode($first));
        $this->assertSame(['ok' => true, 'duplicate' => true], $second, json_encode($second));

        Sanctum::actingAs($user);
        $sub = $this->getJson('/api/v1/me/subscription')->json();
        $this->assertTrue($sub['is_premium'] ?? null, json_encode($sub));
        $this->assertTrue($sub['will_renew']);
        $this->assertSame('kalisteni_premium_monthly', $sub['product_id']);

        $me = $this->getJson('/api/v1/me')->json();
        $this->assertTrue($me['data']['subscription']['is_premium'] ?? null, json_encode($me));
    }

    public function test_cancellation_keeps_access_until_expiry_and_expiration_removes_it(): void
    {
        $user = $this->makeAthlete();
        $this->send($this->event($user, 'INITIAL_PURCHASE'));

        $this->send($this->event($user, 'CANCELLATION', ['event_timestamp_ms' => now()->addSecond()->getTimestampMs()]));
        $this->assertTrue($user->fresh()->hasPremium());
        $this->assertSame('cancelled', Subscription::first()->status);

        $this->send($this->event($user, 'EXPIRATION', [
            'event_timestamp_ms' => now()->addSeconds(2)->getTimestampMs(),
            'expiration_at_ms' => now()->subSecond()->getTimestampMs(),
        ]));
        $this->assertFalse($user->fresh()->hasPremium());
    }

    public function test_an_out_of_order_older_event_does_not_overwrite_a_newer_one(): void
    {
        $user = $this->makeAthlete();

        $this->send($this->event($user, 'EXPIRATION', [
            'event_timestamp_ms' => now()->getTimestampMs(),
            'expiration_at_ms' => now()->subMinute()->getTimestampMs(),
        ]));
        $this->send($this->event($user, 'RENEWAL', ['event_timestamp_ms' => now()->subHour()->getTimestampMs()]));

        $this->assertFalse($user->fresh()->hasPremium());
    }

    public function test_other_entitlements_and_anonymous_users_are_ignored(): void
    {
        $user = $this->makeAthlete();

        $this->send($this->event($user, 'INITIAL_PURCHASE', ['entitlement_ids' => ['coins']]))->assertOk();
        $this->send($this->event($user, 'INITIAL_PURCHASE', [
            'app_user_id' => '$RCAnonymousID:zzz', 'original_app_user_id' => '$RCAnonymousID:zzz', 'aliases' => [],
        ]))->assertOk();

        $this->assertSame(0, Subscription::count());
    }

    public function test_sync_reads_the_entitlement_from_the_revenuecat_api(): void
    {
        config(['services.revenuecat.secret_key' => 'sk_test']);
        $user = $this->makeAthlete();

        Http::fake(['api.revenuecat.com/*' => Http::response(['subscriber' => [
            'entitlements' => ['premium' => [
                'expires_date' => now()->addDays(20)->toIso8601String(),
                'product_identifier' => 'kalisteni_premium_monthly',
            ]],
            'subscriptions' => ['kalisteni_premium_monthly' => [
                'store' => 'play_store', 'period_type' => 'normal', 'is_sandbox' => true,
                'unsubscribe_detected_at' => null, 'billing_issues_detected_at' => null,
            ]],
        ]])]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/subscription/sync')->assertOk()->assertJsonPath('is_premium', true);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), "/v1/subscribers/{$user->id}")
            && $r->hasHeader('Authorization', 'Bearer sk_test'));
    }
}
