<?php

namespace App\Filament\Resources\Programs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('track')
                    ->required(),
                TextInput::make('level')
                    ->required()
                    ->numeric(),
                TextInput::make('duration_weeks')
                    ->required()
                    ->numeric()
                    ->default(8),
                TextInput::make('days_per_week')
                    ->required()
                    ->numeric()
                    ->default(3),
                TextInput::make('goals'),
                TextInput::make('equipment'),
                Toggle::make('is_premium')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
