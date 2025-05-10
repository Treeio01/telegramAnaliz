<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatsResource\Pages;
use App\Filament\Resources\StatsResource\RelationManagers;
use App\Models\Stats;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StatsResource extends Resource
{
    protected static ?string $model = Stats::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendor')
                    ->label('Продавец'),
                Tables\Columns\TextColumn::make('survival_percent')
                    ->label('Процент выживаемости')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%'),
                Tables\Columns\TextColumn::make('survival_accounts_count')
                    ->label('Кол-во акков'),
                Tables\Columns\TextColumn::make('survival_spent')
                    ->label('Потрачено')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('survival_earned')
                    ->label('Заработано')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('avg_invite_price')
                    ->label('Средняя цена инвайта')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('invite_accounts_count')
                    ->label('Кол-во акков'),
                Tables\Columns\TextColumn::make('total_invites')
                    ->label('Сумма инвайтов'),
                Tables\Columns\TextColumn::make('invite_spent')
                    ->label('Потрачено')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('invite_earned')
                    ->label('Заработано')
                    ->money('RUB'),
                Tables\Columns\TextColumn::make('total_profit')
                    ->label('Итог')
                    ->money('RUB')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country')
                    ->label('Страна')
                    ->options([
                        // опции стран
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query, $value) {
                            // применяем фильтр только к колонкам выживаемости
                            return $query->where('geo', $value);
                        });
                    }),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('created_at', '<=', $data['until']));
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStats::route('/'),
            'create' => Pages\CreateStats::route('/create'),
            'edit' => Pages\EditStats::route('/{record}/edit'),
        ];
    }
}
