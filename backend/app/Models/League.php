<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['week_start_date' => 'date'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(LeagueMember::class);
    }

    public const DIVISIONS = [1 => 'bronze', 2 => 'silver', 3 => 'gold', 4 => 'platinum', 5 => 'elite'];
}
