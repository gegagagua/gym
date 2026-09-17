<?php

namespace App\Http\Resources;

use App\Http\Controllers\Api\V1\BillingController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'is_guest' => $this->isGuest(),
            'social_enabled' => (bool) $this->social_enabled,
            'subscription' => $this->when(
                $request->user()?->id === $this->id,
                fn () => BillingController::describe($this->resource),
            ),
            'phone' => $this->when($request->user()?->id === $this->id, $this->phone),
            'email' => $this->when($request->user()?->id === $this->id, $this->email),
            'profile' => $profile ? [
                'level' => $profile->level,
                'goal' => $profile->goal,
                'equipment' => $profile->equipment ?? [],
                'city_id' => $profile->city_id,
                'is_public' => (bool) $profile->is_public,
                'birth_year' => $profile->birth_year,
                'gender' => $profile->gender,
                // სხეულის მეტრიკები მხოლოდ მფლობელს უბრუნდება (სპეც. 17)
                'height_cm' => $this->when($request->user()?->id === $this->id, $profile->height_cm),
                'weight_kg' => $this->when($request->user()?->id === $this->id, $profile->weight_kg),
                'level_test' => $this->when($request->user()?->id === $this->id, $profile->level_test),
            ] : null,
        ];
    }
}
