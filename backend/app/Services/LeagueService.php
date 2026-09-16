<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\XpLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * კვირეული ლიგები (სპეც. 7).
 *
 * გლობალური all-time ბორდი განზრახ არ არსებობს — 3 თვეში ის ერთი და
 * იმავე ხალხის საკუთრება ხდება. 30-კაციანი დივიზიონი კვირეული ციკლით
 * ყველას ტოვებს რბოლაში.
 *
 * რანჟირება Redis sorted set-შია: ZADD league:{id} {xp_week} {user_id}.
 * Postgres რჩება ჭეშმარიტების წყაროდ; Redis არის ქეში, რომელიც
 * ledger-იდან ნებისმიერ მომენტში სრულად აღდგება.
 */
class LeagueService
{
    public function key(int $leagueId): string
    {
        return "league:{$leagueId}";
    }

    /** ლიგები ჩართულია მხოლოდ საკმარისი აქტიური ბაზის შემთხვევაში (cold-start დაცვა) */
    public function isEnabled(): bool
    {
        return $this->weeklyActiveCount() >= config('kalisteni.league.min_weekly_active');
    }

    public function weeklyActiveCount(): int
    {
        return (int) DB::table('workout_sessions')
            ->where('started_at', '>=', now()->subDays(config('kalisteni.league.active_window_days')))
            ->distinct()
            ->count('user_id');
    }

    public function currentWeekStart(): Carbon
    {
        // ორშაბათი 00:00 Asia/Tbilisi
        return Carbon::now(config('kalisteni.league.timezone'))->startOfWeek(Carbon::MONDAY);
    }

    public function membershipFor(User $user): ?LeagueMember
    {
        return LeagueMember::query()
            ->join('leagues', 'leagues.id', '=', 'league_members.league_id')
            ->where('league_members.user_id', $user->id)
            ->where('leagues.week_start_date', $this->currentWeekStart()->toDateString())
            ->select('league_members.*')
            ->first();
    }

    /** სესიის დასრულებისას ინკრემენტული განახლება — გადათვლის ლოდინის გარეშე */
    public function addXp(User $user, int $xp): void
    {
        if ($xp === 0) {
            return;
        }

        $member = $this->membershipFor($user);
        if (! $member) {
            return;
        }

        $member->increment('xp_week', $xp);
        Redis::zincrby($this->key($member->league_id), $xp, (string) $user->id);
    }

    /** @return array<int, array{user_id:int, xp:int, rank:int}> */
    public function standings(int $leagueId): array
    {
        $raw = Redis::zrevrange($this->key($leagueId), 0, -1, ['withscores' => true]);

        if (! $raw) {
            return $this->rebuild($leagueId);
        }

        $rank = 0;

        return collect($raw)
            ->map(fn ($score, $userId) => [
                'user_id' => (int) $userId,
                'xp' => (int) $score,
                'rank' => ++$rank,
            ])
            ->values()
            ->all();
    }

    /** Redis-ის აღდგენა Postgres-იდან — ქეშის დაკარგვა მონაცემს არ კარგავს */
    public function rebuild(int $leagueId): array
    {
        $members = LeagueMember::where('league_id', $leagueId)
            ->orderByDesc('xp_week')
            ->get(['user_id', 'xp_week']);

        if ($members->isEmpty()) {
            return [];
        }

        $payload = [];
        foreach ($members as $m) {
            $payload[(string) $m->user_id] = (float) $m->xp_week;
        }
        Redis::zadd($this->key($leagueId), $payload);
        Redis::expire($this->key($leagueId), 60 * 60 * 24 * 14);

        $rank = 0;

        return $members->map(fn ($m) => [
            'user_id' => (int) $m->user_id,
            'xp' => (int) $m->xp_week,
            'rank' => ++$rank,
        ])->all();
    }

