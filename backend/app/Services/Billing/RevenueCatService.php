<?php

namespace App\Services\Billing;

use App\Models\BillingEvent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * RevenueCat → `subscriptions`.
 *
 * კლიენტი (react-native-purchases) `Purchases.logIn(String(user.id))`-ით
 * შედის, ამიტომ `app_user_id` ჩვენი users.id-ია. premium სტატუსი
 * მხოლოდ აქედან ჩაიწერება — მობაილის „ყიდვა წარმატებულია" არაფერს ცვლის.
 */
class RevenueCatService
{
    private const GRANTING = [
        'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE',
        'NON_RENEWING_PURCHASE', 'SUBSCRIPTION_EXTENDED', 'TEMPORARY_ENTITLEMENT_GRANT',
        'REFUND_REVERSED',
    ];

    public function webhookConfigured(): bool
    {
        return filled(config('services.revenuecat.webhook_auth'));
    }

    public function apiConfigured(): bool
    {
        return filled(config('services.revenuecat.secret_key'));
    }

    public function entitlement(): string
    {
        return config('services.revenuecat.entitlement', Subscription::PREMIUM);
    }

    /** @return bool false — event.id უკვე დამუშავებულია */
    public function handleWebhook(array $payload): bool
    {
        $event = $payload['event'] ?? [];
        $user = $this->resolveUser($event);

        // ON CONFLICT DO NOTHING — catch-ი Postgres-ის ტრანზაქციას „წამლავს"
        $inserted = BillingEvent::query()->insertOrIgnore([
            'provider' => 'revenuecat',
            'event_id' => (string) $event['id'],
            'type' => (string) $event['type'],
            'app_user_id' => $event['app_user_id'] ?? null,
            'user_id' => $user?->id,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        match ($event['type']) {
            'TEST', 'SUBSCRIBER_ALIAS', 'INVOICE_ISSUANCE', 'VIRTUAL_CURRENCY_TRANSACTION' => null,
            'TRANSFER' => $this->handleTransfer($event),
            default => $user ? $this->applyEvent($user, $event) : null,
        };

        return true;
    }

    public function applyEvent(User $user, array $event): void
    {
        $entitlements = $event['entitlement_ids'] ?? null;
        if (is_array($entitlements) && ! in_array($this->entitlement(), $entitlements, true)) {
            return;
        }

        $status = match (true) {
            in_array($event['type'], self::GRANTING, true) => 'active',
            $event['type'] === 'CANCELLATION' => 'cancelled',
            $event['type'] === 'BILLING_ISSUE' => 'billing_issue',
            $event['type'] === 'EXPIRATION' => 'expired',
            default => null,
        };

        if (! $status) {
            return;
        }

        $eventMs = (int) ($event['event_timestamp_ms'] ?? now()->getTimestampMs());

        DB::transaction(function () use ($user, $event, $status, $eventMs) {
            $sub = Subscription::lockForUpdate()->firstOrNew([
                'user_id' => $user->id,
                'provider' => 'revenuecat',
                'entitlement' => $this->entitlement(),
            ]);

            // webhook-ები თანმიმდევრობის გარანტიის გარეშე მოდის — ძველი event ახალს არ გადაწერს
            if ($sub->exists && $sub->last_event_ms && $sub->last_event_ms > $eventMs) {
                return;
            }

            $expiresMs = $event['expiration_at_ms'] ?? null;

            $sub->fill([
                'status' => $status,
                'product_id' => $event['product_id'] ?? $sub->product_id,
                'store' => isset($event['store']) ? strtolower($event['store']) : $sub->store,
                'period_type' => isset($event['period_type']) ? strtolower($event['period_type']) : $sub->period_type,
                'environment' => $event['environment'] ?? $sub->environment,
                'expires_at' => $expiresMs ? Carbon::createFromTimestampMs($expiresMs) : ($status === 'expired' ? now() : null),
                'last_event_ms' => $eventMs,
            ])->save();
        });
    }

    /**
     * RevenueCat REST-იდან ჭეშმარიტი მდგომარეობა — ყიდვის შემდეგ კლიენტი
     * ამას იძახის, რომ webhook-ის დაგვიანებით premium „არ დაიგვიანოს".
     */
    public function syncFromApi(User $user): void
    {
        if (! $this->apiConfigured()) {
            return;
        }

        $response = Http::withToken(config('services.revenuecat.secret_key'))
            ->acceptJson()
            ->timeout(10)
            ->get('https://api.revenuecat.com/v1/subscribers/'.rawurlencode((string) $user->id));

        if (! $response->successful()) {
            return;
        }

        $subscriber = $response->json('subscriber', []);
        $ent = $subscriber['entitlements'][$this->entitlement()] ?? null;
        $sub = Subscription::firstOrNew([
            'user_id' => $user->id,
            'provider' => 'revenuecat',
            'entitlement' => $this->entitlement(),
        ]);

        if (! $ent) {
            if ($sub->exists) {
                $sub->update(['status' => 'expired', 'expires_at' => $sub->expires_at ?? now()]);
            }

            return;
        }

        $expires = $ent['expires_date'] ? Carbon::parse($ent['expires_date']) : null;
        $product = $subscriber['subscriptions'][$ent['product_identifier']] ?? [];

        $status = match (true) {
            $expires !== null && $expires->isPast() => 'expired',
            ! empty($product['billing_issues_detected_at']) => 'billing_issue',
            ! empty($product['unsubscribe_detected_at']) => 'cancelled',
            default => 'active',
        };

        $sub->fill([
            'status' => $status,
            'product_id' => $ent['product_identifier'],
            'store' => $product['store'] ?? $sub->store,
            'period_type' => $product['period_type'] ?? $sub->period_type,
            'environment' => isset($product['is_sandbox']) ? ($product['is_sandbox'] ? 'SANDBOX' : 'PRODUCTION') : $sub->environment,
            'expires_at' => $expires,
            'last_event_ms' => now()->getTimestampMs(),
        ])->save();
    }

    private function handleTransfer(array $event): void
    {
        foreach ($event['transferred_from'] ?? [] as $id) {
            if ($from = $this->userFromAppId($id)) {
                Subscription::where('user_id', $from->id)
                    ->where('provider', 'revenuecat')
                    ->update(['status' => 'expired', 'expires_at' => now()]);
            }
        }

        foreach ($event['transferred_to'] ?? [] as $id) {
            if ($to = $this->userFromAppId($id)) {
                $this->syncFromApi($to);
            }
        }
    }

    private function resolveUser(array $event): ?User
    {
        $ids = array_filter([
            $event['app_user_id'] ?? null,
            $event['original_app_user_id'] ?? null,
            ...($event['aliases'] ?? []),
        ]);

        foreach ($ids as $id) {
            if ($user = $this->userFromAppId($id)) {
                return $user;
            }
        }

        return null;
    }

    /** ანონიმური `$RCAnonymousID:…` ჩვენს ბაზაში არ ცხოვრობს */
    private function userFromAppId(string $id): ?User
    {
        return ctype_digit($id) ? User::find((int) $id) : null;
    }
}
