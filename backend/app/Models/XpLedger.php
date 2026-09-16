<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * APPEND ONLY. არ განახლდება და არ იშლება — შესწორება მხოლოდ
 * ახალი `adjustment` ჩანაწერით ხდება (სპეც. 10.3).
 */
class XpLedger extends Model
{
    protected $table = 'xp_ledger';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'multipliers' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'raw_value' => 'float',
            'k_snapshot' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** დაცვა შემთხვევითი UPDATE/DELETE-ისგან აპლიკაციის დონეზე */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('xp_ledger is append-only'));
        static::deleting(fn () => throw new \LogicException('xp_ledger is append-only'));
    }
}
