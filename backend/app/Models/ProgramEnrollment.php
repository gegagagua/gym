<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramEnrollment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function currentDay(): ?ProgramDay
    {
        return ProgramDay::where('program_id', $this->program_id)
            ->where('week_no', $this->current_week)
            ->where('day_no', $this->current_day)
            ->first();
    }
}
