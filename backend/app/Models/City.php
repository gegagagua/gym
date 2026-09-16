<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['name' => 'array', 'lat' => 'float', 'lng' => 'float', 'is_active' => 'boolean'];
    }
}
