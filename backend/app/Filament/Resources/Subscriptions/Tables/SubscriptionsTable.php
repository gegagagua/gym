<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('user_id')
                    ->label('User')
                    ->formatStateUsing(fn ($state, $record) => $record->user?->getFilamentName() ?? "#{$state}"),
                TextColumn::make('provider')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'active' => 'success',
                    'cancelled', 'billing_issue' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('product_id')->toggleable(),
                TextColumn::make('store')->toggleable(),
                TextColumn::make('environment')->toggleable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')->options(['revenuecat' => 'revenuecat', 'manual' => 'manual']),
                SelectFilter::make('status')->options(['active' => 'active', 'cancelled' => 'cancelled', 'billing_issue' => 'billing_issue', 'expired' => 'expired']),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
