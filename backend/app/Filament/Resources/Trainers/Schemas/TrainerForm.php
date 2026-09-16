<?php

namespace App\Filament\Resources\Trainers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('photo_url')
                    ->url(),
                TextInput::make('bio'),
                TextInput::make('contact_instagram'),
                TextInput::make('contact_phone')
                    ->tel(),
                TextInput::make('contact_telegram')
                    ->tel(),
                TextInput::make('city_id')
                    ->numeric(),
                Toggle::make('is_verified')
                    ->required(),
                TextInput::make('listing_tier')
                    ->required()
                    ->default('free'),
                DateTimePicker::make('expires_at'),
            ]);
    }
}
