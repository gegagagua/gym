<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'difficulty_coef' => 'float',
            'equipment' => 'array',
            'primary_muscles' => 'array',
            'secondary_muscles' => 'array',
            'is_skill_unlock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ExerciseTranslation::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ExerciseMedia::class)->orderBy('sort_order');
    }

    public function progressionFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'progression_from_id');
    }

    public function progressionTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'progression_to_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** მოთხოვნილი ენა → fallback en → პირველი არსებული */
    public function translation(string $locale): ?ExerciseTranslation
    {
        $all = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $all->firstWhere('locale', $locale)
            ?? $all->firstWhere('locale', 'en')
            ?? $all->first();
    }

    public function isTimeBased(): bool
    {
        return $this->unit === 'seconds';
    }
}
