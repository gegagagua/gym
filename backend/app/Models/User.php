<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * FilamentUser კონტრაქტი სავალდებულოა — მის გარეშე Filament
 * ადმინ-პანელს მხოლოდ local გარემოში უშვებს და production-ზე 403-ს აბრუნებს.
 */
class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'provider_id', 'device_uuid'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_active_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'purge_after' => 'datetime',
            'is_admin' => 'boolean',
            'is_moderator' => 'boolean',
            'social_enabled' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }

    public function xpEntries(): HasMany
    {
        return $this->hasMany(XpLedger::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(SpotCheckin::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ProgramEnrollment::class);
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function isGuest(): bool
    {
        return $this->provider === 'guest';
    }

    /** Filament ადმინ-პანელზე წვდომა */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin || $this->is_moderator;
    }

    /** users ცხრილს `name` არ აქვს — Filament-ს ჩვენს ველებს ვაწვდით */
    public function getFilamentName(): string
    {
        return $this->display_name
            ?: $this->username
            ?: ($this->email ?: $this->phone ?: "user #{$this->id}");
    }
}
