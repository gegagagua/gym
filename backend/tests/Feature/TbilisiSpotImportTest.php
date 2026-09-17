<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Spot;
use App\Models\SpotEquipment;
use App\Services\SpotService;
use Database\Seeders\CitySeeder;
use Database\Seeders\TbilisiSpotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TbilisiSpotImportTest extends TestCase
{
    use RefreshDatabase;

    private function dataFile(): array
    {
        return json_decode(file_get_contents(database_path('data/tbilisi_spots.json')), true);
    }

    private function row(string $externalId): array
    {
        return collect($this->dataFile()['spots'])->firstWhere('external_id', $externalId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CitySeeder::class);
    }

    public function test_the_data_file_is_real_tbilisi_data_with_valid_tags(): void
    {
        $data = $this->dataFile();

        $this->assertStringContainsString('OpenStreetMap', $data['_readme']['license']);
        $this->assertGreaterThan(200, count($data['spots']));

        foreach ($data['spots'] as $spot) {
            $this->assertContains($spot['type'], ['yard', 'park', 'school', 'stadium', 'commercial']);
            $this->assertContains($spot['access'], ['public', 'paid', 'restricted']);
            $this->assertContains($spot['source'], ['osm', 'web']);
            // თბილისის bbox
            $this->assertTrue($spot['lat'] > 41.60 && $spot['lat'] < 41.86, $spot['external_id']);
            $this->assertTrue($spot['lng'] > 44.60 && $spot['lng'] < 45.05, $spot['external_id']);
            $this->assertEmpty(array_diff($spot['equipment'], SpotEquipment::TAGS));
        }

        $ids = array_column($data['spots'], 'external_id');
        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(TbilisiSpotSeeder::class);
        $count = Spot::count();
        $expected = count($this->dataFile()['spots']);

        $this->assertSame($expected, Spot::where('source', 'osm')->orWhere('source', 'web')->count());

        $this->seed(TbilisiSpotSeeder::class);

        $this->assertSame($count, Spot::count());
        $this->assertSame(0, Spot::verified()->where('city_id', City::where('slug', 'tbilisi')->value('id'))
            ->whereNull('external_id')->count());
    }

    public function test_legacy_fake_rows_are_hidden_without_touching_ugc_or_checkin_history(): void
    {
        $service = app(SpotService::class);
        $tbilisi = City::where('slug', 'tbilisi')->value('id');
        $user = $this->makeAthlete();

        // ძველი SpotSeeder-ის ყალბი რიგი
        $fake = $service->create(['name' => 'ვაკის პარკი — ტურნიკები', 'type' => 'park', 'status' => 'verified', 'city_id' => $tbilisi], 41.7089, 44.7573);
        // იგივე სახელი, მაგრამ UGC — ავტორი ყავს
        $ugc = $service->create(['name' => 'ვაკე — ჭავჭავაძის ეზო', 'type' => 'yard', 'status' => 'verified', 'city_id' => $tbilisi, 'created_by_user_id' => $user->id, 'source' => 'ugc'], 41.6100, 44.6100);
        // ყალბი, მაგრამ ვინმე უკვე check-in-და მასზე
        $used = $service->create(['name' => 'მთაწმინდის პარკი', 'type' => 'park', 'status' => 'verified', 'city_id' => $tbilisi], 41.6150, 44.6150);
        $service->checkIn($user, $used, 41.6150, 44.6150);

        $this->seed(TbilisiSpotSeeder::class);

        $this->assertSoftDeleted('spots', ['id' => $fake->id]);
        $this->assertNotSoftDeleted('spots', ['id' => $ugc->id]);
        // check-in-ის ისტორია რჩება, მაგრამ რუკაზე აღარ ჩანს
        $this->assertNotSoftDeleted('spots', ['id' => $used->id]);
        $this->assertSame('rejected', $used->fresh()->status);
        $this->assertSame(1, $used->checkins()->count());
        $this->assertSame('verified', $ugc->fresh()->status);
    }

    public function test_nearby_returns_an_imported_gym_with_distance_and_detail_exposes_contacts(): void
    {
        $this->seed(TbilisiSpotSeeder::class);

        $row = $this->row('osm:relation/17845187'); // Oktopus City Mall — oktopus.ge-ზე გადამოწმებული

        $nearby = $this->getJson("/api/v1/spots/nearby?lat={$row['lat']}&lng={$row['lng']}&radius=500")
            ->assertOk()
            ->json('data');

        $hit = collect($nearby)->firstWhere('name', $row['name']);
        $this->assertNotNull($hit);
        $this->assertLessThanOrEqual(1, $hit['distance_m']);
        $this->assertSame('commercial', $hit['type']);
        // nearby სია lean რჩება
        $this->assertArrayNotHasKey('website', $hit);

        $this->getJson("/api/v1/spots/{$hit['id']}")
            ->assertOk()
            ->assertJsonPath('data.source', 'osm')
            ->assertJsonPath('data.website', 'https://oktopus.ge/en/city-mall/')
            ->assertJsonPath('data.opening_hours', '24/7');

        // ცენტრიდან 5 კმ-ში რეალური მოედნები ჩანს
        $center = $this->getJson('/api/v1/spots/nearby?lat=41.7151&lng=44.8271&radius=5000')->assertOk()->json('data');
        $this->assertNotEmpty($center);
    }

    public function test_attributions_include_openstreetmap_only_once_osm_spots_exist(): void
    {
        $osm = fn () => collect($this->getJson('/api/v1/attributions')->assertOk()->json('data'))
            ->firstWhere('license', 'odbl');

        $this->assertNull($osm());

        $this->seed(TbilisiSpotSeeder::class);

        $row = $osm();
        $this->assertSame('© OpenStreetMap contributors', $row['attribution_text']);
        $this->assertSame('https://www.openstreetmap.org/copyright', $row['source_url']);
    }
}
