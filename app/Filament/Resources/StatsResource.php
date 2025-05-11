<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatsResource\Pages;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\GeoPreset;
use Illuminate\Support\Facades\DB;

class StatsResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Доход';
    protected static ?string $navigationGroup = 'Управление';
    protected static ?string $title = 'Доход';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->withCount([
                        'accounts',
                        'accounts as valid_accounts_count' => function (Builder $q) {
                            $q->where('type', 'valid');
                        },
                        'accounts as dead_accounts_count' => function (Builder $q) {
                            $q->where('type', 'dead');
                        },
                    ]);
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

                TextColumn::make('survival_percent')
                    ->label('Процент выживаемости')
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->color(function (Vendor $record) {
                        $total = $record->accounts_count ?? 0;
                        if ($total === 0) return 'gray';
                        $valid = $record->valid_accounts_count ?? 0;
                        $percent = round(($valid / $total) * 100, 2);
                        return \App\Models\Settings::getColorForValue('survival_rate', $percent) ?? 'gray';
                    })
                    ->state(function (Vendor $record) {
                        $total = $record->accounts_count ?? 0;
                        if ($total === 0) return 0;
                        $valid = $record->valid_accounts_count ?? 0;
                        return round(($valid / $total) * 100, 2);
                    })
                    ->sortable(),

                TextColumn::make('accounts_count')
                    ->label('Кол-во акков')
                    ->sortable(),

                TextColumn::make('total_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        return $record->accounts()->sum('price');
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withSum('accounts', 'price')
                            ->orderBy('accounts_sum_price', $direction);
                    }),

                TextColumn::make('total_earned')
                    ->label('Заработано')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        $soldPrice = session('tableFilters.stats.sold_price.sold_price', 0);
                        return $record->valid_accounts_count * $soldPrice;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $soldPrice = session('tableFilters.stats.sold_price.sold_price', 0);
                        return $query
                            ->withCount(['accounts as valid_accounts_count' => function (Builder $q) {
                                $q->where('type', 'valid');
                            }])
                            ->orderByRaw("valid_accounts_count * ? {$direction}", [$soldPrice]);
                    }),

                TextColumn::make('avg_invite_price')
                    ->label('Средняя цена инвайта')
                    ->money('RUB')
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

                        // Выполняем прямой SQL-запрос с использованием NULLIF для предотвращения деления на ноль
                        $result = DB::select("
                            SELECT 
                                CASE 
                                    WHEN COUNT(*) = 0 OR AVG(stats_invites_count) = 0 THEN 0
                                    ELSE CAST(SUM(price) AS DECIMAL(10,2)) / 
                                         (CAST(AVG(stats_invites_count) AS DECIMAL(10,2)) * COUNT(*))
                                END as avg_price
                            FROM 
                                accounts
                            WHERE 
                                vendor_id = ?
                                $geoCondition
                        ", $params);

                        if (empty($result)) {
                            return 0;
                        }

                        return round($result[0]->avg_price ?? 0, 2);
                    })
                    ->sortable(),

                TextColumn::make('total_invites')
                    ->label('Сумма инвайтов')
                    ->state(function (Vendor $record) {
                        return $record->accounts()->sum('stats_invites_count');
                    })
                    ->sortable(),

                TextColumn::make('total_profit')
                    ->label('Итог')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record) {
                        $spent = $record->accounts()->sum('price');
                        $soldPrice = session('tableFilters.stats.sold_price.sold_price', 0);
                        $earned = $record->valid_accounts_count * $soldPrice;
                        return $earned - $spent;
                    })
                    ->sortable(),
            ])
            ->filters([
                Filter::make('geo')
                    ->form([
                        Select::make('preset')
                            ->label('Гео пресет')
                            ->options(GeoPreset::pluck('name', 'id'))
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $preset = GeoPreset::find($state);
                                    $set('geo', $preset->geos);
                                }
                            }),

                        Select::make('geo')
                            ->label('Гео')
                            ->multiple()
                            ->searchable()
                            ->options(
                                \App\Models\Account::query()
                                    ->whereNotNull('geo')
                                    ->distinct()
                                    ->pluck('geo', 'geo')
                                    ->toArray()
                            )
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['geo'])) {
                            $geo = $data['geo'];
                            $query->whereHas('accounts', function ($q) use ($geo) {
                                $q->whereIn('geo', $geo);
                            });
                        }
                        return $query;
                    }),

                Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('От'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('До'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['date_from']) || !empty($data['date_to'])) {
                            $query->whereHas('accounts', function ($q) use ($data) {
                                if (!empty($data['date_from'])) {
                                    $q->whereDate('session_created_at', '>=', $data['date_from']);
                                }
                                if (!empty($data['date_to'])) {
                                    $q->whereDate('session_created_at', '<=', $data['date_to']);
                                }
                            });
                        }
                        return $query;
                    }),

                Filter::make('sold_price')
                    ->form([
                        TextInput::make('sold_price')
                            ->label('Цена продажи')
                            ->numeric()
                            ->live()
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['sold_price'])) {
                            session(['tableFilters.stats.sold_price.sold_price' => $data['sold_price']]);
                        }
                        return $query;
                    }),
            ])
            ->persistFiltersInSession(); // Сохраняем фильтры в сессии
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStats::route('/'),
        ];
    }
}
