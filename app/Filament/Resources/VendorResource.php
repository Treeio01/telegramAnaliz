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
    protected static ?string $navigationLabel = 'Статистика';
    protected static ?string $navigationGroup = 'Управление';
    protected static ?string $title = 'Статистика';


    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Извлекаем значения фильтров из запроса
                $geoFilter = request()->input('tableFilters.geo.geo', []);
                $sessionFromFilter = request()->input('tableFilters.session_created_at_range.session_created_from');
                $sessionToFilter = request()->input('tableFilters.session_created_at_range.session_created_to');

                return $query->withCount([
                    // Всего аккаунтов
                    'accounts' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // Валид
                    'accounts as valid_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('type', 'valid');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // Невалид
                    'accounts as dead_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('type', 'dead');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // Спам (любые кроме 'free')
                    'accounts as spam_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('spamblock', '!=', 'free');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // СпамВалид
                    'accounts as spam_valid_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('type', 'valid')->where('spamblock', '!=', 'free');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // СпамМертвые
                    'accounts as spam_dead_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('type', 'dead')->where('spamblock', '!=', 'free');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // Чистые (только free)
                    'accounts as clean_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('spamblock', 'free');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // Чистые валидные
                    'accounts as clean_valid_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('type', 'valid')->where('spamblock', 'free');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                    // Чистые невалидные
                    'accounts as clean_dead_accounts_count' => function ($q) use ($geoFilter, $sessionFromFilter, $sessionToFilter) {
                        $q->where('type', 'dead')->where('spamblock', 'free');
                        if (!empty($geoFilter)) $q->whereIn('geo', $geoFilter);
                        if ($sessionFromFilter) $q->whereDate('session_created_at', '>=', $sessionFromFilter);
                        if ($sessionToFilter) $q->whereDate('session_created_at', '<=', $sessionToFilter);
                    },
                ]);
            })


            ->columns([
                TextColumn::make('name')->label('Продавец')->searchable()->sortable(),
                TextColumn::make('accounts_count')->label('Всего аккаунтов'),
                TextColumn::make('valid_accounts_count')->label('Валид'),
                TextColumn::make('dead_accounts_count')->label('Невалид'),
                TextColumn::make('survival_rate')
                    ->label('Выживаемость')
                    ->state(fn($record) => $record->accounts_count ? round($record->valid_accounts_count / $record->accounts_count * 100, 2) : 0),
                TextColumn::make('spam_accounts_count')->label('Спам'),
                TextColumn::make('spam_valid_accounts_count')->label('СпамV'),
                TextColumn::make('spam_dead_accounts_count')->label('СпамM'),
                TextColumn::make('clean_accounts_count')->label('Чист'),
                TextColumn::make('clean_valid_accounts_count')->label('ЧистV'),
                TextColumn::make('clean_dead_accounts_count')->label('ЧистM'),
                TextColumn::make('clean_percent_accounts')
                    ->label('Чист%')
                    ->state(fn($record) => $record->clean_accounts_count ? round($record->clean_valid_accounts_count / $record->clean_accounts_count * 100, 2) : 0),


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
                            ->live()
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
