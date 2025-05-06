<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\TempVendor;
use App\Models\TempAccount;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class TempVendorProfile extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    
    protected static string $view = 'filament.pages.temp-vendor-profile';
    public $tempVendor;
    
    public function mount($id): void
    {
        $this->tempVendor = TempVendor::findOrFail($id);
    }
    
    public function getGeoList()
    {
        // Удаляем все значения, которые null или пустые строки, чтобы избежать ошибки с null label
        return TempAccount::where('temp_vendor_id', $this->tempVendor->id)
            ->distinct()
            ->pluck('geo', 'geo')
            ->filter(function ($label, $value) {
                return !is_null($label) && $label !== '';
            })
            ->toArray();
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(TempAccount::query()->where('temp_vendor_id', $this->tempVendor->id))
            ->columns([
                TextColumn::make('phone')->label('Номер')->searchable(),
                TextColumn::make('geo')->label('Гео')->searchable(),
                TextColumn::make('spamblock')
                    ->label('Тип')
                    ->colors([
                        'success' => fn($state) => $state === 'free',
                        'danger' => fn($state) => $state !== 'free',
                    ])
                    ->formatStateUsing(function ($state) {
                        return $state === 'free' ? 'Clean' : 'Spam';
                    })->badge(),
                TextColumn::make('type')
                    ->label('Статус')
                    ->colors([
                        'success' => fn($state) => $state === 'valid',
                        'danger' => fn($state) => $state === 'dead',
                    ])
                    ->formatStateUsing(function ($state) {
                        return $state === 'valid' ? 'Валид' : 'Невалид';
                    })->badge(),
                TextColumn::make('session_created_date')->label('Дата сессии'),
                TextColumn::make('last_connect_date')->label('Последний коннект'),
                TextColumn::make('stats_invites_count')->label('Инвайты'),
                TextColumn::make('price')->label('Цена')->money('rub'),
            ])
            ->filters([
                Filter::make('session_created_date_range')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Дата от'),
                        DatePicker::make('created_until')
                            ->label('Дата до'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['created_from'], fn($q) => $q->whereDate('session_created_date', '>=', $data['created_from']))
                            ->when($data['created_until'], fn($q) => $q->whereDate('session_created_date', '<=', $data['created_until']));
                    }),

                SelectFilter::make('geo')
                    ->label('Фильтр по GEO')
                    ->searchable()
                    ->multiple()
                    ->options($this->getGeoList()),

                SelectFilter::make('spamblock')
                    ->label('Фильтр по типу')
                    ->options([
                        'free' => 'Clean',
                        'spam' => 'Spam',
                    ]),
                    
                SelectFilter::make('type')
                    ->label('Фильтр по статусу')
                    ->options([
                        'valid' => 'Валид',
                        'dead' => 'Невалид',
                    ]),
            ]);
    }
}
