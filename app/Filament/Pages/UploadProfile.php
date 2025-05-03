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
        return $table
            ->query(function () {
                // Вынес фильтры из запроса, чтобы они применялись корректно
                return TempVendor::query()
                    ->where('upload_id', $this->uploadId)
                    ->withCount([
                        'tempAccounts',
                        'tempAccounts as valid_accounts_count' => function ($q) {
                            $q->where('type', 'valid');
                        },
                        'tempAccounts as dead_accounts_count' => function ($q) {
                            $q->where('type', 'dead');
                        },
                        'tempAccounts as spam_accounts_count' => function ($q) {
                            $q->where('spamblock', '!=', 'free');
                        },
                        'tempAccounts as spam_valid_accounts_count' => function ($q) {
                            $q->where('type', 'valid')->where('spamblock', '!=', 'free');
                        },
                        'tempAccounts as spam_dead_accounts_count' => function ($q) {
                            $q->where('type', 'dead')->where('spamblock', '!=', 'free');
                        },
                        'tempAccounts as clean_accounts_count' => function ($q) {
                            $q->where('spamblock', 'free');
                        },
                        'tempAccounts as clean_valid_accounts_count' => function ($q) {
                            $q->where('type', 'valid')->where('spamblock', 'free');
                        },
                        'tempAccounts as clean_dead_accounts_count' => function ($q) {
                            $q->where('type', 'dead')->where('spamblock', 'free');
                        },
                    ]);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('temp_accounts_count')
                    ->label('Всего аккаунтов')
                    ->state(fn(TempVendor $record) => $record->temp_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('temp_accounts_count', $direction);
                    }),

                TextColumn::make('valid_accounts_count')
                    ->label('Валид')
                    ->state(fn(TempVendor $record) => $record->valid_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('valid_accounts_count', $direction);
                    }),

                TextColumn::make('dead_accounts_count')
                    ->label('Невалид')
                    ->state(fn(TempVendor $record) => $record->dead_accounts_count)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('dead_accounts_count', $direction);
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
                        if ($total === 0) return 'gray';
                        $spam = $record->spam_valid_accounts_count ?? 0;
                        $percent = round(($spam / $total) * 100, 2);
                        if ($percent > 75) {
                            return 'danger';
                        } elseif ($percent > 25) {
                            return 'warning';
                        } else {
                            return 'success';
                        }
                    })
                    ->state(function (TempVendor $record) {
                        $total = $record->spam_accounts_count ?? 0;
                        if ($total === 0) return 0;
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
                        if ($total === 0) return 'gray';
                        $clean = $record->clean_valid_accounts_count ?? 0;
                        $percent = round(($clean / $total) * 100, 2);
                        if ($percent < 25) {
                            return 'danger';
                        } elseif ($percent < 75) {
                            return 'warning';
                        } else {
                            return 'success';
                        }
                    })
                    ->state(function (TempVendor $record) {
                        $total = $record->clean_accounts_count ?? 0;
                        if ($total === 0) return 0;
                        $clean = $record->clean_valid_accounts_count ?? 0;
                        return round(($clean / $total) * 100, 2);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            "CASE WHEN clean_accounts_count = 0 THEN 0 ELSE (clean_valid_accounts_count * 100.0 / clean_accounts_count) END $direction"
                        );
                    }),
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
                        \Filament\Forms\Components\TextInput::make('survival_rate')
                            ->numeric()
                            ->label('Мин. выживаемость (%)')
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['survival_rate'])) {
                            $min = (int) $data['survival_rate'];
                            // Исправлено: фильтр survival_rate для only_full_group_by
                            // Вместо selectRaw('* ...') используем только агрегаты и group by
                            return $query->whereIn('id', function ($sub) use ($min) {
                                $sub->select('temp_vendor_id')
                                    ->from('temp_accounts')
                                    ->groupBy('temp_vendor_id')
                                    ->havingRaw('CASE WHEN COUNT(*) > 0 THEN (SUM(CASE WHEN spamblock = "free" THEN 1 ELSE 0 END) / COUNT(*)) * 100 ELSE 0 END >= ?', [$min]);
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\Filter::make('geo')
                    ->form([
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
                        if (!empty($data['geo'])) {
                            $geo = $data['geo'];
                            $query->whereHas('tempAccounts', function ($q) use ($geo) {
                                $q->whereIn('geo', $geo);
                            });
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
                            'phone' => $tempAccount->phone,
                            'geo' => $tempAccount->geo,
                            'price' => $tempAccount->price,
                            'spamblock' => $tempAccount->spamblock,
                            'type' => $tempAccount->type,
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
}
