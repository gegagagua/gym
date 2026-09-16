<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['first_session', 'flag', '#D7FF3E', ['ka' => 'პირველი ნაბიჯი', 'ru' => 'Первый шаг', 'en' => 'First step'],
                ['type' => 'sessions', 'value' => 1]],
            ['streak_7', 'flame', '#FF6B35', ['ka' => 'კვირა ზედიზედ', 'ru' => 'Неделя подряд', 'en' => 'Seven in a row'],
                ['type' => 'streak', 'value' => 7]],
            ['streak_30', 'flame', '#FF6B35', ['ka' => 'თვე ზედიზედ', 'ru' => 'Месяц подряд', 'en' => 'Thirty in a row'],
                ['type' => 'streak', 'value' => 30]],
            ['streak_100', 'flame', '#FF4D5E', ['ka' => 'ასი დღე', 'ru' => 'Сто дней', 'en' => 'One hundred days'],
                ['type' => 'streak', 'value' => 100]],
            ['xp_10k', 'bolt', '#D7FF3E', ['ka' => '10 000 XP', 'ru' => '10 000 XP', 'en' => '10,000 XP'],
                ['type' => 'xp_total', 'value' => 10000]],
            ['xp_50k', 'bolt', '#D7FF3E', ['ka' => '50 000 XP', 'ru' => '50 000 XP', 'en' => '50,000 XP'],
                ['type' => 'xp_total', 'value' => 50000]],
            ['first_pull_up', 'arrow-up', '#8B5CFF', ['ka' => 'პირველი მოზიდვა', 'ru' => 'Первое подтягивание', 'en' => 'First pull-up'],
                ['type' => 'skill', 'exercise_slug' => 'pull-up', 'value' => 1]],
            ['first_muscle_up', 'star', '#8B5CFF', ['ka' => 'პირველი მასლ-აპი', 'ru' => 'Первый выход силой', 'en' => 'First muscle-up'],
                ['type' => 'skill', 'exercise_slug' => 'muscle-up', 'value' => 1]],
            ['first_handstand', 'star', '#3EE8FF', ['ka' => 'პირველი სტოიკა', 'ru' => 'Первая стойка', 'en' => 'First handstand'],
                ['type' => 'skill', 'exercise_slug' => 'handstand-hold', 'value' => 10]],
            ['first_front_lever', 'star', '#3EE8FF', ['ka' => 'ფრონტ ლევერი', 'ru' => 'Front lever', 'en' => 'Front lever'],
                ['type' => 'skill', 'exercise_slug' => 'front-lever', 'value' => 5]],
            ['first_planche', 'crown', '#C77DFF', ['ka' => 'პლანში', 'ru' => 'Планш', 'en' => 'Planche'],
                ['type' => 'skill', 'exercise_slug' => 'planche', 'value' => 3]],
            ['spot_founder', 'map-pin', '#34D07F', ['ka' => 'მოედნის დამფუძნებელი', 'ru' => 'Основатель площадки', 'en' => 'Spot founder'],
                ['type' => 'spots_added', 'value' => 1]],
            ['spot_scout', 'map-pin', '#34D07F', ['ka' => 'რუკის მკვლევარი', 'ru' => 'Картограф', 'en' => 'Map scout'],
                ['type' => 'spots_added', 'value' => 5]],
            ['checkin_25', 'target', '#3EE8FF', ['ka' => '25 check-in', 'ru' => '25 чек-инов', 'en' => '25 check-ins'],
                ['type' => 'checkins', 'value' => 25]],
            ['league_elite', 'crown', '#C77DFF', ['ka' => 'ელიტა', 'ru' => 'Элита', 'en' => 'Elite'],
                ['type' => 'division', 'value' => 5]],
        ];

        foreach ($badges as $i => [$code, $icon, $tint, $name, $criteria]) {
            Badge::updateOrCreate(['code' => $code], [
                'icon' => $icon,
                'tint' => $tint,
                'name' => $name,
                'criteria' => $criteria,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
