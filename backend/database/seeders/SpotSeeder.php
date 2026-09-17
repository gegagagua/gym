<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Spot;
use App\Models\SpotEquipment;
use App\Services\SpotService;
use Illuminate\Database\Seeder;

/**
 * საწყისი მოედნები თბილისის გარეთ (ბათუმი).
 *
 * თბილისი აქედან ამოღებულია — მისი ძველი 18 რიგი უბნების ცენტრების
 * კოორდინატებით იყო და რუკაზე ყალბ წერტილებს აჩვენებდა. რეალური
 * თბილისის მოედნები `TbilisiSpotSeeder`-ია (OpenStreetMap), ის ძველ
 * ყალბ რიგებსაც მალავს (`TbilisiSpotSeeder::LEGACY_FAKE_NAMES`).
 *
 * ⚠️ ბათუმის კოორდინატებიც უბნების ცენტრებია და ველზე გადამოწმებას საჭიროებს.
 */
class SpotSeeder extends Seeder
{
    public function run(SpotService $spots): void
    {
        $batumi = City::where('slug', 'batumi')->first();

        $rows = [
            // [სახელი, lat, lng, ტიპი, განათება, მდგომარეობა, ინვენტარი]
            ['ბათუმის ბულვარი — ჩრდილოეთი', 41.6512, 41.6363, 'park', true, 5, ['pull_up_bar', 'parallel_bars', 'rings', 'monkey_bars', 'outdoor_gym_machines']],
            ['ბათუმი — 6 მაისის პარკი', 41.6398, 41.6291, 'park', true, 4, ['pull_up_bar', 'parallel_bars', 'bench']],
        ];

        foreach ($rows as [$name, $lat, $lng, $type, $lighting, $condition, $equipment]) {
            if ($spots->findDuplicate($lat, $lng)) {
                continue;
            }

            $spot = $spots->create([
                'name' => $name,
                'city_id' => $batumi?->id,
                'type' => $type,
                'access' => 'public',
                'condition_rating' => $condition,
                'has_lighting' => $lighting,
                'status' => 'verified',
                'source' => 'seed',
                'verified_at' => now(),
            ], $lat, $lng);

            foreach ($equipment as $tag) {
                SpotEquipment::firstOrCreate(['spot_id' => $spot->id, 'equipment_tag' => $tag]);
            }
        }

        $this->command?->info('Spots: '.Spot::count());
    }
}
