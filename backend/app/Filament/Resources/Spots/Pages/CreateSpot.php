<?php

namespace App\Filament\Resources\Spots\Pages;

use App\Filament\Resources\Spots\SpotResource;
use App\Models\Spot;
use App\Services\SpotService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSpot extends CreateRecord
{
    protected static string $resource = SpotResource::class;

    /** ერთი INSERT location-თან ერთად — location არის NOT NULL */
    protected function handleRecordCreation(array $data): Model
    {
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        unset($data['lat'], $data['lng']);

        $data['created_by_user_id'] ??= auth()->id();

        /** @var Spot $spot */
        $spot = app(SpotService::class)->create($data, $lat, $lng);

        return $spot;
    }
}
