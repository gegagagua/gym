<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushLog extends Model
{
    protected $table = 'push_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'sent_at' => 'datetime', 'opened' => 'boolean'];
    }
}
