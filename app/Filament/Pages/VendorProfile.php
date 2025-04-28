<?php

namespace App\Filament\Pages;

use App\Models\Vendor;
use App\Models\Account;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Contracts\View\View;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Widgets\Widget;

class VendorProfile extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $title = 'Профиль продавца';
    protected static ?string $slug = 'vendor-profile';
    // Указываем view напрямую
    protected static string $view = 'filament.resources.vendor-profile-resource.pages.vendor-profile'; // Это шаблон, который ты создашь в resources/views

    public $vendor;

    public function mount($vendorId)
    {
        $this->vendor = Vendor::findOrFail($vendorId);
    }

    public function getGeoList()
    {
        return Account::where('vendor_id', $this->vendor->id)->distinct()->pluck('geo', 'geo')->toArray();
    }

    public function getAccounts()
    {
        $query = Account::where('vendor_id', $this->vendor->id);

        // Apply filters
        if ($this->filters['geo']) {
            $query->whereIn('geo', $this->filters['geo']);
        }
        if ($this->filters['from']) {
            $query->where('session_created_at', '>=', $this->filters['from']);
        }
        if ($this->filters['to']) {
            $query->where('session_created_at', '<=', $this->filters['to']);
        }

        return $query->get();
    }

    public function getWidgets(): array
    {
        // Здесь предполагается, что у вас есть отдельный класс виджета GeoStatsWidget
        // Если его нет, создайте его в app/Filament/Widgets/GeoStatsWidget.php
        // и реализуйте там всю логику отображения статистики по GEO
        return [
            \App\Filament\Widgets\GeoStatsWidget::class,
            \App\Filament\Widgets\AccountsStatsWidget::class,
        ];
    }

    // Исправим метод, чтобы он возвращал массив статистики по GEO
    public function getGeoStats(): array
    {
        return Account::where('vendor_id', $this->vendor->id)
            ->selectRaw('geo, COUNT(*) as count')
            ->groupBy('geo')
            ->pluck('count', 'geo')
            ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Account::query()->where('vendor_id', $this->vendor->id))
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
                TextColumn::make('session_created_at')->label('Дата'),
                TextColumn::make('last_connect_at')->label('Последний коннект'),
                TextColumn::make('stats_invites_count')->label('Инвайты'),
                TextColumn::make('price')->label('Цена')->money('usd'),
            ])->filters($this->getTableFilters());
    }

    public function getTableFilters(): array
    {
        return [
            Filter::make('session_created_from')
                ->form([
                    DatePicker::make('created_from')
                        ->label('Дата от'),
                    DatePicker::make('created_until')
                        ->label('Дата до'),
                ])
                ->query(function (Builder $query, array $data) {
                    return $query
                        ->when($data['created_from'], fn($q) => $q->whereDate('session_created_at', '>=', $data['created_from']))
                        ->when($data['created_until'], fn($q) => $q->whereDate('session_created_at', '<=', $data['created_until']));
                }),

            SelectFilter::make('geo')
                ->label('Фильтр по GEO')
                ->searchable()
                ->multiple()
                ->options($this->getGeoList()),

            SelectFilter::make('vendor_id')
                ->label('Фильтр по продавцу')
                ->relationship('vendor', 'name'),

            SelectFilter::make('spamblock')
                ->label('Фильтр по типу')
                ->options([
                    'free' => 'Clean',
                    'spam' => 'Spam',
                ]),
        ];
    }
}
