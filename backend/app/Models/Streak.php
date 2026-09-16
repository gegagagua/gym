<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Streak extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_activity_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** streak_mod (სპეც. 6.2) */
    public function multiplier(): float
    {
        return match (true) {
            $this->current_days >= 30 => 1.25,
            $this->current_days >= 14 => 1.15,
            $this->current_days >= 7 => 1.10,
            $this->current_days >= 3 => 1.05,
            default => 1.0,
        };
    }
}
