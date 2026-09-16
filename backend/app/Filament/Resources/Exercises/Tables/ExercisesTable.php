<?php

namespace App\Filament\Resources\Exercises\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExercisesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('difficulty_coef')
            ->columns([
                TextColumn::make('slug')->searchable()->sortable()->weight('bold'),
                TextColumn::make('translations')
                    ->label('ka')
                    ->getStateUsing(fn ($record) => $record->translation('ka')?->name)
                    ->searchable(query: fn ($query, $search) => $query->whereHas(
                        'translations',
                        fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                    )),
                TextColumn::make('force')->badge()->color(fn ($state) => match ($state) {
                    'push' => 'success', 'pull' => 'info', 'static' => 'warning',
                    'legs' => 'danger', default => 'gray',
                }),
                TextColumn::make('unit')->badge()->color('gray'),
                TextColumn::make('difficulty_coef')->label('k')->numeric(2)->sortable()->weight('bold'),
                TextColumn::make('level_min')->label('დონე')
                    ->formatStateUsing(fn ($record) => "{$record->level_min}–{$record->level_max}"),
                IconColumn::make('is_skill_unlock')->label('skill')->boolean(),
                TextColumn::make('unlock_bonus_xp')->label('ბონუსი')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : '—'),
                IconColumn::make('is_active')->label('აქტიური')->boolean(),
            ])
            ->filters([
                SelectFilter::make('force')->options([
                    'push' => 'push', 'pull' => 'pull', 'static' => 'static',
                    'legs' => 'legs', 'core' => 'core',
                ]),
                TernaryFilter::make('is_skill_unlock')->label('Skill unlock'),
                TernaryFilter::make('is_active')->label('აქტიური'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
