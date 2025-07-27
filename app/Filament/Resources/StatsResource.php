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
                        $percent = $total > 0 ? round(($valid / $total) * 100, 2) : 0;

                        return \App\Models\Settings::getColorForValue('survival_rate', $percent) ?? 'gray';
                    })
                    ->state(function (Vendor $record) {
                        $dateFrom = session('tableFilters.stats.date_from');
                        $dateTo = session('tableFilters.stats.date_to');

                        $accountsQuery = $record->accounts();
                        if ($dateFrom || $dateTo) {
                            if ($dateFrom) {
                                $accountsQuery->whereDate('session_created_at', '>=', $dateFrom);
                            }
                            if ($dateTo) {
                                $accountsQuery->whereDate('session_created_at', '<=', $dateTo);
                            }
                        }

                        $total = $accountsQuery->count();
                        if ($total === 0) return 0;

                        $valid = $accountsQuery->where('type', 'valid')->count();
                        return round(($valid / $total) * 100, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount(['accounts'])
                            ->withCount(['accounts as valid_accounts_count' => function (Builder $q) {
                                $q->where('type', 'valid');
                            }])
                            ->orderByRaw(
                                "CASE WHEN accounts_count = 0 THEN 0 ELSE (valid_accounts_count * 100.0 / accounts_count) END $direction"
                            );
                    }),

                TextColumn::make('accounts_count')
                    ->label('Кол-во акков')
                    ->sortable(),

                TextColumn::make('survival_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        // Вычисляем среднюю цену аккаунта
                        $totalAccounts = $record->accounts_count ?? 0;
                        $totalPrice = $record->accounts()->sum('price');

                        // Если есть аккаунты, считаем среднюю цену, иначе 0
                        $avgPrice = $totalAccounts > 0 ? ($totalPrice / $totalAccounts) : 0;

                        // Формула: акки * цена
                        return (float)$totalAccounts * (float)$avgPrice;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount('accounts')
                            ->withSum('accounts', 'price')
                            ->orderByRaw("CASE WHEN accounts_count = 0 THEN 0 ELSE accounts_count * (accounts_sum_price / accounts_count) END {$direction}");
                    }),

                TextColumn::make('survival_earned')
                    ->label('Заработано')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record) {
                        $soldPrice = (float)session('tableFilters.stats.sold_price.survival_sold_price', 0);
                        $totalAccounts = $record->accounts_count ?? 0;

                        // Вычисляем процент выживаемости
                        $survivalPercent = 0;
                        if ($totalAccounts > 0) {
                            $validAccounts = $record->valid_accounts_count ?? 0;
                            $survivalPercent = $validAccounts / $totalAccounts;
                        }

                        // Формула: акки * выжило процент * цена продажи
                        return (float)$totalAccounts * (float)$survivalPercent * (float)$soldPrice;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $soldPrice = (float)session('tableFilters.stats.sold_price.survival_sold_price', 0);
                        return $query
                            ->withCount('accounts')
                            ->withCount(['accounts as valid_accounts_count' => function (Builder $q) {
                                $q->where('type', 'valid');
                            }])
                            ->orderByRaw("CASE WHEN accounts_count = 0 THEN 0 ELSE accounts_count * (valid_accounts_count / accounts_count) * ? END {$direction}", [$soldPrice]);
                    }),

                TextColumn::make('avg_invite_price')
                    ->label('Средняя цена инвайта')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        $geoFilters = request('tableFilters.geo.geo', []);
                        $hasGeoFilter = !empty($geoFilters);

                        $geoCondition = '';
                        $params = [$inviteVendor->id];

                        if ($hasGeoFilter) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND geo IN ($placeholders)";
                            $params = array_merge($params, $geoFilters);
                        }

                        $result = DB::select("
                            SELECT 
                                CASE 
                                    WHEN COUNT(*) = 0 OR AVG(stats_invites_count) = 0 THEN 0
                                    ELSE CAST(SUM(price) AS DECIMAL(10,2)) / 
                                         (CAST(AVG(stats_invites_count) AS DECIMAL(10,2)) * COUNT(*))
                                END as avg_price
                            FROM 
                                invite_accounts
                            WHERE 
                                invite_vendor_id = ?
                                $geoCondition
                        ", $params);

                        return round($result[0]->avg_price ?? 0, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $geoCondition = '';
                        $params = [];

                        if (!empty($geoFilters)) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND geo IN ($placeholders)";
                            $params = $geoFilters;
                        }

                        return $query
                            ->orderByRaw("(
                                SELECT 
                                    CASE 
                                        WHEN COUNT(*) = 0 OR AVG(stats_invites_count) = 0 THEN 0
                                        ELSE CAST(SUM(price) AS DECIMAL(10,2)) / 
                                             (CAST(AVG(stats_invites_count) AS DECIMAL(10,2)) * COUNT(*))
                                    END
                                FROM 
                                    invite_accounts iv
                                    JOIN invite_vendors ivv ON iv.invite_vendor_id = ivv.id 
                                WHERE 
                                    ivv.name = vendors.name
                                    $geoCondition
                            ) $direction", $params);
                    }),

                TextColumn::make('invites_accounts_count')
                    ->label('Кол-во акков')
                    ->state(function (Vendor $record) {
                        // Находим соответствующего InviteVendor с таким же именем
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        return $inviteVendor->inviteAccounts()->count();
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(
                            SELECT COUNT(*)
                            FROM invite_accounts ia
                            JOIN invite_vendors iv ON ia.invite_vendor_id = iv.id
                            WHERE iv.name = vendors.name
                        ) {$direction}");
                    }),

                TextColumn::make('total_invites')
                    ->label('Сумма инвайтов')
                    ->state(function (Vendor $record) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        return $inviteVendor->inviteAccounts()->sum('stats_invites_count');
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(
                            SELECT COALESCE(SUM(stats_invites_count), 0)
                            FROM invite_accounts ia
                            JOIN invite_vendors iv ON ia.invite_vendor_id = iv.id
                            WHERE iv.name = vendors.name
                        ) {$direction}");
                    }),

                TextColumn::make('invites_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->state(function (Vendor $record) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        // Потрачено на все аккаунты
                        return $inviteVendor->inviteAccounts()->sum('price');
                    })

                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $geoCondition = '';
                        $geoParams = [];

                        if (!empty($geoFilters)) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND ia.geo IN ($placeholders)";
                            $geoParams = $geoFilters;
                        }

                        return $query->orderByRaw("(
                            SELECT 
                                CASE 
                                    WHEN COUNT(ia.id) = 0 THEN 0
                                    ELSE COUNT(ia.id) * 
                                         (COALESCE(SUM(ia.stats_invites_count), 0) / COUNT(ia.id)) * 
                                         (
                                            SELECT 
                                                CASE 
                                                    WHEN COUNT(ia2.id) = 0 OR AVG(ia2.stats_invites_count) = 0 THEN 0
                                                    ELSE CAST(SUM(ia2.price) AS DECIMAL(10,2)) / 
                                                         (CAST(AVG(ia2.stats_invites_count) AS DECIMAL(10,2)) * COUNT(ia2.id))
                                                END
                                            FROM invite_accounts ia2
                                            WHERE ia2.invite_vendor_id = iv.id
                                            $geoCondition
                                         )
                                END
                            FROM invite_accounts ia
                            JOIN invite_vendors iv ON ia.invite_vendor_id = iv.id
                            WHERE iv.name = vendors.name
                        ) {$direction}", $geoParams);
                    }),

                // Исправленная колонка invites_earned
                TextColumn::make('invites_earned')
                    ->label('Заработано')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        $soldPrice = (float)(request('tableFilters.sold_price.invite_sold_price') ?? session('tableFilters.stats.sold_price.invite_sold_price', 0));

                        // Правильно: считаем сумму инвайтов только по выжившим аккаунтам (type = 'valid')
                        $totalInvites = $inviteVendor->inviteAccounts()->where('type', 'valid')->sum('stats_invites_count');

                        return $totalInvites * $soldPrice;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $soldPrice = (float)(request('tableFilters.sold_price.invite_sold_price') ?? session('tableFilters.stats.sold_price.invite_sold_price', 0));

                        return $query->orderByRaw("(
                            SELECT 
                                CASE 
                                    WHEN COUNT(ia.id) = 0 THEN 0
                                    ELSE COUNT(ia.id) * 
                                         (COALESCE(SUM(ia.stats_invites_count), 0) / COUNT(ia.id)) * 
                                         ?
                                END
                            FROM invite_accounts ia
                            JOIN invite_vendors iv ON ia.invite_vendor_id = iv.id
                            WHERE iv.name = vendors.name
                        ) {$direction}", [$soldPrice]);
                    }),

                TextColumn::make('total_profit')
                    ->label('Итог')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();

                        // Потрачено (выживаемость)
                        $survivalSpent = $record->accounts()->sum('price');
                        // Потрачено (инвайты)
                        $inviteSpent = $inviteVendor ? $inviteVendor->inviteAccounts()->sum('price') : 0;

                        // Заработано (выживаемость)
                        $survivalSoldPrice = (float)(request('tableFilters.sold_price.survival_sold_price') ?? session('tableFilters.stats.sold_price.survival_sold_price', 0));
                        $validAccounts = $record->accounts()->where('type', 'valid')->count();
                        $survivalEarned = $validAccounts * $survivalSoldPrice;

                        // Заработано (инвайты)
                        $inviteSoldPrice = (float)(request('tableFilters.sold_price.invite_sold_price') ?? session('tableFilters.stats.sold_price.invite_sold_price', 0));
                        $inviteEarned = $inviteVendor
                            ? $inviteVendor->inviteAccounts()->where('type', 'valid')->sum('stats_invites_count') * $inviteSoldPrice
                            : 0;

                        $totalEarned = $survivalEarned + $inviteEarned;
                        $totalSpent = $survivalSpent + $inviteSpent;

                        return $totalEarned - $totalSpent;
                    })

                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Получаем актуальные значения цен из запроса (из фильтров)
                        $survivalSoldPrice = (float)(request('tableFilters.sold_price.survival_sold_price') ?? session('tableFilters.stats.sold_price.survival_sold_price', 0));
                        $inviteSoldPrice = (float)(request('tableFilters.sold_price.invite_sold_price') ?? session('tableFilters.stats.sold_price.invite_sold_price', 0));

                        // Получаем гео фильтры для корректного расчета средней цены инвайта
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $geoCondition = '';
                        $geoParams = [];

                        if (!empty($geoFilters)) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND ia.geo IN ($placeholders)";
                            $geoParams = $geoFilters;
                        }

                        return $query
                            ->withCount('accounts')
                            ->withCount(['accounts as valid_accounts_count' => function (Builder $q) {
                                $q->where('type', 'valid');
                            }])
                            ->withSum('accounts', 'price')
                            ->orderByRaw("(
                                /* Заработано выживаемость */
                                (CASE 
                                    WHEN accounts_count = 0 THEN 0 
                                    ELSE accounts_count * (valid_accounts_count / accounts_count) * ? 
                                END)
                                +
                                /* Заработано инвайты */
                                (
                                    SELECT 
                                        CASE 
                                            WHEN COUNT(ia.id) = 0 THEN 0
                                            ELSE COUNT(ia.id) * 
                                                 (COALESCE(SUM(ia.stats_invites_count), 0) / COUNT(ia.id)) * 
                                                 ?
                                        END
                                    FROM invite_accounts ia
                                    JOIN invite_vendors iv ON ia.invite_vendor_id = iv.id
                                    WHERE iv.name = vendors.name
                                )
                                -
                                /* Потрачено выживаемость */
                                (CASE 
                                    WHEN accounts_count = 0 THEN 0 
                                    ELSE accounts_count * (accounts_sum_price / accounts_count) 
                                END)
                                -
                                /* Потрачено инвайты */
                                (
                                    SELECT 
                                        CASE 
                                            WHEN COUNT(ia.id) = 0 THEN 0
                                            ELSE COUNT(ia.id) * 
                                                 (COALESCE(SUM(ia.stats_invites_count), 0) / COUNT(ia.id)) * 
                                                 (
                                                    SELECT 
                                                        CASE 
                                                            WHEN COUNT(ia2.id) = 0 OR AVG(ia2.stats_invites_count) = 0 THEN 0
                                                            ELSE CAST(SUM(ia2.price) AS DECIMAL(10,2)) / 
                                                                 (CAST(AVG(ia2.stats_invites_count) AS DECIMAL(10,2)) * COUNT(ia2.id))
                                                        END
                                                    FROM invite_accounts ia2
                                                    WHERE ia2.invite_vendor_id = iv.id
                                                    $geoCondition
                                                 )
                                        END
                                    FROM invite_accounts ia
                                    JOIN invite_vendors iv ON ia.invite_vendor_id = iv.id
                                    WHERE iv.name = vendors.name
                                )
                            ) {$direction}", array_merge([$survivalSoldPrice, $inviteSoldPrice], $geoParams));
                    }),
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
                            // Сохраняем даты фильтра в сессии для использования в расчетах
                            session(['tableFilters.stats.date_from' => $data['date_from'] ?? null]);
                            session(['tableFilters.stats.date_to' => $data['date_to'] ?? null]);

                            $query->whereHas('accounts', function ($q) use ($data) {
                                if (!empty($data['date_from'])) {
                                    $q->whereDate('session_created_at', '>=', $data['date_from']);
                                }
                                if (!empty($data['date_to'])) {
                                    $q->whereDate('session_created_at', '<=', $data['date_to']);
                                }
                            });
                        } else {
                            // Если фильтр очищен, удаляем значения из сессии
                            session()->forget(['tableFilters.stats.date_from', 'tableFilters.stats.date_to']);
                        }
                        return $query;
                    }),

                Filter::make('sold_price')
                    ->form([
                        TextInput::make('survival_sold_price')
                            ->label('Цена продажи для выживаемости')
                            ->numeric()
                            ->live(),
                        TextInput::make('invite_sold_price')
                            ->label('Цена продажи для инвайтов')
                            ->numeric()
                            ->live()
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['survival_sold_price'])) {
                            session(['tableFilters.stats.sold_price.survival_sold_price' => $data['survival_sold_price']]);
                        }
                        if (isset($data['invite_sold_price'])) {
                            session(['tableFilters.stats.sold_price.invite_sold_price' => $data['invite_sold_price']]);
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
