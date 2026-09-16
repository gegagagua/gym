<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpotEquipment extends Model
{
    protected $table = 'spot_equipment';

    protected $guarded = ['id'];

    /** სპეც. 9.1 — ვალიდური ინვენტარის თეგები */
    public const TAGS = [
        'pull_up_bar', 'parallel_bars', 'low_bar', 'wall_bars', 'rings',
        'monkey_bars', 'horizontal_ladder', 'bench', 'rope', 'ab_bench',
        'outdoor_gym_machines',
    ];

    public function spot(): BelongsTo
    {
        return $this->belongsTo(Spot::class);
    }
}
