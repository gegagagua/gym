<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanDay;
use App\Services\Plans\PlanGenerator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** კალენდარის პლანერი — premium (middleware `premium`, routes/api.php) */
class PlanController extends Controller
{
    public function __construct(private readonly PlanGenerator $generator) {}

    public function show(Request $request)
    {
        $plan = TrainingPlan::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('id')
            ->with('days.exercises.exercise.translations', 'days.exercises.exercise.media')
            ->first();

        return response()->json(['plan' => $plan ? $this->present($plan) : null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule' => ['required', 'array', 'min:1', 'max:'.PlanGenerator::MAX_TRAINING_DAYS],
            'schedule.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'schedule.*.location' => ['required', Rule::in(TrainingPlan::LOCATIONS)],
            'intensity' => ['required', Rule::in(TrainingPlan::INTENSITIES)],
            'weeks' => ['sometimes', 'integer', 'between:1,12'],
            'starts_on' => ['sometimes', 'date'],
            'level' => ['sometimes', 'integer', 'between:1,5'],
        ]);

        $plan = $this->generator->generate($request->user(), $data);
        $plan->load('days.exercises.exercise.translations', 'days.exercises.exercise.media');

        return response()->json(['plan' => $this->present($plan)], 201);
    }

    /** პლანის გაუქმება premium-ს არ საჭიროებს — ვადაგასული მომხმარებელი ისევ უნდა შეეძლოს */
    public function destroy(Request $request)
    {
        TrainingPlan::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->update(['status' => 'archived']);

        return response()->json(['plan' => null]);
    }

    private function present(TrainingPlan $plan): array
    {
        $locale = app()->getLocale();
        $today = now($plan->user?->timezone ?: config('kalisteni.league.timezone'))->toDateString();

        return [
            'id' => $plan->id,
            'intensity' => $plan->intensity,
            'level' => $plan->level,
            'schedule' => $plan->schedule,
            'weeks' => $plan->weeks,
            'starts_on' => $plan->starts_on->toDateString(),
            'ends_on' => $plan->ends_on->toDateString(),
            'today' => $today,
            'stats' => [
                'workouts' => $plan->days->where('type', 'workout')->count(),
                'completed' => $plan->days->whereNotNull('completed_at')->count(),
            ],
            'days' => $plan->days->map(fn (TrainingPlanDay $d) => [
                'id' => $d->id,
                'date' => $d->date->toDateString(),
                'week_no' => $d->week_no,
                'weekday' => $d->weekday,
                'type' => $d->type,
                'split' => $d->split,
                'location' => $d->location,
                'focus' => $d->focus ?? [],
                'est_minutes' => $d->est_minutes,
                'is_deload' => $d->is_deload,
                'completed_at' => $d->completed_at?->toIso8601String(),
                'exercises' => $d->exercises->map(fn ($row) => [
                    'exercise_id' => $row->exercise_id,
                    'sets' => $row->sets,
                    'target_reps' => $row->target_reps,
                    'target_seconds' => $row->target_seconds,
                    'rest_seconds' => $row->rest_seconds,
                    'tempo' => 'normal',
                    'exercise' => [
                        'id' => $row->exercise->id,
                        'slug' => $row->exercise->slug,
                        'name' => $row->exercise->translation($locale)?->name ?? $row->exercise->slug,
                        'unit' => $row->exercise->unit,
                        'zone' => $row->exercise->zone,
                        'force' => $row->exercise->force,
                        'difficulty_coef' => (float) $row->exercise->difficulty_coef,
                        'media' => $row->exercise->media->map(fn ($m) => ['type' => $m->type, 'url' => $m->url]),
                    ],
                ]),
            ])->values(),
        ];
    }
}
