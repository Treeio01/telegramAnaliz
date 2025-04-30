<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeoPresetResource\Pages;
use App\Models\GeoPreset;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;

class GeoPresetResource extends Resource
{
    protected static ?string $model = GeoPreset::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'GEO Пресеты';
    protected static ?string $navigationGroup = 'Управление';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),

                Select::make('geos')
                    ->label('GEO')
                    ->multiple()
                    ->options(
                        \App\Models\Account::query()
                            ->whereNotNull('geo')
                            ->distinct()
                            ->pluck('geo', 'geo')
                            ->toArray()
                    )
                    ->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('geos')
                    ->label('GEO')
                    ->formatStateUsing(function ($state) {
                        // $state может быть строкой (json) или массивом
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);
                            if (is_array($decoded)) {
                                return implode(', ', $decoded);
                            }
                            return $state;
                        } elseif (is_array($state)) {
                            return implode(', ', $state);
                        }
                        return '';
                    }),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeoPresets::route('/'),
            'create' => Pages\CreateGeoPreset::route('/create'),
            'edit' => Pages\EditGeoPreset::route('/{record}/edit'),
        ];
    }
}