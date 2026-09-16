<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Profile;
use App\Services\LevelTestService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly LevelTestService $levelTest,
    ) {}

    public function show(Request $request)
    {
        return new UserResource($request->user()->load('profile', 'streak'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'locale' => ['sometimes', 'in:ka,ru,en'],
            'timezone' => ['sometimes', 'string', 'max:48'],
            'username' => ['sometimes', 'string', 'min:3', 'max:32', 'alpha_dash', 'unique:users,username,'.$request->user()->id],
            'display_name' => ['sometimes', 'string', 'max:64'],
            'avatar_url' => ['sometimes', 'nullable', 'url'],
        ]);

        $request->user()->update($data);

        return new UserResource($request->user()->fresh()->load('profile'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'birth_year' => ['sometimes', 'nullable', 'integer', 'min:1930', 'max:'.date('Y')],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],
            'height_cm' => ['sometimes', 'nullable', 'integer', 'min:100', 'max:250'],
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'min:25', 'max:250'],
            'level' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'goal' => ['sometimes', 'nullable', 'in:strength,muscle,weight_loss,skills,health'],
            'equipment' => ['sometimes', 'array'],
            'equipment.*' => ['in:none,bar,yard,gym'],
            'city_id' => ['sometimes', 'nullable', 'exists:cities,id'],
            'is_public' => ['sometimes', 'boolean'],
            'disclaimer_accepted' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $profile = Profile::firstOrCreate(['user_id' => $user->id]);

        if (! empty($data['disclaimer_accepted'])) {
            $data['disclaimer_accepted_at'] = now();
        }
        unset($data['disclaimer_accepted']);

        $profile->fill($data)->save();

        // 16 წლამდე — ლიდერბორდი და სოციალური ფუნქციები ითიშება (სპეც. 17)
        if ($profile->birth_year) {
            $age = (int) date('Y') - $profile->birth_year;
            $user->forceFill(['social_enabled' => $age >= config('kalisteni.auth.min_social_age')])->save();
        }

        return new UserResource($user->fresh()->load('profile'));
    }

    /** დონის ტესტის შედეგები → დონე + პროგრამის რეკომენდაცია (სპეც. 5.1) */
    public function submitLevelTest(Request $request)
    {
        $data = $request->validate([
            'pushup' => ['required', 'integer', 'min:0', 'max:500'],
            'pullup' => ['required', 'integer', 'min:0', 'max:200'],
            'plank_sec' => ['required', 'integer', 'min:0', 'max:1800'],
        ]);

        return response()->json($this->levelTest->apply($request->user(), $data));
    }

    public function stats(Request $request)
    {
        $period = $request->query('period', 'week');

        return response()->json($this->stats->summary($request->user(), $period));
    }

    public function records(Request $request)
    {
        $records = DB::table('personal_records as pr')
            ->join('exercises as e', 'e.id', '=', 'pr.exercise_id')
            ->leftJoin('exercise_translations as t', function ($join) {
                $join->on('t.exercise_id', '=', 'e.id')->where('t.locale', app()->getLocale());
            })
            ->where('pr.user_id', $request->user()->id)
            ->orderByDesc('pr.achieved_at')
            ->select([
                'pr.exercise_id', 'pr.metric', 'pr.value', 'pr.achieved_at',
                'pr.verification_tier', 'e.slug', 'e.unit', 'e.force',
                DB::raw('COALESCE(t.name, e.slug) as name'),
            ])
            ->get();

        return response()->json(['data' => $records]);
    }
}