    /**
     * ახალი კვირის ჯგუფების ფორმირება.
     * მონაწილეობს ის, ვისაც ბოლო 7 დღეში ≥1 სესია აქვს დასრულებული.
     */
    public function formWeek(?Carbon $weekStart = null): int
    {
        $weekStart ??= $this->currentWeekStart();

        if (! $this->isEnabled()) {
            return 0;
        }

        $size = config('kalisteni.league.size');
        $eligible = $this->eligibleUsers();
        $created = 0;

        foreach ($eligible->groupBy('division') as $division => $users) {
            foreach ($users->shuffle()->chunk($size) as $chunk) {
                $league = League::create([
                    'division' => (int) $division,
                    'week_start_date' => $weekStart->toDateString(),
                    'status' => 'active',
                    'member_count' => $chunk->count(),
                ]);

                LeagueMember::insert($chunk->map(fn ($u) => [
                    'league_id' => $league->id,
                    'user_id' => $u->user_id,
                    'xp_week' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());

                $created++;
            }
        }

        return $created;
    }

    /**
     * კვირის დახურვა: ტოპ 7 ადის, ბოლო 7 ეშვება (ბრინჯაოდან არ ეშვება).
     * ახალი დივიზიონი ინახება League::DIVISIONS-ის ლოგიკით მომდევნო
     * კვირის ფორმირებისთვის.
     */
    public function closeWeek(Carbon $weekStart): int
    {
        $promote = config('kalisteni.league.promote');
        $demote = config('kalisteni.league.demote');
        $max = config('kalisteni.league.divisions');
        $closed = 0;

        League::where('week_start_date', $weekStart->toDateString())
            ->where('status', 'active')
            ->each(function (League $league) use ($promote, $demote, $max, &$closed) {
                $standings = $this->standings($league->id);
                $total = count($standings);

                foreach ($standings as $row) {
                    $movement = 'stay';
                    $newDivision = $league->division;

                    if ($row['rank'] <= $promote && $league->division < $max) {
                        $movement = 'up';
                        $newDivision++;
                    } elseif ($row['rank'] > $total - $demote && $league->division > 1) {
                        $movement = 'down';
                        $newDivision--;
                    }

                    LeagueMember::where('league_id', $league->id)
                        ->where('user_id', $row['user_id'])
                        ->update(['rank_final' => $row['rank'], 'movement' => $movement]);

                    // შემდეგი კვირის დივიზიონი Redis hash-ში ინახება —
                    // ცალკე ცხრილი ამისთვის ზედმეტია, აღდგენა league_members-იდან შეიძლება.

                    Redis::hset('league:division', (string) $row['user_id'], (string) $newDivision);
                }

                $league->update(['status' => 'closed']);
                Redis::del($this->key($league->id));
                $closed++;
            });

        return $closed;
    }

    /** @return Collection<int, object{user_id:int, division:int}> */
    private function eligibleUsers()
    {
        $since = now()->subDays(config('kalisteni.league.active_window_days'));

        $userIds = DB::table('workout_sessions')
            ->where('started_at', '>=', $since)
            ->where('status', 'completed')
            ->distinct()
            ->pluck('user_id');

        $divisions = Redis::hgetall('league:division') ?: [];

        return $userIds
            ->map(fn ($id) => (object) [
                'user_id' => (int) $id,
                'division' => (int) ($divisions[(string) $id] ?? 1),
            ])
            ->filter(fn ($u) => User::whereKey($u->user_id)->value('social_enabled'));
    }

    /** ledger-იდან სრული გადათვლა — cron-ის fallback (ყოველ 5 წუთში) */
    public function recomputeFromLedger(int $leagueId): void
    {
        $league = League::find($leagueId);
        if (! $league) {
            return;
        }

        $from = $league->week_start_date->copy()->startOfDay();
        $to = $from->copy()->addWeek();

        $totals = XpLedger::query()
            ->whereIn('user_id', LeagueMember::where('league_id', $leagueId)->pluck('user_id'))
            ->whereBetween('occurred_at', [$from, $to])
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(league_xp) as total')
            ->pluck('total', 'user_id');

        foreach ($totals as $userId => $total) {
            LeagueMember::where('league_id', $leagueId)
                ->where('user_id', $userId)
                ->update(['xp_week' => (int) $total]);
        }

        Redis::del($this->key($leagueId));
        $this->rebuild($leagueId);
    }
}
