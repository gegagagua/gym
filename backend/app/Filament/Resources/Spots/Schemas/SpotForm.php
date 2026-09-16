<?php

namespace App\Filament\Resources\Spots\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class SpotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('მოედანი')
                ->columns(3)
                ->schema([
                    TextInput::make('name')->required()->columnSpan(3),
                    Select::make('city_id')->relationship('city', 'slug')->label('ქალაქი'),
                    Select::make('type')->required()->options([
                        'yard' => 'ეზო', 'park' => 'პარკი', 'school' => 'სკოლა',
                        'stadium' => 'სტადიონი', 'commercial' => 'კომერციული',
                    ]),
                    Select::make('access')->required()->default('public')->options([
                        'public' => 'საჯარო', 'paid' => 'ფასიანი', 'restricted' => 'შეზღუდული',
                    ]),
                    TextInput::make('condition_rating')->numeric()->minValue(1)->maxValue(5)->default(3),
                    Toggle::make('has_lighting')->label('განათება'),
                    KeyValue::make('description')->keyLabel('locale')->valueLabel('აღწერა')->columnSpan(3),
                ]),

            Section::make('კოორდინატები')
                ->description('PostGIS GEOGRAPHY(POINT, 4326). შენახვისას ხელახლა იწერება ერთი ST_MakePoint-ით.')
                ->columns(2)
                ->schema([
                    // location ბინარული სვეტია — ფორმაში lat/lng-ად ვშლით და უკან ვაწყობთ
                    TextInput::make('lat')->label('განედი')->numeric()->required()
                        ->afterStateHydrated(fn ($component, $state, $record) => $component->state(
                            $state ?? ($record ? DB::table('spots')->where('id', $record->id)
                                ->value(DB::raw('ST_Y(location::geometry)')) : null)
                        ))
                        ->dehydrated(),
                    TextInput::make('lng')->label('გრძედი')->numeric()->required()
                        ->afterStateHydrated(fn ($component, $state, $record) => $component->state(
                            $state ?? ($record ? DB::table('spots')->where('id', $record->id)
                                ->value(DB::raw('ST_X(location::geometry)')) : null)
                        ))
                        ->dehydrated(),
                ]),

            Section::make('მოდერაცია')
                ->columns(2)
                ->schema([
                    Select::make('status')->required()->default('pending')->options([
                        'pending' => 'მოდერაციაზე', 'verified' => 'დადასტურებული', 'rejected' => 'უარყოფილი',
                    ]),
                    TextInput::make('reject_reason')->label('უარყოფის მიზეზი'),
                ]),
        ]);
    }
}
