<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteResource\Pages;
use App\Filament\Resources\InviteResource\RelationManagers;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\GeoPreset;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\DB;
use App\Models\Vendor;

class StatsResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Инвайты';
    protected static ?string $navigationGroup = 'Управление';
    protected static ?string $title = 'Инвайты';

    public static function table(Table $table): Table
    {
        // Получаем активные фильтры GEO, если они установлены
        $geoFilters = $table->getFilters()['geo']['geo'] ?? [];
        $hasGeoFilter = !empty($geoFilters);

        return $table
            ->query(function () use ($hasGeoFilter, $geoFilters) {
                $query = Vendor::query();
                
                // Формируем selectRaw для нужных метрик
                $geoCondition = $hasGeoFilter
                    ? 'accounts.geo IN ("' . implode('","', $geoFilters) . '")'
                    : '1=1';

                $query->selectRaw("
                    vendors.*,
                    COUNT(accounts.id) as total_accounts,
                    AVG(CASE WHEN $geoCondition THEN accounts.stats_invites_count ELSE NULL END) as avg_invites,
                    SUM(CASE WHEN $geoCondition AND accounts.stats_invites_count > 0 THEN 1 ELSE 0 END) as worked_accounts,
                    SUM(CASE WHEN $geoCondition AND (accounts.stats_invites_count = 0 OR accounts.stats_invites_count IS NULL) THEN 1 ELSE 0 END) as zero_accounts,
                    CASE WHEN COUNT(accounts.id) = 0 THEN 0 ELSE
                        (SUM(CASE WHEN $geoCondition AND accounts.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(accounts.id))
                    END as percent_worked,
                    
                    /* Суммы для расчета средней цены */
                    SUM(CASE WHEN $geoCondition THEN accounts.price ELSE 0 END) as total_price,
                    SUM(CASE WHEN $geoCondition THEN accounts.stats_invites_count ELSE 0 END) as total_invites,
                    CASE 
                        WHEN COUNT(accounts.id) > 0 
                        THEN CAST(SUM(accounts.price) AS DECIMAL(10,2)) / 
                             (CAST(AVG(accounts.stats_invites_count) AS DECIMAL(10,2)) * COUNT(accounts.id))
                        ELSE 0
                    END as avg_price_per_invite
                ")
                ->leftJoin('accounts', 'vendors.id', '=', 'accounts.vendor_id')
                ->groupBy('vendors.id');

                return $query;
            })
            ->columns([
                TextColumn::make('copy_name')
                    ->label('')
                    ->state('📋')  // Эмодзи буфера обмена
                    ->copyable()
                    ->copyableState(fn(Vendor $record): string => $record->name)
                    ->copyMessage('Скопировано')
                    ->copyMessageDuration(2000),
                TextColumn::make('name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable()
                    ->url(fn(Vendor $record): string => route('vendor.profile', $record->id)),
                TextColumn::make('total_accounts')
                    ->label('Кол-во аккаунтов')
                    ->state(fn(Vendor $record) => $record->total_accounts ?? 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_accounts', $direction);
                    }),
                TextColumn::make('avg_invites')
                    ->label('Среднее кол-во инвайта')
                    ->state(fn(Vendor $record) => is_null($record->avg_invites) ? 0 : round($record->avg_invites, 2))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('avg_invites', $direction);
                    }),
                TextColumn::make('worked_accounts')
                    ->label('Отработали')
                    ->state(fn(Vendor $record) => $record->worked_accounts ?? 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('worked_accounts', $direction);
                    }),
                TextColumn::make('zero_accounts')
                    ->label('Нулевые')
                    ->state(fn(Vendor $record) => $record->zero_accounts ?? 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('zero_accounts', $direction);
                    }),
                TextColumn::make('percent_worked')
                    ->label('% рабочих')
                    ->state(fn(Vendor $record) => is_null($record->percent_worked) ? 0 : round($record->percent_worked, 2))
                    ->color(function (Vendor $record) {
                        $percent = $record->percent_worked ?? 0;
                        return \App\Models\Settings::getColorForValue('percent_worked', $percent);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('percent_worked', $direction);
                    }),
                TextColumn::make('avg_price_per_invite')
                    ->label('Средняя цена инвайта')
                    ->state(function (Vendor $record) {
                        // Получаем ID продавца
                        $vendorId = $record->id;

                        // Получаем фильтры гео
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $hasGeoFilter = !empty($geoFilters);

                        // Формируем условие для гео
                        $geoCondition = '';
                        $params = [$vendorId];

                        if ($hasGeoFilter) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND geo IN ($placeholders)";
                            $params = array_merge($params, $geoFilters);
                        }

                        // Выполняем прямой SQL-запрос для получения данных
                        $result = DB::select("
                            SELECT 
                                SUM(price) as total_price,
                                SUM(stats_invites_count) as total_invites
                            FROM 
                                accounts
                            WHERE 
                                vendor_id = ?
                                $geoCondition
                        ", $params);

                        if (empty($result)) {
                            return 0;
                        }

                        $totalPrice = $result[0]->total_price ?? 0;
                        $totalInvites = $result[0]->total_invites ?? 0;

                        // Защита от деления на ноль
                        if ($totalInvites <= 0) {
                            return 0;
                        }

                        // Вычисляем среднюю цену за инвайт
                        $avgPrice = $totalPrice / $totalInvites;

                        return round($avgPrice, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('avg_price_per_invite', $direction);
                    }),
                    
                // Новые поля
                TextColumn::make('spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        $totalAccounts = $record->total_accounts ?? 0;
                        $avgInvites = is_null($record->avg_invites) ? 0 : round($record->avg_invites, 2);
                        
                        // Получаем среднюю цену инвайта
                        $vendorId = $record->id;
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $hasGeoFilter = !empty($geoFilters);
                        $geoCondition = '';
                        $params = [$vendorId];

                        if ($hasGeoFilter) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND geo IN ($placeholders)";
                            $params = array_merge($params, $geoFilters);
                        }

                        $result = DB::select("
                            SELECT 
                                SUM(price) as total_price,
                                SUM(stats_invites_count) as total_invites
                            FROM 
                                accounts
                            WHERE 
                                vendor_id = ?
                                $geoCondition
                        ", $params);

                        if (empty($result) || $result[0]->total_invites <= 0) {
                            return 0;
                        }

                        $avgPricePerInvite = $result[0]->total_price / $result[0]->total_invites;
                        
                        // Формула: акки * среднее кол-во инвайта * средняя цена инвайта = Потрачено
                        return round($totalAccounts * $avgInvites * $avgPricePerInvite, 2);
                    })
                    ->sortable(),
                    
                TextColumn::make('earned')
                    ->label('Заработано')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        // Получаем данные для формулы
                        $totalAccounts = $record->total_accounts ?? 0;
                        $avgInvites = is_null($record->avg_invites) ? 0 : round($record->avg_invites, 2);
                        
                        // Получаем цену инвайта из фильтра для инвайтов
                        $invitePrice = session('tableFilters.invite.sold_price.sold_price', 0);
                        
                        // Получаем потраченную сумму (используя ту же формулу, что и для поля "Потрачено")
                        $vendorId = $record->id;
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $hasGeoFilter = !empty($geoFilters);
                        $geoCondition = '';
                        $params = [$vendorId];

                        if ($hasGeoFilter) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND geo IN ($placeholders)";
                            $params = array_merge($params, $geoFilters);
                        }

                        $result = DB::select("
                            SELECT 
                                SUM(price) as total_price,
                                SUM(stats_invites_count) as total_invites
                            FROM 
                                accounts
                            WHERE 
                                vendor_id = ?
                                $geoCondition
                        ", $params);

                        if (empty($result) || $result[0]->total_invites <= 0) {
                            return 0;
                        }

                        $avgPricePerInvite = $result[0]->total_price / $result[0]->total_invites;
                        $spent = $totalAccounts * $avgInvites * $avgPricePerInvite;
                        
                        // Формула: потрачено - (акки * среднее кол-во инвайта * цена инвайта из фильтра) = Заработано
                        $earned = $spent - ($totalAccounts * $avgInvites * $invitePrice);
                        
                        return round($earned, 2);
                    })
                    ->sortable(),
                    
                TextColumn::make('survival_spent')
                    ->label('Потрачено (выживаемость)')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        return $record->accounts()->sum('price');
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withSum('accounts', 'price')
                            ->orderBy('accounts_sum_price', $direction);
                    }),

                TextColumn::make('survival_earned')
                    ->label('Заработано (выживаемость)')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        // Используем цену продажи из фильтра выживаемости
                        $soldPrice = session('tableFilters.survival.sold_price.sold_price', 0);
                        $validAccountsCount = $record->accounts()->where('type', 'valid')->count();
                        return $validAccountsCount * $soldPrice;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $soldPrice = session('tableFilters.survival.sold_price.sold_price', 0);
                        return $query
                            ->withCount(['accounts as valid_accounts_count' => function (Builder $q) {
                                $q->where('type', 'valid');
                            }])
                            ->orderByRaw("valid_accounts_count * ? {$direction}", [$soldPrice]);
                    }),
                
                TextColumn::make('total_profit')
                    ->label('Итог')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record) {
                        $spent = $record->accounts()->sum('price');
                        $soldPrice = session('tableFilters.survival.sold_price.sold_price', 0);
                        $validAccountsCount = $record->accounts()->where('type', 'valid')->count();
                        $earned = $validAccountsCount * $soldPrice;
                        return $earned - $spent;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $soldPrice = session('tableFilters.survival.sold_price.sold_price', 0);
                        return $query
                            ->withCount(['accounts as valid_accounts_count' => function (Builder $q) {
                                $q->where('type', 'valid');
                            }])
                            ->withSum('accounts', 'price')
                            ->orderByRaw("(valid_accounts_count * ? - COALESCE(accounts_sum_price, 0)) {$direction}", 
                                [$soldPrice]
                            );
                    }),
            ])
            ->filters([
                Filter::make('min_accounts')
                    ->form([
                        TextInput::make('min_accounts')
                            ->numeric()
                            ->label('Мин. аккаунтов')
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['min_accounts'])) {
                            $min = (int) $data['min_accounts'];
                            return $query->whereHas('accounts', function ($query) use ($min) {
                                $query->havingRaw('COUNT(accounts.id) >= ?', [$min]);
                            });
                        }
                        return $query;
                    }),
                Filter::make('percent_worked')
                    ->form([
                        TextInput::make('percent_worked_min')
                            ->numeric()
                            ->label('Мин. % рабочих')
                            ->default(null),
                        TextInput::make('percent_worked_max')
                            ->numeric()
                            ->label('Макс. % рабочих')
                            ->default(null),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $min = isset($data['percent_worked_min']) && $data['percent_worked_min'] !== null && $data['percent_worked_min'] !== '' ? (float)$data['percent_worked_min'] : null;
                        $max = isset($data['percent_worked_max']) && $data['percent_worked_max'] !== null && $data['percent_worked_max'] !== '' ? (float)$data['percent_worked_max'] : null;

                        if ($min !== null) {
                            $query->whereRaw('
                                (
                                    SELECT 
                                        CASE WHEN COUNT(a.id) = 0 THEN 0 ELSE
                                            (SUM(CASE WHEN a.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id))
                                        END
                                    FROM accounts a
                                    WHERE a.vendor_id = vendors.id
                                ) >= ?', [$min]);
                        }
                        if ($max !== null) {
                            $query->whereRaw('
                                (
                                    SELECT 
                                        CASE WHEN COUNT(a.id) = 0 THEN 0 ELSE
                                            (SUM(CASE WHEN a.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id))
                                        END
                                    FROM accounts a
                                    WHERE a.vendor_id = vendors.id
                                ) <= ?', [$max]);
                        }
                        return $query;
                    }),
                Filter::make('geo')
                    ->form([
                        Select::make('preset')
                            ->label('Гео пресет')
                            ->options(GeoPreset::pluck('name', 'id'))
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $preset = GeoPreset::find($state);
                                    $set('geo', $preset ? $preset->geos : []);
                                }
                            }),
                        Select::make('geo')
                            ->label('Гео')
                            ->multiple()
                            ->searchable()
                            ->options(
                                Account::query()
                                    ->whereNotNull('geo')
                                    ->distinct()
                                    ->pluck('geo', 'geo')
                                    ->toArray()
                            )
                    ])
                    ->query(function (Builder $query, array $data) {
                        session(['current_geo_filters' => $data['geo'] ?? []]);
                        if (!empty($data['geo'])) {
                            $geo = $data['geo'];
                            $query->whereHas('accounts', function ($query) use ($geo) {
                                $query->whereIn('geo', $geo);
                            });
                        }
                        return $query;
                    }),
                Filter::make('session_created_date_range')
                    ->form([
                        DatePicker::make('session_created_from')
                            ->label('Сессия от'),
                        DatePicker::make('session_created_to')
                            ->label('Сессия до'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['session_created_from'])) {
                            $query->whereHas('accounts', function ($query) use ($data) {
                                $query->whereDate('session_created_at', '>=', $data['session_created_from']);
                            });
                        }
                        if (!empty($data['session_created_to'])) {
                            $query->whereHas('accounts', function ($query) use ($data) {
                                $query->whereDate('session_created_at', '<=', $data['session_created_to']);
                            });
                        }
                        return $query;
                    }),
                // Фильтр для цены инвайтов (для расчета заработка с инвайтов)
                Filter::make('invite_sold_price')
                    ->form([
                        TextInput::make('sold_price')
                            ->label('Цена продажи инвайта')
                            ->numeric()
                            ->live()
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['sold_price'])) {
                            session(['tableFilters.invite.sold_price.sold_price' => $data['sold_price']]);
                        }
                        return $query;
                    }),
                // Фильтр для цены по выживаемости (для расчета заработка с выживших аккаунтов)
                Filter::make('survival_sold_price')
                    ->form([
                        TextInput::make('sold_price')
                            ->label('Цена продажи выживших')
                            ->numeric()
                            ->live()
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['sold_price'])) {
                            session(['tableFilters.survival.sold_price.sold_price' => $data['sold_price']]);
                        }
                        return $query;
                    }),
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
            'index' => Pages\ListInvites::route('/'),
            'create' => Pages\CreateInvite::route('/create'),
            'edit' => Pages\EditInvite::route('/{record}/edit'),
        ];
    }
}