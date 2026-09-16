<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XpSpent extends Model
{
    protected $table = 'xp_spent';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
