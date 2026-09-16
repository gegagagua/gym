<?php

namespace App\Filament\Resources\FeatureFlags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FeatureFlagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                TextInput::make('country_code')
                    ->required()
                    ->default('*'),
                Toggle::make('is_enabled')
                    ->required(),
                TextInput::make('config'),
            ]);
    }
}
