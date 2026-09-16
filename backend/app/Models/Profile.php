<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'equipment' => 'array',
            'level_test' => 'array',
            'weight_kg' => 'float',
            'is_public' => 'boolean',
            'disclaimer_accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * weight_mod-ისთვის საჭირო წონა. თუ მომხმარებელმა არ მიუთითა,
     * ვიყენებთ 75 კგ-ს — ეს ამცირებს added_weight-ის ეფექტს
     * გაზვიადების ნაცვლად, რაც უსაფრთხო ნაგულისხმევია.
     */
    public function effectiveWeightKg(): float
    {
        return $this->weight_kg > 0 ? (float) $this->weight_kg : 75.0;
    }
}
