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
                    ->state('📋')
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
                    ->state(function (Vendor $record, $livewire) {
                        $filters = $livewire->tableFilters;
                        $dateFrom = $filters['date']['date_from'] ?? null;
                        $dateTo   = $filters['date']['date_to'] ?? null;

                        $accountsQuery = $record->accounts();
                        if ($dateFrom) {
                            $accountsQuery->where('session_created_at', '>=', $dateFrom);
                        }
                        if ($dateTo) {
                            $accountsQuery->where('session_created_at', '<=', $dateTo);
                        }

                        $total = $accountsQuery->count();
                        if ($total === 0) return 0;

                        $valid = (clone $accountsQuery)->where('type', 'valid')->count();
                        return round(($valid / $total) * 100, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount('accounts')
                            ->withCount(['accounts as valid_accounts_count' => fn($q) => $q->where('type', 'valid')])
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
                    ->state(function (Vendor $record, $livewire) {
                        $filters = $livewire->tableFilters;
                        $dateFrom = $filters['date']['date_from'] ?? null;
                        $dateTo   = $filters['date']['date_to'] ?? null;

                        $accountsQuery = $record->accounts();
                        if ($dateFrom) {
                            $accountsQuery->where('session_created_at', '>=', $dateFrom);
                        }
                        if ($dateTo) {
                            $accountsQuery->where('session_created_at', '<=', $dateTo);
                        }

                        $totalAccounts = $accountsQuery->count();
                        $totalPrice = (clone $accountsQuery)->sum('price');
                        $avgPrice = $totalAccounts > 0 ? ($totalPrice / $totalAccounts) : 0;

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
                    ->state(function (Vendor $record, $livewire) {
                        $filters = $livewire->tableFilters;
                        $soldPrice = (float)($filters['sold_price']['survival_sold_price'] ?? 0);
                        $totalAccounts = $record->accounts_count ?? 0;
                        $validAccounts = $record->valid_accounts_count ?? 0;

                        $survivalPercent = $totalAccounts > 0
                            ? $validAccounts / $totalAccounts
                            : 0;

                        return $totalAccounts * $survivalPercent * $soldPrice;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount('accounts')
                            ->withCount(['accounts as valid_accounts_count' => fn($q) => $q->where('type', 'valid')])
                            ->orderByRaw("CASE WHEN accounts_count = 0 THEN 0 ELSE accounts_count * (valid_accounts_count / accounts_count) END {$direction}");
                    }),

                TextColumn::make('avg_invite_price')
                    ->label('Средняя цена инвайта')
                    ->money('RUB')
                    ->state(function (Vendor $record, $livewire) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        $geoFilters = $livewire->tableFilters['geo']['geo'] ?? [];
                        $params = [$inviteVendor->id];
                        $geoCondition = '';

                        if (!empty($geoFilters)) {
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
                    }),

                TextColumn::make('invites_accounts_count')
                    ->label('Кол-во акков')
                    ->state(fn(Vendor $record) =>
                        optional(\App\Models\InviteVendor::where('name', $record->name)->first())
                            ?->inviteAccounts()->count() ?? 0
                    ),

                TextColumn::make('total_invites')
                    ->label('Сумма инвайтов')
                    ->state(fn(Vendor $record) =>
                        optional(\App\Models\InviteVendor::where('name', $record->name)->first())
                            ?->inviteAccounts()->sum('stats_invites_count') ?? 0
                    ),

                TextColumn::make('invites_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->state(fn(Vendor $record) =>
                        optional(\App\Models\InviteVendor::where('name', $record->name)->first())
                            ?->inviteAccounts()->sum('price') ?? 0
                    ),

                TextColumn::make('invites_earned')
                    ->label('Заработано')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record, $livewire) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        if (!$inviteVendor) return 0;

                        $filters = $livewire->tableFilters;
                        $soldPrice = (float)($filters['sold_price']['invite_sold_price'] ?? 0);

                        $totalInvites = $inviteVendor->inviteAccounts()
                            ->where('type', 'valid')
                            ->sum('stats_invites_count');

                        return $totalInvites * $soldPrice;
                    }),

                TextColumn::make('total_profit')
                    ->label('Итог')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record, $livewire) {
                        $inviteVendor = \App\Models\InviteVendor::where('name', $record->name)->first();
                        $filters = $livewire->tableFilters;

                        $survivalSpent = $record->accounts()->sum('price');
                        $inviteSpent   = $inviteVendor ? $inviteVendor->inviteAccounts()->sum('price') : 0;

                        $survivalSoldPrice = (float)($filters['sold_price']['survival_sold_price'] ?? 0);
                        $validAccounts     = $record->accounts()->where('type', 'valid')->count();
                        $survivalEarned    = $validAccounts * $survivalSoldPrice;

                        $inviteSoldPrice = (float)($filters['sold_price']['invite_sold_price'] ?? 0);
                        $inviteEarned    = $inviteVendor
                            ? $inviteVendor->inviteAccounts()->where('type', 'valid')->sum('stats_invites_count') * $inviteSoldPrice
                            : 0;

                        return ($survivalEarned + $inviteEarned) - ($survivalSpent + $inviteSpent);
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
                    ->query(fn (Builder $query, array $data) =>
                        !empty($data['geo'])
                            ? $query->whereHas('accounts', fn($q) => $q->whereIn('geo', $data['geo']))
                            : $query
                    ),

                Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('От'),
                        Forms\Components\DatePicker::make('date_to')->label('До'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['date_from'] ?? null) {
                            $query->whereHas('accounts', fn($q) => $q->whereDate('session_created_at', '>=', $data['date_from']));
                        }
                        if ($data['date_to'] ?? null) {
                            $query->whereHas('accounts', fn($q) => $q->whereDate('session_created_at', '<=', $data['date_to']));
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
                    ->query(fn (Builder $query, array $data) => $query),
            ])
            ->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStats::route('/'),
        ];
    }
}
