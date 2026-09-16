<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spot extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * `location` არის PostGIS GEOGRAPHY — Eloquent მას ბინარულად წაიკითხავდა,
     * ამიტომ ყოველთვის ცალკე ვსელექტავთ lat/lng-ს (იხ. scopeWithCoordinates).
     */
    protected $hidden = ['location'];

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'has_lighting' => 'boolean',
            'verified_at' => 'datetime',
            'rating_sum' => 'float',
        ];
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(SpotEquipment::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(SpotMedia::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(SpotCheckin::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'trainer_spots');
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('status', 'verified');
    }

    /**
     * lat/lng ცალკე სვეტებად. `spots.*` აუცილებელია — addSelect ცვლის
     * ნაგულისხმევ `*`-ს და მის გარეშე მხოლოდ კოორდინატები დაბრუნდებოდა.
     */
    public function scopeWithCoordinates(Builder $q): Builder
    {
        return $q->select('spots.*')
            ->selectRaw('ST_Y(location::geometry) as lat')
            ->selectRaw('ST_X(location::geometry) as lng');
    }

    public function averageRating(): float
    {
        return $this->rating_count > 0
            ? round($this->rating_sum / $this->rating_count, 1)
            : (float) $this->condition_rating;
    }
}
