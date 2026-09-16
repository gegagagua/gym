<?php

namespace App\Services\Xp;

/** ერთი სესიის დათვლის შედეგი — API-ს პასუხის ბირთვი */
class XpResult
{
    /**
     * @param  array<int, array{code: string, exercise_id: int, bonus_xp: int}>  $unlocked
     * @param  array<int, array{exercise_id: int, metric: string, value: float}>  $newRecords
     */
    public function __construct(
        public int $workoutXp = 0,
        public int $bonusXp = 0,
        public int $leagueXp = 0,
        public array $unlocked = [],
        public array $newRecords = [],
        public bool $cappedOut = false,
    ) {}

    public function totalXp(): int
    {
        return $this->workoutXp + $this->bonusXp;
    }
}
