<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trainer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['bio' => 'array', 'is_verified' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function spots(): BelongsToMany
    {
        return $this->belongsToMany(Spot::class, 'trainer_spots');
    }

    /**
     * free ტარიფზე მხოლოდ სახელი ჩანს — კონტაქტები ფასიანია.
     * ეს არის v1-ის ერთადერთი შემოსავლის წყარო (სპეც. 9.4).
     */
    public function showsContacts(): bool
    {
        return $this->listing_tier !== 'free'
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
