<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['goals' => 'array', 'equipment' => 'array', 'is_premium' => 'boolean', 'is_active' => 'boolean'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProgramTranslation::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramDay::class)->orderBy('week_no')->orderBy('day_no');
    }

    public function translation(string $locale): ?ProgramTranslation
    {
        $all = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $all->firstWhere('locale', $locale) ?? $all->firstWhere('locale', 'en') ?? $all->first();
    }
}
