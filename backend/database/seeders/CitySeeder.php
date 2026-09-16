<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['tbilisi', 41.7151, 44.8271, ['ka' => 'თბილისი', 'ru' => 'Тбилиси', 'en' => 'Tbilisi']],
            ['batumi', 41.6459, 41.6417, ['ka' => 'ბათუმი', 'ru' => 'Батуми', 'en' => 'Batumi']],
            ['kutaisi', 42.2679, 42.6946, ['ka' => 'ქუთაისი', 'ru' => 'Кутаиси', 'en' => 'Kutaisi']],
            ['rustavi', 41.5495, 44.9930, ['ka' => 'რუსთავი', 'ru' => 'Рустави', 'en' => 'Rustavi']],
            ['zugdidi', 42.5088, 41.8709, ['ka' => 'ზუგდიდი', 'ru' => 'Зугдиди', 'en' => 'Zugdidi']],
            ['gori', 41.9847, 44.1086, ['ka' => 'გორი', 'ru' => 'Гори', 'en' => 'Gori']],
        ];

        foreach ($cities as [$slug, $lat, $lng, $name]) {
            City::updateOrCreate(['slug' => $slug], [
                'country_code' => 'GE',
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'is_active' => true,
            ]);
        }
    }
}
