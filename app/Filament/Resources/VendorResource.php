<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorResource\Pages;
use App\Models\Vendor;
use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Продавцы';
    protected static ?string $navigationGroup = 'Управление';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->withCount([
                        'accounts',
                        'accounts as alive_accounts_count' => function (Builder $q) {
                            $q->where('spamblock', 'free');
                        }
                    ]);
            })


            ->columns([
                TextColumn::make('name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable()->url(fn(Vendor $record): string => route('vendor.profile', $record->id)),

                TextColumn::make('accounts_count')
                    ->label('Всего аккаунтов')
                    ->sortable(),

                TextColumn::make('avg_invites')
                    ->label('Среднее кол-во инвайтов')
                    ->state(function (Vendor $record) {
                        $avg = $record->accounts()->avg('stats_invites_count');
                        return $avg ? round($avg, 2) : 0;
                    }),
                    // Убираем sortable(), чтобы не было ошибки SQL

                TextColumn::make('survival_rate')
                    ->label('Выживаемость (%)')
                    ->state(function (Vendor $record) {
                        $alive = $record->accounts()->where('spamblock', 'free')->count();
                        $total = $record->accounts_count ?? 0;

                        if ($total === 0) {
                            return 0;
                        }

                        return round(($alive / $total) * 100, 2);
                    })
                    ->color(fn($state) => match (true) {
                        $state < 50 => 'danger',
                        $state < 80 => 'warning',
                        default => 'success',
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
                            return $query->whereExists(function ($sub) use ($min) {
                                $sub->selectRaw(1)
                                    ->from('accounts')
                                    ->whereColumn('accounts.vendor_id', 'vendors.id')
                                    ->groupBy('accounts.vendor_id')
                                    ->havingRaw('COUNT(*) >= ?', [$min]);
                            });
                        }
                        return $query;
                    }),

                Filter::make('survival_rate')
                    ->form([
                        TextInput::make('survival_rate')
                            ->numeric()
                            ->label('Мин. выживаемость (%)')
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['survival_rate'])) {
                            $min = (int) $data['survival_rate'];

                            return $query->whereRaw("
                                (SELECT CASE 
                                    WHEN COUNT(*) > 0 
                                    THEN (SUM(CASE WHEN spamblock = 'free' THEN 1 ELSE 0 END) / COUNT(*)) * 100 
                                    ELSE 0 
                                END
                                FROM accounts 
                                WHERE accounts.vendor_id = vendors.id
                                ) >= ?
                            ", [$min]);
                        }
                        return $query;
                    }),

                Filter::make('geo')
                    ->form([
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
                                $q->where('geo', $geo);
                            });
                        }
                        return $query;
                    }),

            ])
            ->defaultSort('id', 'desc');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Имя продавца')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
