<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Upload;
use App\Models\TempVendor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\Vendor;
use App\Models\Account;
use App\Models\TempAccount;
use App\Models\GeoPreset;

class UploadProfile extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static string $view = 'filament.pages.upload-profile';
    public $uploadId;
    public Upload $upload;

    public function mount($id): void
    {
        $this->uploadId = $id;
        $this->upload = Upload::findOrFail($id);
    }

    public function table(Table $table): Table
    {
        // Получаем активные фильтры GEO, если они установлены
        $geoFilters = $this->tableFilters['geo']['geo'] ?? [];
        $hasGeoFilter = !empty($geoFilters);

        return $table
            ->query(function () use ($hasGeoFilter, $geoFilters) {
                $query = TempVendor::query();

                // Базовый запрос с учетом GEO фильтра
                if ($hasGeoFilter) {
                    $query->selectRaw('
                        temp_vendors.*,
                        COUNT(DISTINCT CASE WHEN ' . ($hasGeoFilter ? 'temp_accounts.geo IN ("' . implode('","', $geoFilters) . '")' : 'TRUE') . ' THEN temp_accounts.id ELSE NULL END) as total_accounts,
                        SUM(CASE WHEN temp_accounts.type = "valid" AND ' . ($hasGeoFilter ? 'temp_accounts.geo IN ("' . implode('","', $geoFilters) . '")' : 'TRUE') . ' THEN 1 ELSE 0 END) as total_valid
                    ');
                } else {
                    $query->selectRaw('
                        temp_vendors.*,
                        COUNT(temp_accounts.id) as total_accounts,
                        SUM(CASE WHEN temp_accounts.type = "valid" THEN 1 ELSE 0 END) as total_valid
                    ');
                }

                $query->leftJoin('temp_accounts', 'temp_vendors.id', '=', 'temp_accounts.temp_vendor_id')
                    ->groupBy('temp_vendors.id')
                    ->where('temp_vendors.upload_id', $this->uploadId);

                // Применяем withCount с учетом GEO фильтра
                $query->withCount([
                    'tempAccounts as temp_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as valid_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('type', 'valid');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as dead_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('type', 'dead');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as spam_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('spamblock', '!=', 'free');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as spam_valid_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('type', 'valid')->where('spamblock', '!=', 'free');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as spam_dead_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('type', 'dead')->where('spamblock', '!=', 'free');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as clean_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('spamblock', 'free');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as clean_valid_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('type', 'valid')->where('spamblock', 'free');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                    'tempAccounts as clean_dead_accounts_count' => function ($q) use ($hasGeoFilter, $geoFilters) {
                        $q->where('type', 'dead')->where('spamblock', 'free');
                        if ($hasGeoFilter) {
                            $q->whereIn('geo', $geoFilters);
                        }
                    },
                ]);

                return $query;
            })
            ->columns([
                TextColumn::make('copy_name')
                ->label('')
                ->state('📋')  // Эмодзи буфера обмена
                ->extraAttributes([
                    'x-data' => '{
                        copyToClipboard: function(text) {
                            try {
                                // Используем только запасной метод с document.execCommand
                                const textarea = document.createElement("textarea");
                                textarea.value = text;
                                textarea.style.position = "fixed";
                                textarea.style.opacity = "0";
                                document.body.appendChild(textarea);
                                textarea.select();
                                const success = document.execCommand("copy");
                                document.body.removeChild(textarea);
                                
                                if (success) {
                                    $dispatch("notify", {
                                        message: "Скопировано",
                                        timeout: 2000
                                    });
                                } else {
                                    $dispatch("notify", {
                                        message: "Не удалось скопировать",
                                        timeout: 2000
                                    });
                                }
                            } catch (e) {
                                console.error("Ошибка копирования:", e);
                                $dispatch("notify", {
                                    message: "Ошибка копирования",
                                    timeout: 2000
                                });
                            }
                        }
                    }',
                    'x-on:click' => 'copyToClipboard($el.getAttribute("data-copy-text"))',
                    'data-copy-text' => fn(TempVendor $record) => $record->name,
                    'class' => 'cursor-pointer',
                ]),
                TextColumn::make('name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable()
                    ->url(function (TempVendor $record) {
                        $vendor = \App\Models\Vendor::where('name', $record->name)->first();
                        if ($vendor) {
                            return route('vendor.profile', $vendor->id);
                        }
                        return null;
                    }),
                TextColumn::make('total_accounts')
                    ->label('Всего аккаунтов')
                    ->state(fn(TempVendor $record) => $record->total_accounts)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_accounts', $direction);
                    }),

                TextColumn::make('total_valid')
                    ->label('Валид')
                    ->state(fn(TempVendor $record) => $record->total_valid)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_valid', $direction);
                    }),

                TextColumn::make('dead_accounts_count')
                    ->label('Невалид')
                    ->state(fn(TempVendor $record) => $record->dead_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('dead_accounts_count', $direction);
                    }),

                TextColumn::make('survival_rate')
                    ->label('Выживаемость')
                    ->color(function (TempVendor $record) {
                        $total = $record->total_accounts ?? 0;
                        if ($total === 0)
                            return 'gray';
                        $valid = $record->total_valid ?? 0;
                        $percent = round(($valid / $total) * 100, 2);
                        return \App\Models\Settings::getColorForValue('survival_rate', $percent) ?? 'gray';
                    })
                    ->state(function (TempVendor $record) {
                        $valid = $record->total_valid ?? 0;
                        $total = $record->total_accounts ?? 0;
                        if ($total === 0) {
                            return 0;
                        }
                        return round(($valid / $total) * 100, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            "CASE WHEN total_accounts = 0 THEN 0 ELSE (total_valid * 100.0 / total_accounts) END $direction"
                        );
                    }),

                TextColumn::make('spam_accounts_count')
                    ->label('Спам')
                    ->state(fn(TempVendor $record) => $record->spam_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('spam_accounts_count', $direction);
                    }),

                TextColumn::make('spam_valid_accounts_count')
                    ->label('СпамV')
                    ->state(fn(TempVendor $record) => $record->spam_valid_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('spam_valid_accounts_count', $direction);
                    }),

                TextColumn::make('spam_dead_accounts_count')
                    ->label('СпамM')
                    ->state(fn(TempVendor $record) => $record->spam_dead_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('spam_dead_accounts_count', $direction);
                    }),

                TextColumn::make('spam_percent_accounts')
                    ->label('Спам %')
                    ->color(function (TempVendor $record) {
                        $total = $record->spam_accounts_count ?? 0;
                        if ($total === 0)
                            return 'gray';
                        $spam = $record->spam_valid_accounts_count ?? 0;
                        $percent = round(($spam / $total) * 100, 2);
                        return \App\Models\Settings::getColorForValue('spam_percent_accounts', $percent) ?? 'gray';
                    })
                    ->state(function (TempVendor $record) {
                        $total = $record->spam_accounts_count ?? 0;
                        if ($total === 0)
                            return 0;
                        $spam = $record->spam_valid_accounts_count ?? 0;
                        return round(($spam / $total) * 100, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            "CASE WHEN spam_accounts_count = 0 THEN 0 ELSE (spam_valid_accounts_count * 100.0 / spam_accounts_count) END $direction"
                        );
                    }),

                TextColumn::make('clean_accounts_count')
                    ->label('Чист')
                    ->state(fn(TempVendor $record) => $record->clean_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('clean_accounts_count', $direction);
                    }),

                TextColumn::make('clean_valid_accounts_count')
                    ->label('ЧистV')
                    ->state(fn(TempVendor $record) => $record->clean_valid_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('clean_valid_accounts_count', $direction);
                    }),

                TextColumn::make('clean_dead_accounts_count')
                    ->label('ЧистM')
                    ->state(fn(TempVendor $record) => $record->clean_dead_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('clean_dead_accounts_count', $direction);
                    }),

                TextColumn::make('clean_percent_accounts')
                    ->label('Чист%')
                    ->color(function (TempVendor $record) {
                        $total = $record->clean_accounts_count ?? 0;
                        if ($total === 0)
                            return 'gray';
                        $clean = $record->clean_valid_accounts_count ?? 0;
                        $percent = round(($clean / $total) * 100, 2);
                        return \App\Models\Settings::getColorForValue('clean_percent_accounts', $percent) ?? 'gray';
                    })
                    ->state(function (TempVendor $record) {
                        $total = $record->clean_accounts_count ?? 0;
                        if ($total === 0)
                            return 0;
                        $clean = $record->clean_valid_accounts_count ?? 0;
                        return round(($clean / $total) * 100, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            "CASE WHEN clean_accounts_count = 0 THEN 0 ELSE (clean_valid_accounts_count * 100.0 / clean_accounts_count) END $direction"
                        );
                    }),
                Tables\Columns\CheckboxColumn::make('del_user')
                    ->label('del_user')
                    ->sortable()
            ])
            ->filters([
                Tables\Filters\Filter::make('min_accounts')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('min_accounts')
                            ->numeric()
                            ->label('Мин. аккаунтов')
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['min_accounts'])) {
                            $min = (int) $data['min_accounts'];
                            // Исправлено: фильтр теперь корректно работает с whereHas
                            return $query->whereHas('tempAccounts', function ($q) use ($min) {
                                $q->select('temp_vendor_id')
                                    ->groupBy('temp_vendor_id')
                                    ->havingRaw('COUNT(*) >= ?', [$min]);
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('survival_rate')
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
                        $min = isset($data['survival_rate_min']) && $data['survival_rate_min'] !== null && $data['survival_rate_min'] !== '' ? (float)$data['survival_rate_min'] : null;
                        $max = isset($data['survival_rate_max']) && $data['survival_rate_max'] !== null && $data['survival_rate_max'] !== '' ? (float)$data['survival_rate_max'] : null;

                        if ($min !== null || $max !== null) {
                            // Формируем условие для havingRaw
                            $havingCondition = 'CASE WHEN COUNT(temp_accounts.id) = 0 THEN 0 ELSE (SUM(CASE WHEN temp_accounts.type = "valid" THEN 1 ELSE 0 END) * 100.0 / COUNT(temp_accounts.id)) END';

                            $validIdsQuery = DB::table('temp_vendors')
                                ->select('temp_vendors.id')
                                ->leftJoin('temp_accounts', 'temp_vendors.id', '=', 'temp_accounts.temp_vendor_id')
                                ->groupBy('temp_vendors.id');

                            if ($min !== null) {
                                $validIdsQuery->havingRaw("$havingCondition >= ?", [$min]);
                            }

                            if ($max !== null) {
                                $validIdsQuery->havingRaw("$havingCondition <= ?", [$max]);
                            }

                            $validIds = $validIdsQuery->pluck('id');

                            return $query->whereIn('temp_vendors.id', $validIds);
                        }

                        return $query;
                    }),

                Tables\Filters\Filter::make('geo')
                    ->form([
                        \Filament\Forms\Components\Select::make('preset')
                            ->label('Гео пресет')
                            ->options(\App\Models\GeoPreset::pluck('name', 'id'))
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $preset = \App\Models\GeoPreset::find($state);
                                    $set('geo', $preset ? $preset->geos : []);
                                }
                            }),
                        \Filament\Forms\Components\Select::make('geo')
                            ->label('Гео')
                            ->multiple()
                            ->searchable()
                            ->options(
                                \App\Models\TempAccount::query()
                                    ->whereNotNull('geo')
                                    ->distinct()
                                    ->pluck('geo', 'geo')
                                    ->toArray()
                            )
                    ])
                    ->query(function (Builder $query, array $data) {
                        // Сохраняем фильтры в сессии для доступа в основном запросе
                        session(['current_geo_filters' => $data['geo'] ?? []]);

                        if (!empty($data['geo'])) {
                            $geo = $data['geo'];
                            $query->whereHas('tempAccounts', function ($q) use ($geo) {
                                $q->whereIn('geo', $geo);
                            });

                            // Обновляем сессионную переменную с этими фильтрами,
                            // чтобы основной запрос знал о них
                            $this->tableFilters['geo']['geo'] = $geo;
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('session_created_date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('session_created_from')
                            ->label('Сессия от'),
                        \Filament\Forms\Components\DatePicker::make('session_created_to')
                            ->label('Сессия до'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['session_created_from']) || !empty($data['session_created_to'])) {
                            $query->whereHas('tempAccounts', function ($q) use ($data) {
                                if (!empty($data['session_created_from'])) {
                                    $q->whereDate('session_created_date', '>=', $data['session_created_from']);
                                }
                                if (!empty($data['session_created_to'])) {
                                    $q->whereDate('session_created_date', '<=', $data['session_created_to']);
                                }
                            });
                        }
                        return $query;
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save_to_main')
                ->label('Сохранить в основную базу')
                ->color('success')
                ->icon('heroicon-o-check')
                ->action('saveToMainDatabase')
                ->requiresConfirmation()
                ->modalHeading('Сохранить в основную базу?')
                ->modalDescription('Все данные будут перенесены в основную базу данных. Временные данные будут удалены.')
                ->modalSubmitActionLabel('Да, сохранить')
                ->modalCancelActionLabel('Отмена'),
        ];
    }

    public function saveToMainDatabase()
    {
        try {
            DB::transaction(function () {
                // Получаем все временные данные
                $tempVendors = TempVendor::with('tempAccounts')
                    ->where('upload_id', $this->uploadId)
                    ->get();

                foreach ($tempVendors as $tempVendor) {
                    // Создаем или находим основного вендора
                    $vendor = Vendor::firstOrCreate([
                        'name' => $tempVendor->name
                    ]);

                    // Переносим аккаунты
                    foreach ($tempVendor->tempAccounts as $tempAccount) {
                        Account::create([
                            'vendor_id' => $vendor->id,
                            'upload_id' => $this->uploadId,
                            'phone' => $tempAccount->phone ?? "0",
                            'geo' => $tempAccount->geo ?? "0",
                            'price' => $tempAccount->price ?? 0,
                            'spamblock' => $tempAccount->spamblock ?? "0",
                            'type' => $tempAccount->type ?? "0",
                            'session_created_at' => $tempAccount->session_created_date,
                            'last_connect_at' => $tempAccount->last_connect_date,
                            'stats_invites_count' => $tempAccount->stats_invites_count,
                        ]);
                    }
                }

                // Удаляем временные данные
                TempAccount::where('upload_id', $this->uploadId)->delete();
                TempVendor::where('upload_id', $this->uploadId)->delete();
            });

            Notification::make()
                ->title('Данные успешно сохранены')
                ->success()
                ->send();

            // Редиректим на страницу со списком загрузок
            return redirect()->route('filament.admin.resources.uploads.index');
        } catch (\Exception $e) {
            Notification::make()
                ->title('Ошибка при сохранении')
                ->body('Произошла ошибка при сохранении данных: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getActiveGeoFilters(): array
    {
        // Доступ к текущим фильтрам
        return $this->tableFilters['geo']['geo'] ?? [];
    }
}
