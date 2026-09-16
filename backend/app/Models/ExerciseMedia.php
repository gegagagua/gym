<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMedia extends Model
{
    protected $table = 'exercise_media';

    protected $guarded = ['id'];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** ატრიბუციის ეკრანზე ჩვენება საჭიროა ყველაფერზე, რაც არ არის ჩვენი */
    public function requiresAttribution(): bool
    {
        return ! in_array($this->license, ['own', 'public-domain'], true);
    }
}
