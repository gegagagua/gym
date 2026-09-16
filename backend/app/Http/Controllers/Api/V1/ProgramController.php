<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\ProgramEnrollment;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $query = Program::where('is_active', true)->with('translations');

        if ($track = $request->query('track')) {
            $query->whereIn('track', explode(',', $track));
        }

        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }

        return ProgramResource::collection($query->orderBy('track')->orderBy('level')->get());
    }

    public function show(Program $program)
    {
        return new ProgramResource($program->load(['translations', 'days.exercises']));
    }

    public function enroll(Request $request, Program $program)
    {
        $user = $request->user();

        // ერთი აქტიური პროგრამა ერთდროულად — ორი პარალელური ციკლი
        // გადავარჯიშების პირდაპირი გზაა
        ProgramEnrollment::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'abandoned']);

        $enrollment = ProgramEnrollment::create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'started_on' => now($user->timezone ?: config('kalisteni.league.timezone'))->toDateString(),
            'current_week' => 1,
            'current_day' => 1,
            'status' => 'active',
        ]);

        return response()->json(['enrollment' => $enrollment], 201);
    }

    /** მიმდინარე პროგრამა, დღევანდელი დღე და პროგრესი */
    public function current(Request $request)
    {
        $enrollment = ProgramEnrollment::with('program.translations')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $enrollment) {
            return response()->json(['enrollment' => null, 'today' => null]);
        }

        $day = $enrollment->currentDay();
        $day?->load('exercises.exercise.translations', 'exercises.exercise.media');

        $totalDays = $enrollment->program->days()->count();
        $doneDays = ($enrollment->current_week - 1) * $enrollment->program->days_per_week + ($enrollment->current_day - 1);

        return response()->json([
            'enrollment' => $enrollment,
            'program' => new ProgramResource($enrollment->program),
            'progress' => [
                'done_days' => max(0, $doneDays),
                'total_days' => $totalDays,
                'percent' => $totalDays > 0 ? round(max(0, $doneDays) / $totalDays * 100) : 0,
            ],
            'today' => $day ? [
                'id' => $day->id,
                'week_no' => $day->week_no,
                'day_no' => $day->day_no,
                'type' => $day->type,
                'est_minutes' => $day->est_minutes,
                'exercises' => $day->exercises->map(fn ($pde) => [
                    'exercise_id' => $pde->exercise_id,
                    'sets' => $pde->sets,
                    'target_reps' => $pde->target_reps,
                    'target_seconds' => $pde->target_seconds,
                    'rest_seconds' => $pde->rest_seconds,
                    'tempo' => $pde->tempo,
                    'exercise' => $pde->exercise ? [
                        'id' => $pde->exercise->id,
                        'slug' => $pde->exercise->slug,
                        'name' => $pde->exercise->translation(app()->getLocale())?->name,
                        'unit' => $pde->exercise->unit,
                        'force' => $pde->exercise->force,
                        'difficulty_coef' => (float) $pde->exercise->difficulty_coef,
                        'media' => $pde->exercise->media->map(fn ($m) => ['type' => $m->type, 'url' => $m->url]),
                    ] : null,
                ]),
            ] : null,
        ]);
    }

    /** დღის დასრულების შემდეგ კურსორის გადაწევა */
    public function advance(Request $request)
    {
        $enrollment = ProgramEnrollment::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('id')
            ->firstOrFail();

        $program = $enrollment->program;

        if ($enrollment->current_day < $program->days_per_week) {
            $enrollment->current_day++;
        } elseif ($enrollment->current_week < $program->duration_weeks) {
            $enrollment->current_week++;
            $enrollment->current_day = 1;
        } else {
            $enrollment->status = 'completed';
            $enrollment->completed_at = now();
        }

        $enrollment->save();

        return response()->json(['enrollment' => $enrollment]);
    }
}
