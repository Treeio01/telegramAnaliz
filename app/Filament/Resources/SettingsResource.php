<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingsResource\Pages;
use App\Filament\Resources\SettingsResource\RelationManagers;
use App\Models\Settings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SettingsResource extends Resource
{
    protected static ?string $model = Settings::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationLabel = 'Настройки цветов';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('column_name')
                    ->label('Колонка')
                    ->options([
                        'survival_rate' => 'Выживаемость',
                        'spam_percent_accounts' => 'Процент спама',
                        'clean_percent_accounts' => 'Процент чистых аккаунтов',
                        'percent_worked' => 'Процент отработанных аккаунтов',
                    ])
                    ->required(),
                Forms\Components\Select::make('color_type')
                    ->label('Тип цвета')
                    ->options([
                        'success' => 'Зеленый',
                        'warning' => 'Желтый',
                        'danger' => 'Красный',
                        'gray' => 'Серый',
                    ])
                    ->required(),
                Forms\Components\Select::make('condition_type')
                    ->label('Тип условия')
                    ->options([
                        'range' => 'Диапазон',
                        'less_than' => 'Меньше чем',
                        'greater_than' => 'Больше чем',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('min_value')
                    ->label('Минимальное значение')
                    ->numeric()
                    ->required()
                    ->visible(fn (callable $get) => in_array($get('condition_type'), ['range', 'greater_than'])),
                Forms\Components\TextInput::make('max_value')
                    ->label('Максимальное значение')
                    ->numeric()
                    ->required()
                    ->visible(fn (callable $get) => in_array($get('condition_type'), ['range', 'less_than'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('column_name')
                    ->label('Колонка')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'survival_rate' => 'Выживаемость',
                        'spam_percent_accounts' => 'Процент спама',
                        'clean_percent_accounts' => 'Процент чистых аккаунтов',
                        'percent_worked' => 'Процент отработанных аккаунтов',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('color_type')
                    ->label('Тип цвета')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Зеленый',
                        'warning' => 'Желтый',
                        'danger' => 'Красный',
                        'gray' => 'Серый',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('condition_type')
                    ->label('Тип условия')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'range' => 'Диапазон',
                        'less_than' => 'Меньше чем',
                        'greater_than' => 'Больше чем',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('min_value')
                    ->label('Минимальное значение')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_value')
                    ->label('Максимальное значение')
                    ->numeric()
                    ->sortable(),
                //
            ])
            ->filters([
                //
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
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSettings::route('/create'),
            'edit' => Pages\EditSettings::route('/{record}/edit'),
        ];
    }
}
