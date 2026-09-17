<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'display_name')
                    ->getOptionLabelFromRecordUsing(fn ($user) => $user->getFilamentName())
                    ->searchable(['display_name', 'username', 'email', 'phone'])
                    ->required(),
                Hidden::make('provider')->default('manual'),
                Hidden::make('entitlement')->default('premium'),
                Select::make('status')
                    ->options(['active' => 'active', 'cancelled' => 'cancelled', 'billing_issue' => 'billing_issue', 'expired' => 'expired'])
                    ->default('active')
                    ->required()
                    // RevenueCat-ის სტატუსს webhook მართავს — ხელით ცვლა შემდეგ event-ზე გადაიწერება
                    ->disabled(fn ($record) => $record?->provider === 'revenuecat'),
                DateTimePicker::make('expires_at')
                    ->helperText('ცარიელი = ვადის გარეშე (მხოლოდ manual)')
                    ->disabled(fn ($record) => $record?->provider === 'revenuecat'),
                TextInput::make('note')->maxLength(255),
            ]);
    }
}
