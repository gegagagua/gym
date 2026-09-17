<?php

namespace App\Http\Resources;

use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => Locale::pick($this->description, app()->getLocale()),
            'lat' => isset($this->lat) ? (float) $this->lat : null,
            'lng' => isset($this->lng) ? (float) $this->lng : null,
            'city_id' => $this->city_id,
            'type' => $this->type,
            'access' => $this->access,
            'condition_rating' => $this->averageRating(),
            'has_lighting' => (bool) $this->has_lighting,
            'status' => $this->status,
            // დეტალ-ბარათისთვის; nearby სია ამ ველებს განზრახ არ აბრუნებს
            'source' => $this->source,
            'address' => $this->address,
            'phone' => $this->phone,
            'website' => $this->website,
            'opening_hours' => $this->opening_hours,
            'checkin_count' => (int) $this->checkin_count,
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment->pluck('equipment_tag')),
            'photos' => $this->whenLoaded('media', fn () => $this->media
                ->where('status', 'approved')
                ->sortByDesc('is_primary')
                ->values()
                ->map(fn ($m) => ['url' => $m->url, 'thumb_url' => $m->thumb_url])),
            'trainers' => $this->whenLoaded('trainers', fn () => $this->trainers->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'photo_url' => $t->photo_url,
                'bio' => Locale::pick($t->bio, app()->getLocale()),
                'is_verified' => (bool) $t->is_verified,
                'listing_tier' => $t->listing_tier,
                // free ტარიფზე კონტაქტები არ ჩანს — ეს არის v1-ის მონეტიზაცია
                'contacts' => $t->showsContacts() ? [
                    'instagram' => $t->contact_instagram,
                    'phone' => $t->contact_phone,
                    'telegram' => $t->contact_telegram,
                ] : null,
            ])),
        ];
    }
}
