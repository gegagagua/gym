<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const PREMIUM = 'premium';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_event_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * წვდომა ვადაზე ისაზღვრება: `cancelled` ნიშნავს მხოლოდ ავტო-განახლების
     * გამორთვას, `billing_issue` — store-ის grace period-ს. `expired` კი
     * ყოველთვის წვდომის გარეშეა, მიუხედავად ველისა.
     */
    public function scopeGrantingAccess(Builder $q): Builder
    {
        return $q->where('status', '!=', 'expired')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
