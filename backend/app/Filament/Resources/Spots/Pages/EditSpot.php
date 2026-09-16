<?php

namespace App\Filament\Resources\Spots\Pages;

use App\Filament\Resources\Spots\SpotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditSpot extends EditRecord
{
    protected static string $resource = SpotResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $point = DB::table('spots')->where('id', $this->record->id)
            ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng')
            ->first();

        $data['lat'] = $point?->lat;
        $data['lng'] = $point?->lng;

        return $data;
    }

    /** lat/lng ვირტუალურია — ცალკე UPDATE-ით იწერება geography-ად */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingPoint = [(float) $data['lng'], (float) $data['lat']];
        unset($data['lat'], $data['lng']);

        return $data;
    }

    private array $pendingPoint = [];

    protected function afterSave(): void
    {
        if ($this->pendingPoint) {
            DB::statement(
                'UPDATE spots SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [...$this->pendingPoint, $this->record->id],
            );
        }
    }
}
