<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlan extends Model
{
    public const INTENSITIES = ['light', 'moderate', 'intense'];

    public const LOCATIONS = ['home', 'yard', 'gym'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(TrainingPlanDay::class)->orderBy('date');
    }
}
