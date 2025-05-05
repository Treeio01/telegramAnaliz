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
                        'accounts as accounts_count',
                        // Исправлено: valid_accounts_count теперь считает type = 'valid'
                        'accounts as valid_accounts_count' => function (Builder $q) {
                            $q->where('type', 'valid');
                        },
                        'accounts as dead_accounts_count' => function (Builder $q) {
                            $q->where('type', 'dead');
                        },
                        'accounts as spam_accounts_count' => function (Builder $q) {
                            $q->where('spamblock', '!=', 'free');
                        },
                        'accounts as spam_valid_accounts_count' => function (Builder $q) {
                            $q->where('type', 'valid')->where('spamblock', '!=', 'free');
                        },
                        // Добавляем withCount для clean_accounts_count
                        'accounts as clean_accounts_count' => function (Builder $q) {
                            $q->where('spamblock', 'free');
                        },
                    ]);
            })

            ->columns([
                TextColumn::make('copy_name')
                ->label('')
                ->state('Копировать')
                ->copyable()
                ->copyMessageDuration(2000)
                ->copyMessage('Скопировано')
                ->copyableState(fn(TempVendor $record) => $record->name),
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
                                AND type = "valid"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()->where('type', 'valid')->count();
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

                TextColumn::make('survival_rate')
                    ->label('Выживаемость')
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
                    // Исправлено: сортировка survival_rate по valid_accounts_count / accounts_count
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        // Используем valid_accounts_count и accounts_count, которые уже withCount-нуты
                        return $query->orderByRaw(
                            'CASE WHEN accounts_count = 0 THEN 0 ELSE (valid_accounts_count * 100.0 / accounts_count) END ' . $direction
                        );
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
                                AND type = "valid" 
                                AND spamblock != "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('type', 'valid')
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
                                WHEN (
                                    SELECT COUNT(*) 
                                    FROM accounts 
                                    WHERE accounts.vendor_id = vendors.id 
                                    AND type = "valid" 
                                    AND spamblock != "free"
                                ) = 0 THEN 0
                                ELSE (
                                    SELECT (
                                        COUNT(*) * 100.0 / 
                                        (SELECT COUNT(*) FROM accounts WHERE accounts.vendor_id = vendors.id AND type = "valid" AND spamblock != "free")
                                    )
                                    FROM accounts 
                                    WHERE accounts.vendor_id = vendors.id 
                                    AND spamblock != "free"
                                )
                            END ' . $direction
                        );
                    })
                    ->color(function (Vendor $record) {
                        $validSpam = $record->accounts()
                            ->where('type', 'valid')
                            ->where('spamblock', '!=', 'free')
                            ->count();
                        if ($validSpam === 0) return 'gray';
                        $spam = $record->accounts()->where('spamblock', '!=', 'free')->count();
                        $percent = round(($spam / $validSpam) * 100, 2);
                        
                        return \App\Models\Settings::getColorForValue('spam_percent_accounts', $percent) ?? 'gray';
                    })
                    ->state(function (Vendor $record) {
                        $validSpam = $record->accounts()
                            ->where('type', 'valid')
                            ->where('spamblock', '!=', 'free')
                            ->count();
                        if ($validSpam === 0) return 0;
                        $spam = $record->accounts()->where('spamblock', '!=', 'free')->count();
                        return round(($spam / $validSpam) * 100, 2);
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
                        // Используем withCount, чтобы не делать лишний запрос
                        return $record->clean_accounts_count ?? $record->accounts()->where('spamblock', 'free')->count();
                    }),

                TextColumn::make('clean_valid_accounts_count')
                    ->label('ЧистV')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            '
                            (
                                SELECT COUNT(*) FROM accounts 
                                WHERE accounts.vendor_id = vendors.id 
                                AND type = "valid" 
                                AND spamblock = "free"
                            ) ' . $direction
                        );
                    })
                    ->state(function (Vendor $record) {
                        return $record->accounts()
                            ->where('type', 'valid')
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
                        // Используем withCount clean_accounts_count и clean_valid_accounts_count
                        return $query->orderByRaw(
                            'CASE WHEN clean_accounts_count = 0 THEN 0 ELSE (clean_valid_accounts_count * 100.0 / clean_accounts_count) END ' . $direction
                        );
                    })
                    ->color(function (Vendor $record) {
                        $cleanTotal = $record->clean_accounts_count ?? $record->accounts()->where('spamblock', 'free')->count();
                        if ($cleanTotal === 0) return 'gray';
                        $cleanValid = $record->clean_valid_accounts_count ?? $record->accounts()->where('type', 'valid')->where('spamblock', 'free')->count();
                        $percent = round(($cleanValid / $cleanTotal) * 100, 2);
                        return \App\Models\Settings::getColorForValue('clean_percent_accounts', $percent) ?? 'gray';
                    })
                    ->state(function (Vendor $record) {
                        $cleanTotal = $record->clean_accounts_count ?? $record->accounts()->where('spamblock', 'free')->count();
                        if ($cleanTotal === 0) return 0;
                        $cleanValid = $record->clean_valid_accounts_count ?? $record->accounts()->where('type', 'valid')->where('spamblock', 'free')->count();
                        return round(($cleanValid / $cleanTotal) * 100, 2);
                    }),

                Tables\Columns\CheckboxColumn::make('del_user')
                    ->label('del_user')
                    ->sortable()

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
                        \Filament\Forms\Components\TextInput::make('survival_rate_min')
                            ->numeric()
                            ->label('Мин. выживаемость (%)')
                            ->default(null),
                        \Filament\Forms\Components\TextInput::make('survival_rate_max')
                            ->numeric()
                            ->label('Макс. выживаемость (%)')
                            ->default(null),
                    ])
                    ->query(function (Builder $query, array $data) {
                        $survivalRateQuery = $query;
                        
                        if (!empty($data['survival_rate_min'])) {
                            $min = (float) $data['survival_rate_min'];
                            $survivalRateQuery = $query->whereRaw("
                                (
                                    SELECT 
                                        CASE 
                                            WHEN COUNT(*) > 0 
                                            THEN (SUM(CASE WHEN type = 'valid' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) 
                                            ELSE 0 
                                        END
                                    FROM accounts 
                                    WHERE accounts.vendor_id = vendors.id
                                ) >= ?
                            ", [$min]);
                        }
                        
                        if (!empty($data['survival_rate_max'])) {
                            $max = (float) $data['survival_rate_max'];
                            $survivalRateQuery = $query->whereRaw("
                                (
                                    SELECT 
                                        CASE 
                                            WHEN COUNT(*) > 0 
                                            THEN (SUM(CASE WHEN type = 'valid' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) 
                                            ELSE 0 
                                        END
                                    FROM accounts 
                                    WHERE accounts.vendor_id = vendors.id
                                ) <= ?
                            ", [$max]);
                        }
                        
                        return $survivalRateQuery;
                    }),

                // Добавляем фильтр по проценту чистых аккаунтов
                Filter::make('clean_percent_accounts')
                    ->form([
                        TextInput::make('clean_percent_accounts')
                            ->numeric()
                            ->label('Мин. Чист%')
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['clean_percent_accounts'])) {
                            $min = (int) $data['clean_percent_accounts'];
                            // Аналогично survival_rate, только по spamblock = 'free'
                            return $query->whereRaw("
                                (
                                    SELECT 
                                        CASE 
                                            WHEN COUNT(*) > 0 
                                            THEN (SUM(CASE WHEN spamblock = 'free' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) 
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
                // Добавляем чекбокс в форму, если требуется
                \Filament\Forms\Components\Checkbox::make('del_user')
                    ->label('Удалить пользователя')
                    ->default(false)
                    ->helperText('Отметьте для удаления пользователя'),
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
