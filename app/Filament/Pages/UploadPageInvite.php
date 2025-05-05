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

class UploadPageInvite extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.upload-page-invite';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

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

                // Формируем selectRaw для нужных метрик
                $geoCondition = $hasGeoFilter
                    ? 'temp_accounts.geo IN ("' . implode('","', $geoFilters) . '")'
                    : '1=1';

                $query->selectRaw("
                    temp_vendors.*,
                    COUNT(temp_accounts.id) as total_accounts,
                    AVG(CASE WHEN $geoCondition THEN temp_accounts.stats_invites_count ELSE NULL END) as avg_invites,
                    SUM(CASE WHEN $geoCondition AND temp_accounts.stats_invites_count > 0 THEN 1 ELSE 0 END) as worked_accounts,
                    SUM(CASE WHEN $geoCondition AND (temp_accounts.stats_invites_count = 0 OR temp_accounts.stats_invites_count IS NULL) THEN 1 ELSE 0 END) as zero_accounts,
                    CASE WHEN COUNT(temp_accounts.id) = 0 THEN 0 ELSE
                        (SUM(CASE WHEN $geoCondition AND temp_accounts.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(temp_accounts.id))
                    END as percent_worked,
                    CASE 
                        WHEN SUM(CASE WHEN $geoCondition THEN temp_accounts.stats_invites_count ELSE 0 END) = 0 THEN 0
                        ELSE SUM(CASE WHEN $geoCondition THEN temp_accounts.price ELSE 0 END) / 
                             SUM(CASE WHEN $geoCondition THEN temp_accounts.stats_invites_count ELSE 0 END)
                    END as avg_price_per_invite
                ");

                $query->leftJoin('temp_accounts', 'temp_vendors.id', '=', 'temp_accounts.temp_vendor_id')
                    ->groupBy('temp_vendors.id')
                    ->where('temp_vendors.upload_id', $this->uploadId);

                return $query;
            })
            ->columns([
                TextColumn::make('copy_name')
                    ->label('')
                    ->state('📋')  // Эмодзи буфера обмена
                    ->extraAttributes([
                        'x-data' => '{}',
                        'x-on:click' => '
                            const text = $el.getAttribute("data-copy-text");
                            const textarea = document.createElement("textarea");
                            textarea.value = text;
                            textarea.style.position = "fixed";
                            textarea.style.opacity = "0";
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand("copy");
                            document.body.removeChild(textarea);
                            
                            $dispatch("notify", {
                                message: "Скопировано",
                                timeout: 2000
                            });
                        ',
                        'data-copy-text' => '{record.name}',
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
                    ->label('Кол-во аккаунтов')
                    ->state(fn(TempVendor $record) => $record->total_accounts)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_accounts', $direction);
                    }),

                TextColumn::make('avg_invites')
                    ->label('Среднее кол-во инвайта')
                    ->state(fn(TempVendor $record) => is_null($record->avg_invites) ? 0 : round($record->avg_invites, 2))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('avg_invites', $direction);
                    }),

                TextColumn::make('worked_accounts')
                    ->label('Отработали')
                    ->state(fn(TempVendor $record) => $record->worked_accounts)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('worked_accounts', $direction);
                    }),

                TextColumn::make('zero_accounts')
                    ->label('Нулевые')
                    ->state(fn(TempVendor $record) => $record->zero_accounts)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('zero_accounts', $direction);
                    }),

                TextColumn::make('percent_worked')
                    ->label('% рабочих')
                    ->state(fn(TempVendor $record) => is_null($record->percent_worked) ? 0 : round($record->percent_worked, 2))
                    ->color(function (TempVendor $record) {
                        $percent = $record->percent_worked ?? 0;
                        return \App\Models\Settings::getColorForValue('percent_worked', $percent);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('percent_worked', $direction);
                    }),

                TextColumn::make('avg_price_per_invite')
                    ->label('Средняя цена инвайта')
                    ->state(fn(TempVendor $record) => is_null($record->avg_price_per_invite) ? 0 : round($record->avg_price_per_invite, 2))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('avg_price_per_invite', $direction);
                    }),
            ])
            ->filters([
                // Фильтр "Мин. аккаунтов" не работает, потому что havingRaw требует groupBy в основном запросе.
                // Альтернатива — фильтровать через подзапрос или после выборки (но это неэффективно).
                // Можно попробовать whereExists с подзапросом:
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
                            return $query->whereRaw('
                                (
                                    SELECT COUNT(ta.id)
                                    FROM temp_accounts ta
                                    WHERE ta.temp_vendor_id = temp_vendors.id
                                ) >= ?', [$min]);
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('percent_worked')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('percent_worked_min')
                            ->numeric()
                            ->label('Мин. % рабочих')
                            ->default(null),
                        \Filament\Forms\Components\TextInput::make('percent_worked_max')
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
                                        CASE WHEN COUNT(ta.id) = 0 THEN 0 ELSE
                                            (SUM(CASE WHEN ta.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(ta.id))
                                        END
                                    FROM temp_accounts ta
                                    WHERE ta.temp_vendor_id = temp_vendors.id
                                ) >= ?', [$min]);
                        }
                        if ($max !== null) {
                            $query->whereRaw('
                                (
                                    SELECT 
                                        CASE WHEN COUNT(ta.id) = 0 THEN 0 ELSE
                                            (SUM(CASE WHEN ta.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(ta.id))
                                        END
                                    FROM temp_accounts ta
                                    WHERE ta.temp_vendor_id = temp_vendors.id
                                ) <= ?', [$max]);
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
                        session(['current_geo_filters' => $data['geo'] ?? []]);
                        if (!empty($data['geo'])) {
                            $geo = $data['geo'];
                            $query->whereHas('tempAccounts', function ($q) use ($geo) {
                                $q->whereIn('geo', $geo);
                            });
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
