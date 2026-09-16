<?php

namespace App\Filament\Resources\Spots\Tables;

use App\Models\Spot;
use App\Services\SpotService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * მოდერაციის რიგი. UGC მოედანი pending → verified გადადის აქედან;
 * დადასტურებისას დამამატებელი იღებს +100 XP (სპეც. 9.2).
 */
class SpotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->wrap()->weight('bold'),
                TextColumn::make('city.slug')->label('ქალაქი')->badge()->color('gray'),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'verified' => 'success', 'pending' => 'warning', default => 'danger',
                }),
                TextColumn::make('equipment_count')
                    ->label('ინვენტარი')
                    ->counts('equipment'),
                TextColumn::make('condition_rating')->label('მდგომარეობა'),
                IconColumn::make('has_lighting')->label('განათება')->boolean(),
                TextColumn::make('checkin_count')->label('check-in')->sortable(),
                TextColumn::make('createdByUser.username')->label('დაამატა')->default('—'),
                TextColumn::make('created_at')->dateTime('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'მოდერაციაზე',
                    'verified' => 'დადასტურებული',
                    'rejected' => 'უარყოფილი',
                ])->default('pending'),
                SelectFilter::make('type')->options([
                    'yard' => 'ეზო', 'park' => 'პარკი', 'school' => 'სკოლა',
                    'stadium' => 'სტადიონი', 'commercial' => 'კომერციული',
                ]),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('დადასტურება')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Spot $record) => $record->status !== 'verified')
                    ->requiresConfirmation()
                    ->modalDescription('დამამატებელი მიიღებს +100 XP და მოედანი გამოჩნდება რუკაზე.')
                    ->action(function (Spot $record) {
                        app(SpotService::class)->verify($record, auth()->user());
                        Notification::make()->title('მოედანი დადასტურდა')->success()->send();
                    }),
                Action::make('reject')
                    ->label('უარყოფა')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Spot $record) => $record->status === 'pending')
                    ->schema([TextInput::make('reason')->label('მიზეზი')->required()])
                    ->action(function (Spot $record, array $data) {
                        $record->update(['status' => 'rejected', 'reject_reason' => $data['reason']]);
                        Notification::make()->title('უარყოფილია')->warning()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('verifyMany')
                        ->label('ყველას დადასტურება')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $service = app(SpotService::class);
                            $records->each(fn (Spot $spot) => $service->verify($spot, auth()->user()));
                            Notification::make()->title($records->count().' მოედანი დადასტურდა')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
