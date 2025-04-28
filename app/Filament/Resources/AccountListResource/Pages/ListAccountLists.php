<?php

namespace App\Filament\Resources\AccountListResource\Pages;

use App\Filament\Resources\AccountListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountLists extends ListRecords
{
    protected static string $resource = AccountListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
