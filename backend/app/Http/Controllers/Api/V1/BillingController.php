<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\RevenueCatService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly RevenueCatService $revenueCat) {}

    /** RevenueCat → Authorization ჰედერი დაშბორდში მითითებული საიდუმლოთია */
    public function revenueCatWebhook(Request $request)
    {
        abort_unless($this->revenueCat->webhookConfigured(), 503, 'Billing webhook is not configured.');

        $expected = (string) config('services.revenuecat.webhook_auth');
        $given = (string) $request->header('Authorization');
        $given = str_starts_with($given, 'Bearer ') ? substr($given, 7) : $given;

        abort_unless(hash_equals($expected, $given), 401);

        $request->validate([
            'event' => ['required', 'array'],
            'event.id' => ['required', 'string'],
            'event.type' => ['required', 'string'],
        ]);

        $fresh = $this->revenueCat->handleWebhook($request->all());

        return response()->json(['ok' => true, 'duplicate' => ! $fresh]);
    }

    public function show(Request $request)
    {
        return response()->json(self::describe($request->user()));
    }

    /** ყიდვის/აღდგენის შემდეგ — webhook-ს ნუ ელი, RevenueCat-ს პირდაპირ ჰკითხე */
    public function sync(Request $request)
    {
        $this->revenueCat->syncFromApi($request->user());

        return response()->json(self::describe($request->user()));
    }

    public static function describe(User $user): array
    {
        $sub = $user->premiumSubscription();

        return [
            'is_premium' => $sub !== null,
            'status' => $sub?->status,
            'provider' => $sub?->provider,
            'product_id' => $sub?->product_id,
            'expires_at' => $sub?->expires_at?->toIso8601String(),
            'will_renew' => $sub ? $sub->status === 'active' && $sub->provider === 'revenuecat' : false,
        ];
    }
}
