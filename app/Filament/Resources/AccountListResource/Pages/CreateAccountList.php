<?php

namespace App\Filament\Resources\AccountListResource\Pages;

use App\Filament\Resources\AccountListResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountList extends CreateRecord
{
    protected static string $resource = AccountListResource::class;
}
