<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('provider'),
                TextInput::make('provider_id'),
                TextInput::make('device_uuid'),
                TextInput::make('password')
                    ->password(),
                TextInput::make('username'),
                TextInput::make('display_name'),
                TextInput::make('avatar_url')
                    ->url(),
                TextInput::make('locale')
                    ->required()
                    ->default('ka'),
                TextInput::make('timezone')
                    ->required()
                    ->default('Asia/Tbilisi'),
                TextInput::make('country_code')
                    ->required()
                    ->default('GE'),
                Toggle::make('is_admin')
                    ->required(),
                Toggle::make('is_moderator')
                    ->required(),
                Toggle::make('social_enabled')
                    ->required(),
                DateTimePicker::make('last_active_at'),
                DateTimePicker::make('email_verified_at'),
                DateTimePicker::make('purge_after'),
            ]);
    }
}
