<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBadge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
