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
            ->modifyQueryUsing(function (Builder $query, $livewire) {
                $filters  = $livewire->tableFilters;
                $dateFrom = $filters['date']['date_from'] ?? null;
                $dateTo   = $filters['date']['date_to'] ?? null;

                // survival counts
                $query
                    ->withCount([
                        'accounts as accounts_count' => function ($q) use ($dateFrom, $dateTo) {
                            if ($dateFrom) $q->where('session_created_at', '>=', $dateFrom);
                            if ($dateTo)   $q->where('session_created_at', '<=', $dateTo);
                        },
                        'accounts as valid_accounts_count' => function ($q) use ($dateFrom, $dateTo) {
                            $q->where('type', 'valid');
                            if ($dateFrom) $q->where('session_created_at', '>=', $dateFrom);
                            if ($dateTo)   $q->where('session_created_at', '<=', $dateTo);
                        },
                    ])
                    ->withSum(['accounts as accounts_sum_price' => function ($q) use ($dateFrom, $dateTo) {
                        if ($dateFrom) $q->where('session_created_at', '>=', $dateFrom);
                        if ($dateTo)   $q->where('session_created_at', '<=', $dateTo);
                    }], 'price');

                // invites counts
                $query->addSelect([
                    'invites_accounts_count' => \App\Models\InviteVendor::selectRaw('COUNT(ia.id)')
                        ->from('invite_vendors as iv')
                        ->join('invite_accounts as ia', 'ia.invite_vendor_id', '=', 'iv.id')
                        ->whereColumn('iv.name', 'vendors.name'),
                    'total_invites' => \App\Models\InviteVendor::selectRaw('COALESCE(SUM(ia.stats_invites_count),0)')
                        ->from('invite_vendors as iv')
                        ->join('invite_accounts as ia', 'ia.invite_vendor_id', '=', 'iv.id')
                        ->whereColumn('iv.name', 'vendors.name'),
                    'invites_spent' => \App\Models\InviteVendor::selectRaw('COALESCE(SUM(ia.price),0)')
                        ->from('invite_vendors as iv')
                        ->join('invite_accounts as ia', 'ia.invite_vendor_id', '=', 'iv.id')
                        ->whereColumn('iv.name', 'vendors.name'),
                    'valid_invites' => \App\Models\InviteVendor::selectRaw('COALESCE(SUM(ia.stats_invites_count),0)')
                        ->from('invite_vendors as iv')
                        ->join('invite_accounts as ia', 'ia.invite_vendor_id', '=', 'iv.id')
                        ->whereColumn('iv.name', 'vendors.name')
                        ->where('ia.type', 'valid'),
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
                    ->state(fn(Vendor $record) =>
                        $record->accounts_count > 0
                            ? round(($record->valid_accounts_count / $record->accounts_count) * 100, 2)
                            : 0
                    )
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->sortable(query: fn($q, $dir) =>
                        $q->orderByRaw("CASE WHEN accounts_count = 0 THEN 0 ELSE (valid_accounts_count * 100.0 / accounts_count) END {$dir}")
                    ),

                TextColumn::make('accounts_count')
                    ->label('Кол-во акков')
                    ->sortable(),

                TextColumn::make('survival_spent')
                    ->label('Потрачено')
                    ->money('RUB')
                    ->state(fn(Vendor $record) => (float)($record->accounts_sum_price ?? 0))
                    ->sortable(query: fn($q, $dir) => $q->orderBy('accounts_sum_price', $dir)),

                TextColumn::make('survival_earned')
                    ->label('Заработано')
                    ->money('RUB')
                    ->color(fn($state) => $state >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record, $livewire) {
                        $soldPrice = (float)($livewire->tableFilters['sold_price']['survival_sold_price'] ?? 0);
                        return ($record->valid_accounts_count ?? 0) * $soldPrice;
                    })
                    ->sortable(query: fn($q, $dir) => $q->orderBy('valid_accounts_count', $dir)),

                TextColumn::make('invites_accounts_count')
                    ->label('Кол-во инвайт-акков')
                    ->state(fn($r) => $r->invites_accounts_count ?? 0)
                    ->sortable(),

                TextColumn::make('total_invites')
                    ->label('Сумма инвайтов')
                    ->state(fn($r) => $r->total_invites ?? 0)
                    ->sortable(),

                TextColumn::make('invites_spent')
                    ->label('Потрачено на инвайты')
                    ->money('RUB')
                    ->state(fn($r) => $r->invites_spent ?? 0)
                    ->sortable(),

                TextColumn::make('invites_earned')
                    ->label('Заработано на инвайтах')
                    ->money('RUB')
                    ->color(fn($s) => $s >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record, $livewire) {
                        $price = (float)($livewire->tableFilters['sold_price']['invite_sold_price'] ?? 0);
                        return ($record->valid_invites ?? 0) * $price;
                    })
                    ->sortable(query: fn($q, $dir) => $q->orderBy('valid_invites', $dir)),

                TextColumn::make('total_profit')
                    ->label('Итог')
                    ->money('RUB')
                    ->color(fn($s) => $s >= 0 ? 'success' : 'danger')
                    ->state(function (Vendor $record, $livewire) {
                        $sPrice = (float)($livewire->tableFilters['sold_price']['survival_sold_price'] ?? 0);
                        $iPrice = (float)($livewire->tableFilters['sold_price']['invite_sold_price'] ?? 0);

                        $survivalEarned = ($record->valid_accounts_count ?? 0) * $sPrice;
                        $inviteEarned   = ($record->valid_invites ?? 0) * $iPrice;
                        $spent          = (float)($record->accounts_sum_price ?? 0) + (float)($record->invites_spent ?? 0);

                        return ($survivalEarned + $inviteEarned) - $spent;
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
                            ),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['geo'])) {
                            $query->whereHas('accounts', function ($q) use ($data) {
                                $q->whereIn('geo', $data['geo']);
                            });
                        }
                        return $query;
                    }),

                Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')->label('От'),
                        Forms\Components\DatePicker::make('date_to')->label('До'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['date_from'])) {
                            $query->whereHas('accounts', fn($q) =>
                                $q->whereDate('session_created_at', '>=', $data['date_from'])
                            );
                        }
                        if (!empty($data['date_to'])) {
                            $query->whereHas('accounts', fn($q) =>
                                $q->whereDate('session_created_at', '<=', $data['date_to'])
                            );
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
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Тут просто возвращаем $query,
                        // значения будут доступны через $livewire->tableFilters
                        return $query;
                    }),
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
