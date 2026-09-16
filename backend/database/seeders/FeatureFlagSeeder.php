<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

/**
 * v1-ში ყველა v2/v3 ფუნქცია გამორთულია, მაგრამ ფლაგები არსებობს —
 * ეს იცავს კოდბეისს ორად გახლეჩისგან ექსპორტის დროს (სპეც. 19.1).
 */
class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            ['league', '*', true, null],
            ['spots', '*', true, null],
            ['trainers', 'GE', true, null],
            ['share_cards', '*', true, null],
            ['shop', 'GE', false, ['reason' => 'v2']],
            ['shop', 'AM', false, ['reason' => 'no_logistics']],
            ['nutrition', 'GE', false, ['reason' => 'v3']],
            ['xp_currency', 'GE', false, ['rate_per_1000' => 5, 'max_order_percent' => 20]],
            ['health_sync', '*', false, ['reason' => 'v1.1']],
        ];

        foreach ($flags as [$key, $country, $enabled, $config]) {
            FeatureFlag::updateOrCreate(
                ['key' => $key, 'country_code' => $country],
                ['is_enabled' => $enabled, 'config' => $config],
            );
        }
    }
}
