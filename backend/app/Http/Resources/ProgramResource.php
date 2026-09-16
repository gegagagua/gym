<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $t = $this->translation(app()->getLocale());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $t?->title ?? $this->slug,
            'description' => $t?->description,
            'track' => $this->track,
            'level' => $this->level,
            'duration_weeks' => $this->duration_weeks,
            'days_per_week' => $this->days_per_week,
            'goals' => $this->goals ?? [],
            'equipment' => $this->equipment ?? [],
            'is_premium' => (bool) $this->is_premium,
            'days' => $this->whenLoaded('days', fn () => $this->days->map(fn ($d) => [
                'id' => $d->id,
                'week_no' => $d->week_no,
                'day_no' => $d->day_no,
                'type' => $d->type,
                'title_key' => $d->title_key,
                'est_minutes' => $d->est_minutes,
                'exercises' => $d->relationLoaded('exercises')
                    ? $d->exercises->map(fn ($e) => [
                        'id' => $e->id,
                        'exercise_id' => $e->exercise_id,
                        'sort_order' => $e->sort_order,
                        'sets' => $e->sets,
                        'target_reps' => $e->target_reps,
                        'target_seconds' => $e->target_seconds,
                        'rest_seconds' => $e->rest_seconds,
                        'tempo' => $e->tempo,
                    ])
                    : [],
            ])),
        ];
    }
}
