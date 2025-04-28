<?php

namespace App\Filament\Resources\GeoPriceResource\Pages;

use App\Filament\Resources\GeoPriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGeoPrices extends ListRecords
{
    protected static string $resource = GeoPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
