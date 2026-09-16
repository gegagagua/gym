<?php

namespace App\Filament\Resources\Exercises\Schemas;

use App\Models\Exercise;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExerciseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('იდენტობა')
                ->columns(3)
                ->schema([
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->columnSpan(1),
                    Select::make('category')->required()->options([
                        'push' => 'push', 'pull' => 'pull', 'legs' => 'legs',
                        'core' => 'core', 'static' => 'static', 'mobility' => 'mobility', 'cardio' => 'cardio',
                    ]),
                    Select::make('force')->required()->options([
                        'push' => 'push', 'pull' => 'pull', 'static' => 'static',
                        'legs' => 'legs', 'core' => 'core',
                    ]),
                    Select::make('mechanic')->required()->options(['compound' => 'compound', 'isolation' => 'isolation']),
                    Select::make('unit')->required()->options(['reps' => 'reps', 'seconds' => 'seconds'])->live(),
                    Toggle::make('is_active')->default(true),
                ]),

            Section::make('XP კოეფიციენტი')
                ->description('საბაზისო: 1 კლასიკური push-up = 1.0 XP. გადაკალიბრება უსაფრთხოა — xp_ledger ინახავს k_snapshot-ს, ისტორია არ ზიანდება.')
                ->columns(3)
                ->schema([
                    TextInput::make('difficulty_coef')
                        ->label('k')
                        ->required()
                        ->numeric()
                        ->step(0.1)
                        ->minValue(0.1)
                        ->maxValue(20)
                        ->helperText(fn ($get) => $get('unit') === 'seconds'
                            ? 'base_xp = k × (წამები / 5)'
                            : 'base_xp = k × გამეორებები'),
                    TextInput::make('level_min')->numeric()->minValue(1)->maxValue(5)->default(1),
                    TextInput::make('level_max')->numeric()->minValue(1)->maxValue(5)->default(5),
                ]),

            Section::make('Skill unlock')
                ->columns(4)
                ->schema([
                    Toggle::make('is_skill_unlock')->live(),
                    TextInput::make('unlock_bonus_xp')->numeric()->default(0)
                        ->visible(fn ($get) => $get('is_skill_unlock'))
                        ->helperText('ერთჯერადი ბონუსი — დღიურ ჭერს არ ექვემდებარება'),
                    TextInput::make('unlock_threshold')->numeric()->default(1)
                        ->visible(fn ($get) => $get('is_skill_unlock'))
                        ->helperText('რამდენი გამეორება/წამი ითვლება „პირველად"'),
                    TextInput::make('skill_group')->placeholder('planche, front_lever, handstand…'),
                ]),

            Section::make('კუნთები და ინვენტარი')
                ->columns(3)
                ->schema([
                    TagsInput::make('primary_muscles')->required(),
                    TagsInput::make('secondary_muscles'),
                    TagsInput::make('equipment')->placeholder('pull_up_bar, parallel_bars…'),
                ]),

            Section::make('პროგრესია')
                ->columns(2)
                ->schema([
                    Select::make('progression_from_id')->label('უფრო მარტივი')
                        ->options(fn () => Exercise::pluck('slug', 'id'))->searchable(),
                    Select::make('progression_to_id')->label('უფრო რთული')
                        ->options(fn () => Exercise::pluck('slug', 'id'))->searchable(),
                ]),

            Section::make('თარგმანები')
                ->description('„ხშირი შეცდომები" სავალდებულო ბლოკია ყოველ სავარჯიშოზე — ის ტრავმის პრევენციაა, არა დეკორაცია.')
                ->schema([
                    Repeater::make('translations')
                        ->relationship()
                        ->schema([
                            Select::make('locale')->required()
                                ->options(['ka' => 'ქართული', 'ru' => 'Русский', 'en' => 'English']),
                            TextInput::make('name')->required(),
                            Textarea::make('short_desc')->rows(2),
                            KeyValue::make('instructions')->keyLabel('#')->valueLabel('ნაბიჯი'),
                            KeyValue::make('common_mistakes')->keyLabel('#')->valueLabel('შეცდომა'),
                        ])
                        ->itemLabel(fn (array $state) => strtoupper($state['locale'] ?? '?').' — '.($state['name'] ?? ''))
                        ->collapsed()
                        ->columns(2)
                        ->maxItems(3),
                ]),
        ]);
    }
}
