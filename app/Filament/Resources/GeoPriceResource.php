<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeoPriceResource\Pages;
use App\Models\GeoPrice;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class GeoPriceResource extends Resource
{
    protected static ?string $model = GeoPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Цены по GEO';
    protected static ?string $navigationGroup = 'Управление';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('geo')
                    ->label('GEO')
                    ->required()
                    ->maxLength(5),

                TextInput::make('price')
                    ->label('Цена ($)')
                    ->required()
                    ->numeric()
                    ->rules(['min:0']),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('geo')
                    ->label('GEO')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Цена')
                    ->sortable()
                    ->formatStateUsing(fn (string $state) => '₽' . number_format($state, 2)),
            ])
            ->defaultSort('geo', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeoPrices::route('/'),
            'create' => Pages\CreateGeoPrice::route('/create'),
            'edit' => Pages\EditGeoPrice::route('/{record}/edit'),
        ];
    }
}
