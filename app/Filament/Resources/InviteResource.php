<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteResource\Pages;
use App\Models\InviteAccount;
use App\Models\InviteVendor;
use App\Models\GeoPreset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InviteResource extends Resource
{
    protected static ?string $model = InviteVendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Инвайты';
    protected static ?string $navigationGroup = 'Управление';
    protected static ?string $title = 'Инвайты';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(function (Builder $query, $livewire) {
                $filters = $livewire->tableFilters;

                $geoFilters = $filters['geo']['geo'] ?? [];
                $fromDate   = $filters['session_created_date_range']['session_created_from'] ?? null;
                $toDate     = $filters['session_created_date_range']['session_created_to'] ?? null;

                $query->selectRaw('
                    invite_vendors.*,
                    COUNT(invite_accounts.id) as total_accounts,
                    AVG(invite_accounts.stats_invites_count) as avg_invites,
                    SUM(CASE WHEN invite_accounts.stats_invites_count > 0 THEN 1 ELSE 0 END) as worked_accounts,
                    SUM(CASE WHEN invite_accounts.stats_invites_count = 0 OR invite_accounts.stats_invites_count IS NULL THEN 1 ELSE 0 END) as zero_accounts,
                    CASE WHEN COUNT(invite_accounts.id) = 0 THEN 0 ELSE
                        (SUM(CASE WHEN invite_accounts.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(invite_accounts.id))
                    END as percent_worked,
                    SUM(invite_accounts.price) as total_price,
                    SUM(invite_accounts.stats_invites_count) as total_invites,
                    CASE
                        WHEN SUM(invite_accounts.stats_invites_count) > 0
                        THEN SUM(invite_accounts.price) / SUM(invite_accounts.stats_invites_count)
                        ELSE 0
                    END as avg_price_per_invite
                ')
                    ->leftJoin('invite_accounts', function ($join) use ($geoFilters, $fromDate, $toDate) {
                        $join->on('invite_vendors.id', '=', 'invite_accounts.invite_vendor_id');

                        if (!empty($geoFilters)) {
                            $join->whereIn('invite_accounts.geo', $geoFilters);
                        }
                        if ($fromDate) {
                            $from = Carbon::parse($fromDate)->startOfDay();
                            $join->where('invite_accounts.session_created_at', '>=', $from);
                        }
                        if ($toDate) {
                            $to = Carbon::parse($toDate)->endOfDay();
                            $join->where('invite_accounts.session_created_at', '<=', $to);
                        }
                    })
                    ->groupBy('invite_vendors.id');

                return $query;
            })
            ->columns([
                TextColumn::make('copy_name')
                    ->label('')
                    ->state('📋')
                    ->copyable()
                    ->copyableState(fn(InviteVendor $record): string => $record->name)
                    ->copyMessage('Скопировано')
                    ->copyMessageDuration(2000),

                TextColumn::make('name')
                    ->label('Продавец')
                    ->searchable()
                    ->sortable()
                    ->url(fn(InviteVendor $record): string => route('invite.vendor.profile', $record->id)),

                TextColumn::make('total_accounts')
                    ->label('Кол-во аккаунтов')
                    ->state(fn(InviteVendor $record) => $record->total_accounts ?? 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('total_accounts', $direction);
                    }),

                TextColumn::make('avg_invites')
                    ->label('Среднее кол-во инвайта')
                    ->state(fn(InviteVendor $record) => is_null($record->avg_invites) ? 0 : round($record->avg_invites, 2))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('avg_invites', $direction);
                    }),

                TextColumn::make('worked_accounts')
                    ->label('Отработали')
                    ->state(fn(InviteVendor $record) => $record->worked_accounts ?? 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('worked_accounts', $direction);
                    }),

                TextColumn::make('zero_accounts')
                    ->label('Нулевые')
                    ->state(fn(InviteVendor $record) => $record->zero_accounts ?? 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('zero_accounts', $direction);
                    }),

                TextColumn::make('percent_worked')
                    ->label('% рабочих')
                    ->state(fn(InviteVendor $record) => is_null($record->percent_worked) ? 0 : round($record->percent_worked, 2))
                    ->color(fn($state) => \App\Models\Settings::getColorForValue('percent_worked', $state))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('percent_worked', $direction);
                    }),

                TextColumn::make('avg_price_per_invite')
                    ->label('Средняя цена инвайта')
                    ->state(function (InviteVendor $record) {
                        $vendorId   = $record->id;
                        $geoFilters = request('tableFilters.geo.geo', []);
                        $params     = [$vendorId];
                        $geoCondition = '';

                        if (!empty($geoFilters)) {
                            $placeholders = implode(',', array_fill(0, count($geoFilters), '?'));
                            $geoCondition = "AND geo IN ($placeholders)";
                            $params = array_merge($params, $geoFilters);
                        }

                        $result = DB::select("
                            SELECT SUM(price) as total_price, SUM(stats_invites_count) as total_invites
                            FROM invite_accounts
                            WHERE invite_vendor_id = ?
                            $geoCondition
                        ", $params);

                        $totalPrice   = $result[0]->total_price ?? 0;
                        $totalInvites = $result[0]->total_invites ?? 0;

                        return $totalInvites > 0 ? round($totalPrice / $totalInvites, 2) : 0;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('avg_price_per_invite', $direction);
                    }),
            ])
            ->filters([
                Filter::make('min_accounts')
                    ->form([
                        TextInput::make('min_accounts')->numeric()->label('Мин. аккаунтов'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['min_accounts'])) {
                            $query->whereRaw('(
                                SELECT COUNT(*)
                                FROM invite_accounts
                                WHERE invite_accounts.invite_vendor_id = invite_vendors.id
                            ) >= ?', [(int)$data['min_accounts']]);
                        }
                        return $query;
                    }),

                Filter::make('percent_worked')
                    ->form([
                        TextInput::make('percent_worked_min')->numeric()->label('Мин. % рабочих'),
                        TextInput::make('percent_worked_max')->numeric()->label('Макс. % рабочих'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['percent_worked_min'])) {
                            $query->whereRaw('(
                                SELECT CASE WHEN COUNT(a.id) = 0 THEN 0 ELSE
                                    (SUM(CASE WHEN a.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id))
                                END
                                FROM invite_accounts a
                                WHERE a.invite_vendor_id = invite_vendors.id
                            ) >= ?', [(float)$data['percent_worked_min']]);
                        }
                        if (!empty($data['percent_worked_max'])) {
                            $query->whereRaw('(
                                SELECT CASE WHEN COUNT(a.id) = 0 THEN 0 ELSE
                                    (SUM(CASE WHEN a.stats_invites_count > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id))
                                END
                                FROM invite_accounts a
                                WHERE a.invite_vendor_id = invite_vendors.id
                            ) <= ?', [(float)$data['percent_worked_max']]);
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
                                    $set('geo', $preset ? $preset->geos : []);
                                }
                            }),
                        Select::make('geo')
                            ->label('Гео')
                            ->multiple()
                            ->searchable()
                            ->options(
                                InviteAccount::query()
                                    ->whereNotNull('geo')
                                    ->distinct()
                                    ->pluck('geo', 'geo')
                                    ->toArray()
                            ),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // просто возвращаем query, geo отработает в ->query() таблицы
                        return $query;
                    }),

                Filter::make('session_created_date_range')
                    ->form([
                        DatePicker::make('session_created_from')->label('Сессия от'),
                        DatePicker::make('session_created_to')->label('Сессия до'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // только прокидываем, обработка в ->query()
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInvites::route('/'),
            'create' => Pages\CreateInvite::route('/create'),
            'edit'   => Pages\EditInvite::route('/{record}/edit'),
        ];
    }
}
