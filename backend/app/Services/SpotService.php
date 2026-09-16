<?php

namespace App\Services;

use App\Models\Spot;
use App\Models\SpotCheckin;
use App\Models\User;
use App\Models\XpLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * გეო-ფენა PostGIS-ზე (სპეც. 9, დანართი B).
 * მანძილი ყოველთვის geography-ზე ითვლება — მეტრები, არა გრადუსები.
 */
class SpotService
{
    /** @return Collection<int, object> */
    public function nearby(float $lat, float $lng, ?int $radius = null, array $equipment = [], ?int $limit = null): Collection
    {
        $radius ??= config('kalisteni.spots.nearby_default_radius_m');
        $limit ??= config('kalisteni.spots.nearby_limit');

        $query = DB::table('spots as s')
            ->selectRaw('s.id, s.name, s.type, s.access, s.condition_rating, s.has_lighting, s.checkin_count')
            ->selectRaw('ST_Y(s.location::geometry) as lat, ST_X(s.location::geometry) as lng')
            ->selectRaw('ST_Distance(s.location, ST_MakePoint(?, ?)::geography) as distance_m', [$lng, $lat])
            ->selectRaw(
                '(SELECT COUNT(*) FROM spot_checkins c
                    WHERE c.spot_id = s.id AND c.is_valid AND c.expires_at > NOW()) as active_now'
            )
            ->selectRaw(
                "(SELECT m.thumb_url FROM spot_media m
                    WHERE m.spot_id = s.id AND m.status = 'approved'
                    ORDER BY m.is_primary DESC, m.id ASC LIMIT 1) as photo_url"
            )
            ->where('s.status', 'verified')
            ->whereNull('s.deleted_at')
            ->whereRaw('ST_DWithin(s.location, ST_MakePoint(?, ?)::geography, ?)', [$lng, $lat, $radius])
            ->orderBy('distance_m')
            ->limit($limit);

        if ($equipment) {
            // ყველა მოთხოვნილი ინვენტარი უნდა იყოს, არა რომელიმე
            $query->whereRaw(
                '(SELECT COUNT(DISTINCT e.equipment_tag) FROM spot_equipment e
                    WHERE e.spot_id = s.id AND e.equipment_tag = ANY(?)) = ?',
                ['{'.implode(',', $equipment).'}', count($equipment)],
            );
        }

        return collect($query->get())->map(function ($row) {
            $row->distance_m = (int) round($row->distance_m);
            $row->equipment = DB::table('spot_equipment')->where('spot_id', $row->id)->pluck('equipment_tag')->all();

            return $row;
        });
    }

    /** 50 მ რადიუსში დუბლიკატის ავტო-შემოწმება UGC დამატებისას (სპეც. 9.2) */
    public function findDuplicate(float $lat, float $lng): ?Spot
    {
        $radius = config('kalisteni.spots.duplicate_radius_m');

        $id = DB::table('spots')
            ->whereNull('deleted_at')
            ->whereRaw('ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)', [$lng, $lat, $radius])
            ->orderByRaw('ST_Distance(location, ST_MakePoint(?, ?)::geography)', [$lng, $lat])
            ->value('id');

        return $id ? Spot::find($id) : null;
    }

    public function create(array $attributes, float $lat, float $lng): Spot
    {
        $spot = new Spot($attributes);

        // location არის NOT NULL, ამიტომ იგივე INSERT-ში უნდა ჩაიწეროს.
        // %.8f უსაფრთხოა — მნიშვნელობები უკვე float-ებია, სტრიქონი ვერ გაჟონავს.
        $spot->location = DB::raw(sprintf(
            'ST_SetSRID(ST_MakePoint(%.8f, %.8f), 4326)::geography',
            $lng,
            $lat,
        ));

        $spot->save();

        return $spot->refresh();
    }

    /**
     * GPS check-in. ვალიდურია მხოლოდ ვერიფიცირებულ მოედანზე ≤ 100 მ-ზე.
     * აქტიურია 3 საათი და რთავს checkin_mod-ს (×1.15).
     */
    public function checkIn(User $user, Spot $spot, float $lat, float $lng, ?int $accuracyM = null): array
    {
        $radius = config('kalisteni.spots.checkin_radius_m');

        $distance = (float) DB::table('spots')
            ->where('id', $spot->id)
            ->selectRaw('ST_Distance(location, ST_MakePoint(?, ?)::geography) as d', [$lng, $lat])
            ->value('d');

        if ($distance > $radius) {
            return ['ok' => false, 'distance_m' => (int) round($distance), 'checkin' => null];
        }

        $active = SpotCheckin::where('user_id', $user->id)
            ->where('spot_id', $spot->id)
            ->where('expires_at', '>', now())
            ->where('is_valid', true)
            ->first();

        if ($active) {
            return ['ok' => true, 'distance_m' => (int) round($distance), 'checkin' => $active];
        }

        $checkin = SpotCheckin::create([
            'user_id' => $user->id,
            'spot_id' => $spot->id,
            'checked_in_at' => now(),
            'expires_at' => now()->addHours(config('kalisteni.spots.checkin_ttl_hours')),
            'accuracy_m' => $accuracyM,
            'distance_m' => (int) round($distance),
            'is_valid' => $spot->status === 'verified',
        ]);

        $spot->increment('checkin_count');

        return ['ok' => true, 'distance_m' => (int) round($distance), 'checkin' => $checkin];
    }

    /** მოდერატორის დადასტურება → +100 XP დამამატებელს */
    public function verify(Spot $spot, User $moderator): Spot
    {
        if ($spot->status === 'verified') {
            return $spot;
        }

        $spot->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by_user_id' => $moderator->id,
        ]);

        if ($spot->created_by_user_id) {
            $bonus = config('kalisteni.spots.spot_added_xp');

            $alreadyAwarded = XpLedger::where('user_id', $spot->created_by_user_id)
                ->where('reason', 'spot_added')
                ->whereJsonContains('multipliers->spot_id', $spot->id)
                ->exists();

            if (! $alreadyAwarded) {
                XpLedger::create([
                    'user_id' => $spot->created_by_user_id,
                    'reason' => 'spot_added',
                    'xp' => $bonus,
                    'league_xp' => $bonus,
                    'multipliers' => ['spot_id' => $spot->id],
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }

        return $spot;
    }

    /** მოედნის კვირეული/თვიური ბორდი — ყველაზე ძლიერი სოციალური კაუჭი (სპეც. 7.2) */
    public function leaderboard(Spot $spot, string $period = 'week', int $limit = 30): Collection
    {
        $from = $period === 'month' ? now()->subMonth() : now()->subWeek();

        return collect(DB::table('xp_ledger as l')
            ->join('workout_sessions as ws', 'ws.id', '=', 'l.session_id')
            ->join('spot_checkins as c', 'c.id', '=', 'ws.spot_checkin_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->where('c.spot_id', $spot->id)
            ->where('l.occurred_at', '>=', $from)
            ->where('ws.status', 'completed')
            ->groupBy('u.id', 'u.username', 'u.display_name', 'u.avatar_url')
            ->selectRaw('u.id as user_id, u.username, u.display_name, u.avatar_url, SUM(l.league_xp) as xp')
            ->orderByDesc('xp')
            ->limit($limit)
            ->get());
    }
}
