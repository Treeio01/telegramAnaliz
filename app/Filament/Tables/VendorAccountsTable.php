<?php

namespace App\Filament\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Account;
use Filament\Tables\Columns\TextColumn;

class VendorAccountsTable extends Table
{
    protected function getTableQuery()
    {
        // Здесь нужно получить vendor_id из параметров страницы
        return Account::query()->where('vendor_id', request()->route('vendorId'));
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('number')->label('Номер'),
            TextColumn::make('geo')->label('Гео'),
            TextColumn::make('spamblock')->label('Спам'),
            TextColumn::make('date')->label('Дата'),
        ];
    }
}