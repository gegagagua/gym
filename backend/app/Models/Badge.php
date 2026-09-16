<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'criteria' => 'array', 'is_active' => 'boolean'];
    }
}
