<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $t = $this->translation($locale);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $t?->name ?? $this->slug,
            'short_desc' => $t?->short_desc,
            'category' => $this->category,
            'zone' => $this->zone,
            'force' => $this->force,
            'mechanic' => $this->mechanic,
            'unit' => $this->unit,
            'difficulty_coef' => (float) $this->difficulty_coef,
            'level_min' => $this->level_min,
            'level_max' => $this->level_max,
            'equipment' => $this->equipment ?? [],
            'primary_muscles' => $this->primary_muscles ?? [],
            'secondary_muscles' => $this->secondary_muscles ?? [],
            'skill_group' => $this->skill_group,
            'is_skill_unlock' => (bool) $this->is_skill_unlock,
            'unlock_bonus_xp' => (int) $this->unlock_bonus_xp,
            'unlock_threshold' => (int) $this->unlock_threshold,
            'progression_from_id' => $this->progression_from_id,
            'progression_to_id' => $this->progression_to_id,
            'instructions' => $t?->instructions ?? [],
            // „ხშირი შეცდომები“ სავალდებულო ბლოკია ყოველ სავარჯიშოზე (სპეც. 18)
            'common_mistakes' => $t?->common_mistakes ?? [],
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'type' => $m->type,
                'url' => $m->url,
                'width' => $m->width,
                'height' => $m->height,
                'duration_ms' => $m->duration_ms,
                'license' => $m->license,
                'attribution_text' => $m->attribution_text,
                'source_url' => $m->source_url,
            ])),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
