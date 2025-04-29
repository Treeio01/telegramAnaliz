<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use App\Models\AccountList;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Аккаунты';
    protected static ?string $navigationGroup = 'Управление';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')
                    ->label('Номер')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('geo')
                    ->label('GEO')
                    ->sortable(),

                TextColumn::make('spamblock')
                    ->label('Тип')
                    ->colors([
                        'success' => fn($state) => $state === 'free',
                        'danger' => fn($state) => $state !== 'free',
                    ])
                    ->formatStateUsing(function ($state) {
                        return $state === 'free' ? 'Clean' : 'Spam';
                    })->badge(),

                TextColumn::make('stats_invites_count')
                    ->label('Инвайты')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Цена')
                    ->money('rub')
                    ->sortable(),

                TextColumn::make('vendor.name')
                    ->label('Продавец')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('session_created_at')
                    ->label('Создан')
                    ->date()
                    ->sortable(),

                TextColumn::make('last_connect_at')
                    ->label('Последний коннект')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('session_created_from')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Дата от'),
                        DatePicker::make('created_until')
                            ->label('Дата до'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['created_from'], fn($q) => $q->whereDate('session_created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn($q) => $q->whereDate('session_created_at', '<=', $data['created_until']));
                    }),

                SelectFilter::make('geo')
                    ->label('Фильтр по GEO')
                    ->searchable()
                    ->multiple()
                    ->options(
                        Account::query()
                            ->whereNotNull('geo')
                            ->distinct()
                            ->orderBy('geo')
                            ->pluck('geo', 'geo')
                            ->toArray()
                    ),

                SelectFilter::make('vendor_id')
                    ->label('Фильтр по продавцу')
                    ->relationship('vendor', 'name'),

                SelectFilter::make('spamblock')
                    ->label('Фильтр по типу')
                    ->options([
                        'free' => 'Clean',
                        'spam' => 'Spam',
                    ]),
            ])
            ->defaultSort('session_created_at', 'desc')
            ->actions([
                Action::make('addToList')
                    ->label('Добавить в список')
                    ->icon('heroicon-o-star')
                    ->form([
                        \Filament\Forms\Components\Select::make('account_list_id')
                            ->label('Список')
                            ->options(function () {
                                $user = auth()->user();
                                return AccountList::where('user_id', $user?->id)->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->lists()->syncWithoutDetaching([$data['account_list_id']]);
                        \Filament\Notifications\Notification::make()
                            ->title('Добавлено в список!')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('phone')->required(),
                Forms\Components\TextInput::make('geo')->required(),
                Forms\Components\Select::make('spamblock')
                    ->options([
                        'free' => 'Clean',
                        'spam' => 'Spam',
                    ])->required(),
                Forms\Components\TextInput::make('stats_invites_count')->numeric(),
                Forms\Components\TextInput::make('price')->numeric(),
                Forms\Components\Select::make('vendor_id')
                    ->relationship('vendor', 'name')
                    ->required(),
                Forms\Components\DateTimePicker::make('session_created_at'),
                Forms\Components\DateTimePicker::make('last_connect_at'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
