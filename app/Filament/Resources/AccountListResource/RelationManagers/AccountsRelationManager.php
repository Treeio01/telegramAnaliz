<?php

namespace App\Filament\Resources\AccountListResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;

class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')->label('Номер'),
                TextColumn::make('geo')->label('Гео'),
                TextColumn::make('spamblock')->label('Тип'),
                // Добавь нужные тебе поля
            ]);
    }
}
