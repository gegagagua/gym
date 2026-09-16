<?php

namespace App\Models;

use App\Enums\VerificationTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'flags' => 'array',
            'verification_tier' => VerificationTier::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(SessionSet::class, 'session_id')->orderBy('id');
    }

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(SpotCheckin::class, 'spot_checkin_id');
    }

    public function programDay(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }

    public function isFlagged(): bool
    {
        return $this->status === 'flagged';
    }
}
