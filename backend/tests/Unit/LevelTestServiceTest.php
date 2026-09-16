<?php

namespace Tests\Unit;

use App\Services\LevelTestService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LevelTestServiceTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_it_scores_the_level_test(int $pushup, int $pullup, int $plank, int $expected): void
    {
        $this->assertSame($expected, (new LevelTestService)->score($pushup, $pullup, $plank)['level']);
    }

    public static function cases(): array
    {
        return [
            'complete beginner' => [0, 0, 10, 1],
            'average starter' => [12, 2, 40, 2],
            'solid mid level' => [22, 6, 75, 3],
            // pull-up ჭრის ზედა ზღვარს: 50 აჭიმი 0 მოზიდვით ≠ დონე 4
            'push-heavy, no pulling' => [50, 0, 150, 2],
            'advanced' => [45, 12, 140, 4],
            'elite' => [60, 20, 200, 5],
        ];
    }

    public function test_pull_up_caps_the_final_level(): void
    {
        $result = (new LevelTestService)->score(200, 1, 600);

        $this->assertSame(4, $result['avg']);
        $this->assertSame(3, $result['level'], 'pull-up დონე 2 → ჭერი 3');
    }
}
