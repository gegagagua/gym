<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Spot;
use App\Models\SpotEquipment;
use App\Services\SpotService;
use Illuminate\Database\Seeder;

/**
 * საწყისი მოედნები თბილისში.
 *
 * ⚠️ ცარიელი რუკა = მკვდარი აპი (სპეც. 9.3, რისკი R1). გაშვებამდე
 * თბილისში ≥ 200 მოედანი უნდა იყოს ხელით შევსებული. ეს სიდერი
 * მხოლოდ ჩონჩხია განვითარებისთვის — კოორდინატები უბნების ცენტრებია
 * და ველზე გადამოწმებას საჭიროებს.
 */
class SpotSeeder extends Seeder
{
    public function run(SpotService $spots): void
    {
        $tbilisi = City::where('slug', 'tbilisi')->first();
        $batumi = City::where('slug', 'batumi')->first();

        $rows = [
            // [სახელი, lat, lng, ტიპი, განათება, მდგომარეობა, ინვენტარი]
            ['ვაკის პარკი — ტურნიკები', 41.7089, 44.7573, 'park', true, 4, ['pull_up_bar', 'parallel_bars', 'low_bar', 'monkey_bars']],
            ['ვაკე — ჭავჭავაძის ეზო', 41.7113, 44.7648, 'yard', false, 3, ['pull_up_bar', 'parallel_bars']],
            ['საბურთალო — კავკასიის უნივერსიტეტთან', 41.7256, 44.7688, 'yard', true, 4, ['pull_up_bar', 'parallel_bars', 'wall_bars']],
            ['საბურთალო — ვაჟა-ფშაველას ეზო', 41.7291, 44.7519, 'yard', false, 3, ['pull_up_bar', 'low_bar']],
            ['ლისის ტბა — გარე დარბაზი', 41.7562, 44.7205, 'park', true, 5, ['pull_up_bar', 'parallel_bars', 'rings', 'outdoor_gym_machines', 'monkey_bars']],
            ['დიღომი — 3-ე მასივი', 41.7817, 44.7566, 'yard', false, 3, ['pull_up_bar', 'parallel_bars']],
            ['დიღომი — ჩუღურეთის სკოლის ეზო', 41.7743, 44.7691, 'school', false, 2, ['pull_up_bar', 'low_bar', 'bench']],
            ['გლდანი — მე-4 მიკრორაიონი', 41.7981, 44.8127, 'yard', true, 3, ['pull_up_bar', 'parallel_bars', 'horizontal_ladder']],
            ['გლდანი — ჰიპოდრომი', 41.8034, 44.8258, 'stadium', true, 4, ['pull_up_bar', 'parallel_bars', 'rope', 'monkey_bars']],
            ['ისანი — ქეთევან დედოფლის გამზირი', 41.6903, 44.8321, 'yard', false, 3, ['pull_up_bar', 'low_bar']],
            ['ვარკეთილი — მე-3 მასივი', 41.6743, 44.8878, 'yard', false, 2, ['pull_up_bar', 'parallel_bars']],
            ['ვარკეთილი — სპორტული სკოლა', 41.6698, 44.8801, 'school', true, 4, ['pull_up_bar', 'parallel_bars', 'wall_bars', 'ab_bench']],
            ['ნაძალადევი — თემქა', 41.7962, 44.7893, 'yard', false, 3, ['pull_up_bar', 'monkey_bars']],
            ['მთაწმინდის პარკი', 41.6952, 44.7873, 'park', true, 4, ['pull_up_bar', 'parallel_bars', 'bench']],
            ['რიყე — მტკვრის სანაპირო', 41.6923, 44.8095, 'park', true, 5, ['pull_up_bar', 'parallel_bars', 'rings', 'low_bar']],
            ['ავლაბარი — ეზოს მოედანი', 41.6934, 44.8163, 'yard', false, 2, ['pull_up_bar']],
            ['დიდუბე — პარკი', 41.7501, 44.7842, 'park', true, 3, ['pull_up_bar', 'parallel_bars', 'outdoor_gym_machines']],
            ['ორთაჭალა — ეზო', 41.6796, 44.8145, 'yard', false, 2, ['pull_up_bar', 'low_bar']],
            ['ბათუმის ბულვარი — ჩრდილოეთი', 41.6512, 41.6363, 'park', true, 5, ['pull_up_bar', 'parallel_bars', 'rings', 'monkey_bars', 'outdoor_gym_machines']],
            ['ბათუმი — 6 მაისის პარკი', 41.6398, 41.6291, 'park', true, 4, ['pull_up_bar', 'parallel_bars', 'bench']],
        ];

        foreach ($rows as [$name, $lat, $lng, $type, $lighting, $condition, $equipment]) {
            if ($spots->findDuplicate($lat, $lng)) {
                continue;
            }

            $spot = $spots->create([
                'name' => $name,
                'city_id' => str_contains($name, 'ბათუმ') ? $batumi?->id : $tbilisi?->id,
                'type' => $type,
                'access' => 'public',
                'condition_rating' => $condition,
                'has_lighting' => $lighting,
                'status' => 'verified',
                'verified_at' => now(),
            ], $lat, $lng);

            foreach ($equipment as $tag) {
                SpotEquipment::firstOrCreate(['spot_id' => $spot->id, 'equipment_tag' => $tag]);
            }
        }

        $this->command->info('Spots: '.Spot::count().' (target before launch: 200+ in Tbilisi)');
    }
}
