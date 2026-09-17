<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Spot;
use App\Models\SpotEquipment;
use App\Services\SpotService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * თბილისის რეალური მოედნები და დარბაზები — `database/data/tbilisi_spots.json`
 * (OpenStreetMap, ODbL + ოფიციალური საიტებით გადამოწმებული ფილიალები).
 *
 * Idempotent `external_id`-ზე: ხელახლა გაშვება ანახლებს ველებს, ახალ რიგს
 * არ ქმნის. ადმინის მიერ უარყოფილ ან წაშლილ რიგს ხელახლა არ აღვიძებს.
 * UGC-ს არ ეხება: თუ 50 მ-ში უკვე არის არა-იმპორტირებული მოედანი, ახალი
 * არ იქმნება (spec 9.2-ის დუბლიკატის წესი).
 *
 * პროდაქშენზე: php artisan migrate && php artisan db:seed --class=TbilisiSpotSeeder
 */
class TbilisiSpotSeeder extends Seeder
{
    /**
     * ძველი SpotSeeder-ის ყალბ თბილისის რიგები (კოორდინატები = უბნების ცენტრები).
     * ზუსტად ეს სახელები + external_id/ავტორი null + source null|seed →
     * soft delete (ან rejected, თუ check-in-ები აქვს). UGC-ს ავტორი ყავს
     * და source=ugc, ამიტომ მასზე არ მოხდება.
     */
    public const LEGACY_FAKE_NAMES = [
        'ვაკის პარკი — ტურნიკები',
        'ვაკე — ჭავჭავაძის ეზო',
        'საბურთალო — კავკასიის უნივერსიტეტთან',
        'საბურთალო — ვაჟა-ფშაველას ეზო',
        'ლისის ტბა — გარე დარბაზი',
        'დიღომი — 3-ე მასივი',
        'დიღომი — ჩუღურეთის სკოლის ეზო',
        'გლდანი — მე-4 მიკრორაიონი',
        'გლდანი — ჰიპოდრომი',
        'ისანი — ქეთევან დედოფლის გამზირი',
        'ვარკეთილი — მე-3 მასივი',
        'ვარკეთილი — სპორტული სკოლა',
        'ნაძალადევი — თემქა',
        'მთაწმინდის პარკი',
        'რიყე — მტკვრის სანაპირო',
        'ავლაბარი — ეზოს მოედანი',
        'დიდუბე — პარკი',
        'ორთაჭალა — ეზო',
    ];

    public function __construct(private ?string $path = null) {}

    public function run(SpotService $spots): void
    {
        $path = $this->path ?? database_path('data/tbilisi_spots.json');
        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data['spots'] ?? null)) {
            throw new RuntimeException("Invalid spot data file: {$path}");
        }

        $tbilisi = City::where('slug', 'tbilisi')->first();
        $hidden = $this->hideLegacyFakeRows($tbilisi?->id);

        $created = $updated = $skipped = 0;

        DB::transaction(function () use ($data, $spots, $tbilisi, &$created, &$updated, &$skipped) {
            foreach ($data['spots'] as $row) {
                $attributes = [
                    'name' => mb_substr($row['name'], 0, 120),
                    'city_id' => $tbilisi?->id,
                    'type' => $row['type'],
                    'access' => $row['access'],
                    'has_lighting' => (bool) $row['has_lighting'],
                    'description' => $row['description'] ?? null,
                    'source' => $row['source'],
                    'address' => $row['address'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'website' => $row['website'] ?? null,
                    'opening_hours' => $row['opening_hours'] ?? null,
                ];
                $lat = (float) $row['lat'];
                $lng = (float) $row['lng'];

                $existing = Spot::withTrashed()->where('external_id', $row['external_id'])->first();

                if ($existing) {
                    // ადმინის შეწრიტა ძალაშია
                    if ($existing->trashed() || $existing->status === 'rejected') {
                        $skipped++;

                        continue;
                    }

                    $existing->update($attributes);
                    DB::statement(
                        'UPDATE spots SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                        [$lng, $lat, $existing->id],
                    );
                    $spot = $existing;
                    $updated++;
                } else {
                    if ($this->nonImportedSpotNearby($lat, $lng)) {
                        $skipped++;

                        continue;
                    }

                    $spot = $spots->create($attributes + [
                        'external_id' => $row['external_id'],
                        'status' => 'verified',
                        'verified_at' => now(),
                    ], $lat, $lng);
                    $created++;
                }

                // მხოლოდ ამატება — ადმინის/მომხმარებლის ჩასწორებული ინვენტარი არ იშლება
                foreach (array_intersect($row['equipment'] ?? [], SpotEquipment::TAGS) as $tag) {
                    SpotEquipment::firstOrCreate(['spot_id' => $spot->id, 'equipment_tag' => $tag]);
                }
            }
        });

        $this->command?->info(sprintf(
            'Tbilisi spots: %d created, %d updated, %d skipped, %d legacy fake rows hidden',
            $created, $updated, $skipped, $hidden,
        ));
    }

    /**
     * check-in-ების გარეშე ყალბი რიგი → soft delete. თუ ვინმე უკვე
     * check-in-და, ისტორია (და მასზე მიბმული სესიები) რჩება — რიგი
     * მხოლოდ rejected ხდება, რუკაზე ასე ან ისე აღარ ჩანს.
     */
    private function hideLegacyFakeRows(?int $tbilisiId): int
    {
        $rows = Spot::query()
            ->whereIn('name', self::LEGACY_FAKE_NAMES)
            ->where(fn ($q) => $q->where('city_id', $tbilisiId)->orWhereNull('city_id'))
            ->whereNull('external_id')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', 'seed'))
            ->whereNull('created_by_user_id')
            ->where('status', '!=', 'rejected')
            ->get();

        foreach ($rows as $spot) {
            if ($spot->checkin_count === 0 && ! $spot->checkins()->exists()) {
                $spot->delete();
            } else {
                $spot->update([
                    'status' => 'rejected',
                    'reject_reason' => 'Legacy placeholder coordinates — replaced by OpenStreetMap import',
                ]);
            }
        }

        return $rows->count();
    }

    /** 50 მ-ში UGC/seed მოედანი — იმპორტი მას არ დუბლირავს */
    private function nonImportedSpotNearby(float $lat, float $lng): bool
    {
        return DB::table('spots')
            ->whereNull('deleted_at')
            ->whereNull('external_id')
            ->where('status', '!=', 'rejected')
            ->whereRaw('ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)', [
                $lng, $lat, config('kalisteni.spots.duplicate_radius_m'),
            ])
            ->exists();
    }
}
