<?php

namespace App\Filament\Resources\GeoPriceResource\Pages;

use App\Filament\Resources\GeoPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeoPrice extends EditRecord
{
    protected static string $resource = GeoPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
