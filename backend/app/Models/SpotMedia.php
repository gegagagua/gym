<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotMedia extends Model
{
    protected $table = 'spot_media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }
}
