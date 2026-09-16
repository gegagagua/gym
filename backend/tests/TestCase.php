<?php

namespace Tests;

use App\Models\Profile;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeAthlete(array $profile = []): User
    {
        $user = User::create([
            'provider' => 'guest',
            'device_uuid' => 'test-'.uniqid(),
            'locale' => 'ka',
            'timezone' => 'Asia/Tbilisi',
        ]);

        Profile::create(['user_id' => $user->id] + $profile + ['weight_kg' => 80, 'level' => 3]);
        Streak::create(['user_id' => $user->id]);

        return $user->fresh();
    }
}
