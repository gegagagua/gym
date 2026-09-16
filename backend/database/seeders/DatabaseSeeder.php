<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            ExerciseSeeder::class,
            ExerciseMediaSeeder::class,
            ProgramSeeder::class,
            BadgeSeeder::class,
            FeatureFlagSeeder::class,
            SpotSeeder::class,
        ]);

        // ლოკალური ადმინი Filament-ისთვის
        if (app()->isLocal()) {
            $admin = User::updateOrCreate(
                ['email' => 'admin@kalisteni.ge'],
                [
                    'display_name' => 'Admin',
                    'username' => 'admin',
                    'password' => 'password',
                    'provider' => 'phone',
                    'is_admin' => true,
                    'locale' => 'ka',
                ],
            );

            Profile::firstOrCreate(['user_id' => $admin->id], ['level' => 3, 'weight_kg' => 78]);
            Streak::firstOrCreate(['user_id' => $admin->id]);

            $this->command->info('Admin: admin@kalisteni.ge / password');
        }
    }
}
