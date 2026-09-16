<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseTranslation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['instructions' => 'array', 'common_mistakes' => 'array'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
