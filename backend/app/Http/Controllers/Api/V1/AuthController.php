<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Device;
use App\Models\Profile;
use App\Models\Streak;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ]);

        return response()->json($this->otp->issue($data['phone'], $request->ip()));
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
            'device' => ['sometimes', 'array'],
            'device_uuid' => ['sometimes', 'string', 'max:64'],
        ]);

        if (! $this->otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => __('Invalid or expired code.')]);
        }

        $user = User::withTrashed()->firstOrNew(['phone' => $data['phone']]);
        $isNew = ! $user->exists;

        if ($user->trashed()) {
            // 30-დღიან grace-ში შესვლა აღადგენს ანგარიშს (სპეც. 17)
            $user->restore();
            $user->purge_after = null;
        }

        $user->provider ??= 'phone';
        $user->save();

        $this->bootstrap($user, $request, $data['device_uuid'] ?? null);

        return $this->issueToken($user, $request, $isNew);
    }

    /** Google / Apple. id_token-ის ვერიფიკაცია პროვაიდერის მხარესაა. */
    public function social(Request $request)
    {
        $data = $request->validate([
            'provider' => ['required', 'in:google,apple'],
            'provider_id' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'display_name' => ['nullable', 'string', 'max:64'],
            'device_uuid' => ['sometimes', 'string', 'max:64'],
        ]);

        $user = User::withTrashed()->firstOrNew([
            'provider' => $data['provider'],
            'provider_id' => $data['provider_id'],
        ]);
        $isNew = ! $user->exists;

        if ($user->trashed()) {
            $user->restore();
            $user->purge_after = null;
        }

        $user->email ??= $data['email'] ?? null;
        $user->display_name ??= $data['display_name'] ?? null;
        $user->save();

        $this->bootstrap($user, $request, $data['device_uuid'] ?? null);

        return $this->issueToken($user, $request, $isNew);
    }

    /**
     * სტუმრის რეჟიმი. რეგისტრაცია ონბორდინგის შემდეგ ხდება —
     * მომხმარებელმა ჯერ ღირებულება უნდა დაინახოს (სპეც. 5.1).
     */
    public function guest(Request $request)
    {
        $data = $request->validate(['device_uuid' => ['required', 'string', 'max:64']]);

        $user = User::firstOrCreate(
            ['device_uuid' => $data['device_uuid']],
            ['provider' => 'guest', 'locale' => app()->getLocale()],
        );

        $this->bootstrap($user, $request, $data['device_uuid']);

        return $this->issueToken($user, $request, $user->wasRecentlyCreated);
    }

    /**
     * სტუმრის ანგარიშის მიმაგრება ტელეფონზე — ლოკალური მონაცემები
     * რჩება, ახალი ანგარიში არ იქმნება.
     */
    public function upgradeGuest(Request $request)
    {
        $user = $request->user();

        if (! $user->isGuest()) {
            throw ValidationException::withMessages(['user' => __('Account is already registered.')]);
        }

        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        if (! $this->otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => __('Invalid or expired code.')]);
        }

        if (User::where('phone', $data['phone'])->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => __('This number already has an account.')]);
        }

        $user->update(['phone' => $data['phone'], 'provider' => 'phone']);

        return response()->json(['user' => new UserResource($user->fresh()->load('profile'))]);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();

        return response()->json(['token' => $user->createToken('mobile')->plainTextToken]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /** App Store-ის მოთხოვნა: წაშლა აპიდან, 30 დღიანი grace, შემდეგ hard delete */
    public function destroyAccount(Request $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->forceFill(['purge_after' => now()->addDays(config('kalisteni.auth.delete_grace_days'))])->save();
            $user->delete();
        });

        return response()->json([
            'purge_after' => $user->purge_after->toIso8601String(),
            'message' => __('Account scheduled for deletion. Sign in before this date to restore it.'),
        ]);
    }

    /** პროფილი/streak/მოწყობილობა ყოველთვის უნდა არსებობდეს — ერთ ადგილას */
    private function bootstrap(User $user, Request $request, ?string $deviceUuid): void
    {
        Profile::firstOrCreate(['user_id' => $user->id]);
        Streak::firstOrCreate(['user_id' => $user->id]);

        $device = $request->input('device', []);
        if ($deviceUuid || $device) {
            Device::updateOrCreate(
                ['user_id' => $user->id, 'device_uuid' => $deviceUuid],
                [
                    'push_token' => $device['push_token'] ?? null,
                    'platform' => $device['platform'] ?? 'ios',
                    'app_version' => $device['app_version'] ?? null,
                    'locale' => $device['locale'] ?? $user->locale,
                    'last_seen_at' => now(),
                ],
            );
        }
    }

    private function issueToken(User $user, Request $request, bool $isNew)
    {
        $user->forceFill(['last_active_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'is_new' => $isNew,
            'user' => new UserResource($user->load('profile')),
        ], $isNew ? 201 : 200);
    }
}
