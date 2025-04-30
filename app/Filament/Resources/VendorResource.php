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
use App\Models\GeoPreset;
class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Статистика выживаемости';
    protected static ?string $navigationGroup = 'Управление';
    protected static ?string $title = 'Статистика выживаемости';


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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable()
                    ->url(fn(Vendor $record): string => route('vendor.profile', $record->id)),

                TextColumn::make('accounts_count')
                    ->label('Всего аккаунтов')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('accounts_count', $direction);
                    }),

                TextColumn::make('valid_accounts_count')
                    ->label('Валид')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "alive"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()->where('type', 'alive')->count();
                    }),

                TextColumn::make('dead_accounts_count')
                    ->label('Невалид')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "dead"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()->where('type', 'dead')->count();
                    }),

                TextColumn::make('spam_accounts_count')
                    ->label('Спам')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND spamblock != "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('spamblock', '!=', 'free')
                            ->count();
                    }),

                TextColumn::make('spam_valid_accounts_count')
                    ->label('СпамV')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "alive" 
                                AND spamblock != "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('type', 'alive')
                            ->where('spamblock', '!=', 'free')
                            ->count();
                    }),

                TextColumn::make('spam_dead_accounts_count')
                    ->label('СпамM')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "dead" 
                                AND spamblock != "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('type', 'dead')
                            ->where('spamblock', '!=', 'free')
                            ->count();
                    }),

                TextColumn::make('spam_percent_accounts')
                    ->label('Спам %')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            CASE 
                                WHEN (SELECT COUNT(*) FROM accounts WHERE accounts.vendor_id = vendors.id) = 0 THEN 0
                                ELSE (
                                    SELECT (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM accounts WHERE accounts.vendor_id = vendors.id))
                                    FROM accounts 
                                    WHERE accounts.vendor_id = vendors.id 
                                    AND spamblock != "free"
                                )
                            END ' . $direction
                        );
                    })->color(function (Vendor $record) {
                        $total = $record->accounts_count ?? 0;
                        if ($total === 0) return 'gray';
                        $spam = $record->accounts()->where('spamblock', '!=', 'free')->count();
                        $percent = round(($spam / $total) * 100, 2);
                        if ($percent > 75) {
                            return 'danger';
                        } elseif ($percent > 25) {
                            return 'warning'; // оранжевое
                        } else {
                            return 'success';
                        }
                    })
                    ->state(function (Vendor $record) {
                        $total = $record->accounts_count ?? 0;
                        if ($total === 0) return 0;
                        $spam = $record->accounts()->where('spamblock', '!=', 'free')->count();
                        return round(($spam / $total) * 100, 2);
                    }),

                TextColumn::make('clean_accounts_count')
                    ->label('Чист')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND spamblock = "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()->where('spamblock', 'free')->count();
                    }),

                TextColumn::make('clean_valid_accounts_count')
                    ->label('ЧистV')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "alive" 
                                AND spamblock = "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('type', 'alive')
                            ->where('spamblock', 'free')
                            ->count();
                    }),

                TextColumn::make('clean_dead_accounts_count')
                    ->label('ЧистM')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "dead" 
                                AND spamblock = "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('type', 'dead')
                            ->where('spamblock', 'free')
                            ->count();
                    }),

                TextColumn::make('clean_percent_accounts')
                    ->label('Чист%')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            CASE 
                                WHEN (SELECT COUNT(*) FROM accounts WHERE accounts.vendor_id = vendors.id) = 0 THEN 0
                                ELSE (
                                    SELECT (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM accounts WHERE accounts.vendor_id = vendors.id))
                                    FROM accounts 
                                    WHERE accounts.vendor_id = vendors.id 
                                    AND spamblock = "free"
                                )
                            END ' . $direction
                        );
                    })->color(function (Vendor $record) {
                        $total = $record->accounts_count ?? 0;
                        if ($total === 0) return 'gray';
                        $clean = $record->accounts()->where('spamblock', 'free')->count();
                        $percent = round(($clean / $total) * 100, 2);
                        if ($percent < 25) {
                            return 'danger';
                        } elseif ($percent < 75) {
                            return 'warning'; // оранжевое
                        } else {
                            return 'success';
                        }
                    })
                    ->state(function (Vendor $record) {
                        $total = $record->accounts_count ?? 0;
                        if ($total === 0) return 0;
                        $clean = $record->accounts()->where('spamblock', 'free')->count();
                        return round(($clean / $total) * 100, 2);
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

                Filter::make('session_created_at_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('session_created_from')
                            ->label('Сессия от'),
                        \Filament\Forms\Components\DatePicker::make('session_created_to')
                            ->label('Сессия до'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['session_created_from']) || !empty($data['session_created_to'])) {
                            $query->whereHas('accounts', function ($q) use ($data) {
                                if (!empty($data['session_created_from'])) {
                                    $q->whereDate('session_created_at', '>=', $data['session_created_from']);
                                }
                                if (!empty($data['session_created_to'])) {
                                    $q->whereDate('session_created_at', '<=', $data['session_created_to']);
                                }
                            });
                        }
                        return $query;
                    }),

            ]);
            
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
