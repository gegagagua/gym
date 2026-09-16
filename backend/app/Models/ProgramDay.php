<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramDay extends Model
{
    protected $guarded = ['id'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(ProgramDayExercise::class)->orderBy('sort_order');
    }

    public function isRest(): bool
    {
        return $this->type === 'rest';
    }
}
