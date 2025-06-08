<?php

namespace App\Filament\Pages;

use App\Models\InviteVendor;
use App\Models\InviteAccount;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class InviteVendorProfile extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $title = 'Профиль продавца инвайтов';
    protected static ?string $slug = 'invite-vendor-profile';
    protected static string $view = 'filament.pages.invite-vendor-profile';

    public $inviteVendor;

    public function mount($vendorId)
    {
        $this->inviteVendor = InviteVendor::findOrFail($vendorId);
    }

    public function getGeoList()
    {
        return InviteAccount::where('invite_vendor_id', $this->inviteVendor->id)
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
            ->query(
                InviteAccount::query()->where('invite_vendor_id', $this->inviteVendor->id)
            )
            ->columns([
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('geo')
                    ->label('ГЕО')
                    ->sortable(),
                TextColumn::make('session_created_at')
                    ->label('Дата создания')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('stats_invites_count')
                    ->label('Кол-во инвайтов')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('geo')
                    ->label('ГЕО')
                    ->options($this->getGeoList())
                    ->multiple(),
                Filter::make('session_created_date_range')
                    ->form([
                        DatePicker::make('session_created_from')
                            ->label('Сессия от'),
                        DatePicker::make('session_created_to')
                            ->label('Сессия до'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['session_created_from'])) {
                            $query->whereDate('session_created_at', '>=', $data['session_created_from']);
                        }
                        if (!empty($data['session_created_to'])) {
                            $query->whereDate('session_created_at', '<=', $data['session_created_to']);
                        }
                        return $query;
                    }),
            ]);
    }
}
