<?php

namespace App\Filament\Resources\GeoPresetResource\Pages;

use App\Filament\Resources\GeoPresetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeoPreset extends EditRecord
{
    protected static string $resource = GeoPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
