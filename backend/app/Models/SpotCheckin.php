<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotCheckin extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_valid' => 'boolean',
        ];
    }

    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->is_valid && $this->expires_at->isFuture();
    }
}
