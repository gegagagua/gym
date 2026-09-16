<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SessionSyncService;
use App\Services\StatsService;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionSyncService $sync,
        private readonly StatsService $stats,
    ) {}

    /**
     * ოფლაინ სესიების პარტიული სინქი (სპეც. 11.3).
     * კლიენტიდან მოსული `xp` არსად არ იკითხება — XP მხოლოდ სერვერზე ითვლება.
     */
    public function store(Request $request)
    {
        $max = config('kalisteni.session.sync_batch_max');

        $data = $request->validate([
            'sessions' => ['required', 'array', 'min:1', "max:{$max}"],
            'sessions.*.client_uuid' => ['required', 'uuid'],
            'sessions.*.program_day_id' => ['nullable', 'integer', 'exists:program_days,id'],
            'sessions.*.spot_checkin_id' => ['nullable', 'integer'],
            'sessions.*.started_at' => ['required', 'date'],
            'sessions.*.completed_at' => ['nullable', 'date'],
            'sessions.*.duration_ms' => ['nullable', 'integer', 'min:0'],
            'sessions.*.source' => ['required', 'in:program,freestyle,test'],
            'sessions.*.device_clock_offset_ms' => ['nullable', 'integer'],
            'sessions.*.external_sync_id' => ['nullable', 'string', 'max:64'],
            'sessions.*.sets' => ['required', 'array', 'min:1'],
            'sessions.*.sets.*.exercise_id' => ['required', 'integer'],
            'sessions.*.sets.*.set_no' => ['nullable', 'integer', 'min:1'],
            'sessions.*.sets.*.reps' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'sessions.*.sets.*.seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
            'sessions.*.sets.*.added_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'sessions.*.sets.*.tempo' => ['nullable', 'in:normal,slow'],
            'sessions.*.sets.*.rest_after_ms' => ['nullable', 'integer', 'min:0'],
            'sessions.*.sets.*.started_at' => ['nullable', 'date'],
            'sessions.*.sets.*.completed_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $results = $this->sync->sync($user, $data['sessions']);
        $this->stats->forget($user);

        return response()->json([
            'results' => $results,
            'user_totals' => [
                'xp_total' => $this->stats->totalXp($user),
                'xp_today' => $this->stats->xpToday($user),
                'streak_days' => (int) ($user->fresh()->streak?->current_days ?? 0),
                'level' => (int) ($user->profile?->level ?? 1),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $sessions = $request->user()->sessions()
            ->with('sets:id,session_id,exercise_id,set_no,reps,seconds')
            ->orderByDesc('started_at')
            ->limit((int) $request->query('limit', 30))
            ->get();

        return response()->json(['data' => $sessions]);
    }
}
