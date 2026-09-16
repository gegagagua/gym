<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Spot;
use App\Models\User;
use App\Services\LeagueService;
use App\Services\SpotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeagueController extends Controller
{
    public function __construct(
        private readonly LeagueService $leagues,
        private readonly SpotService $spots,
    ) {}

    /**
     * მიმდინარე ლიგა. სანამ 200 კვირეული აქტიური არ გვყავს,
     * ლიგა გამორთულია და UI პირად streak-სა და მეგობრებს აჩვენებს (სპეც. 7.1).
     */
    public function current(Request $request)
    {
        if (! $this->leagues->isEnabled()) {
            return response()->json([
                'enabled' => false,
                'reason' => 'cold_start',
                'weekly_active' => $this->leagues->weeklyActiveCount(),
                'required' => config('kalisteni.league.min_weekly_active'),
            ]);
        }

        $member = $this->leagues->membershipFor($request->user());

        if (! $member) {
            return response()->json(['enabled' => true, 'joined' => false]);
        }

        $league = League::find($member->league_id);
        $standings = $this->leagues->standings($league->id);
        $users = User::whereIn('id', array_column($standings, 'user_id'))
            ->get(['id', 'username', 'display_name', 'avatar_url'])
            ->keyBy('id');

        $promote = config('kalisteni.league.promote');
        $demote = config('kalisteni.league.demote');
        $total = count($standings);

        return response()->json([
            'enabled' => true,
            'joined' => true,
            'division' => $league->division,
            'division_key' => League::DIVISIONS[$league->division] ?? 'bronze',
            'week_start' => $league->week_start_date->toDateString(),
            'ends_at' => $league->week_start_date->copy()->addWeek()->toIso8601String(),
            'promote_count' => $promote,
            'demote_count' => $league->division > 1 ? $demote : 0,
            'my_rank' => collect($standings)->firstWhere('user_id', $request->user()->id)['rank'] ?? null,
            'members' => collect($standings)->map(fn ($row) => [
                'rank' => $row['rank'],
                'xp' => $row['xp'],
                'user' => $users[$row['user_id']] ?? null,
                'is_me' => $row['user_id'] === $request->user()->id,
                'zone' => match (true) {
                    $row['rank'] <= $promote => 'promotion',
                    $league->division > 1 && $row['rank'] > $total - $demote => 'demotion',
                    default => 'stay',
                },
            ]),
        ]);
    }

    public function friends(Request $request)
    {
        $ids = DB::table('follows')->where('follower_id', $request->user()->id)->pluck('following_id')
            ->push($request->user()->id);

        $rows = DB::table('xp_ledger as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->whereIn('l.user_id', $ids)
            ->where('l.occurred_at', '>=', now()->subWeek())
            ->groupBy('u.id', 'u.username', 'u.display_name', 'u.avatar_url')
            ->selectRaw('u.id as user_id, u.username, u.display_name, u.avatar_url, SUM(l.league_xp) as xp')
            ->orderByDesc('xp')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function spot(Request $request, Spot $spot)
    {
        return response()->json([
            'spot' => ['id' => $spot->id, 'name' => $spot->name],
            'data' => $this->spots->leaderboard($spot, $request->query('period', 'week')),
        ]);
    }

    public function city(Request $request, int $cityId)
    {
        $rows = DB::table('xp_ledger as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('p.city_id', $cityId)
            ->where('p.is_public', true)
            ->where('l.occurred_at', '>=', now()->subMonth())
            ->groupBy('u.id', 'u.username', 'u.display_name', 'u.avatar_url')
            ->selectRaw('u.id as user_id, u.username, u.display_name, u.avatar_url, SUM(l.league_xp) as xp')
            ->orderByDesc('xp')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** all-time PR ბორდი — მხოლოდ ვიდეო-ვერიფიცირებული რეკორდები (T3) */
    public function records(Request $request, int $exerciseId)
    {
        $rows = DB::table('personal_records as pr')
            ->join('users as u', 'u.id', '=', 'pr.user_id')
            ->where('pr.exercise_id', $exerciseId)
            ->where('pr.verification_tier', 3)
            ->orderByDesc('pr.value')
            ->limit(50)
            ->select(['u.id as user_id', 'u.username', 'u.display_name', 'u.avatar_url', 'pr.value', 'pr.metric', 'pr.achieved_at', 'pr.video_url'])
            ->get();

        return response()->json(['data' => $rows]);
    }
}
