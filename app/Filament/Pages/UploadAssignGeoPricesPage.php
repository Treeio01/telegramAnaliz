<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Account;
use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;
use App\Services\GeoDetectorService;
use App\Models\Upload;
use App\Models\GeoPrice;
use Illuminate\Support\Str;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\BaseFilter; // Добавлено для типизации
// use Filament\Tables\Query\Builder as FilamentBuilder; // Удаляем, чтобы не было конфликта типов

// Создаем временную модель
class TempUploadData extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    protected $table = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function setCustomTable($table)
    {
        $this->setTable($table);
    }
}

class UploadAssignGeoPricesPage extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $slug = 'upload-assign-geo-prices';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.upload-assign-geo-prices';

    public $geoPrices = [];
    public $geoList = [];
    public $uploadId;

    public function mount(): void
    {
        $this->uploadId = request()->query('uploadId');

        $geoList = session()->get("geo_list_for_upload_{$this->uploadId}", []);

        if (!$geoList) {
            abort(403, 'Нет данных для загрузки.');
        }

        $this->geoList = $geoList;
        $this->geoPrices = [];

        foreach ($geoList as $geo) {
            $this->geoPrices[$geo] = \App\Models\GeoPrice::where('geo', $geo)->value('price') ?? null;
        }
    }

    public function table(Table $table): Table
    {
        $uploadData = session()->get("upload_data_{$this->uploadId}", []);

        // Создаем временную таблицу с уникальным именем
        $tableName = 'temp_upload_data_' . $this->uploadId;

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function ($table) {
                $table->id();
                $table->string('phone')->nullable();
                $table->string('geo')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->string('spamblock')->nullable();
                $table->string('type')->nullable();
                $table->string('role')->nullable();
                $table->timestamp('session_created_date')->nullable();
                $table->timestamp('last_connect_date')->nullable();
                $table->integer('stats_invites_count')->nullable();
                $table->temporary();
            });

            // Заполняем временную таблицу данными
            collect($uploadData)->each(function ($data) use ($tableName) {
                $insertData = (array) $data;

                // Преобразуем даты в правильный формат MySQL
                foreach (['session_created_date', 'last_connect_date'] as $dateField) {
                    if (!empty($insertData[$dateField])) {
                        // Пробуем распарсить дату, если не получилось — ставим null
                        $timestamp = strtotime($insertData[$dateField]);
                        $insertData[$dateField] = $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
                    } else {
                        $insertData[$dateField] = null;
                    }
                }

                DB::table($tableName)->insert($insertData);
            });
        }

        // Используем временную Eloquent-модель для работы с Filament Table
        $model = new TempUploadData();
        $model->setCustomTable($tableName);

        // Важно: возвращаем query() без типизации FilamentBuilder, чтобы не было конфликта типов
        return $table
            ->query(fn() => $model->newQuery())
            ->columns([
                TextColumn::make('phone')
                    ->label('Номер')
                    ->searchable(),
                TextColumn::make('geo')
                    ->label('Гео')
                    ->searchable(),
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
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($state) {
                        return $state === 'valid' ? 'Живой' : ($state === 'invalid' ? 'Мёртвый' : $state);
                    })
                    ->badge()
                    ->colors([
                        'success' => fn($state) => $state === 'valid',
                        'danger' => fn($state) => $state === 'invalid',
                    ]),
                TextColumn::make('session_created_date')
                    ->label('Дата создания')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('last_connect_date')
                    ->label('Последний коннект')
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('stats_invites_count')
                    ->label('Инвайты'),
            ])->filters([

                SelectFilter::make('geo')
                    ->label('Фильтр по GEO')
                    ->searchable()
                    ->multiple()
                    ->options(
                        Account::query()
                            ->whereNotNull('geo')
                            ->distinct()
                            ->orderBy('geo')
                            ->pluck('geo', 'geo')
                            ->toArray()
                    ),

                // Удаляем фильтр по продавцу, так как во временной таблице нет связи vendor
                // SelectFilter::make('vendor_id')
                //     ->label('Фильтр по продавцу')
                //     ->relationship('vendor', 'name'),

                SelectFilter::make('spamblock')
                    ->label('Фильтр по типу')
                    ->options([
                        'free' => 'Clean',
                        'spam' => 'Spam',
                    ]),
            ]);
    }

    public function submit()
    {
        $missingPrices = collect($this->geoPrices)->filter(fn($price) => $price === null || $price === '');

        if ($missingPrices->isNotEmpty()) {
            $this->addError('geoPrices', 'Необходимо задать цену для всех стран.');
            return;
        }

        $uploadData = session()->get("upload_data_{$this->uploadId}", []);
        $type = session()->get("upload_type_{$this->uploadId}");

        foreach ($uploadData as $data) {

            if (empty($data['phone'])) {
                continue;
            }

            $vendor = Vendor::firstOrCreate(['name' => $data['role'] ?? 'unknown']);

            Account::create([
                'vendor_id' => $vendor->id,
                'upload_id' => $this->uploadId,
                'phone' => $data['phone'],
                'geo' => $data['geo'] ?? null,
                'session_created_at' => $data['session_created_date'] ?? null,
                'last_connect_at' => $data['last_connect_date'] ?? null,
                'spamblock' => $data['spamblock'] ?? null,
                'has_profile_pic' => isset($data['has_profile_pic']) ? (int)$data['has_profile_pic'] : 0,
                'stats_spam_count' => isset($data['stats_spam_count']) ? (int)$data['stats_spam_count'] : 0,
                'stats_invites_count' => isset($data['stats_invites_count']) ? (int)$data['stats_invites_count'] : 0,
                'is_premium' => isset($data['is_premium']) ? (int)$data['is_premium'] : 0,
                'price' => isset($this->geoPrices[$data['geo']]) ? $this->geoPrices[$data['geo']] : null,
                'type' => $data['type'],
            ]);
        }

        // Чистим сессии
        session()->forget([
            "upload_data_{$this->uploadId}",
            "geo_list_for_upload_{$this->uploadId}",
            "upload_type_{$this->uploadId}",
        ]);

        return redirect()->route('filament.admin.resources.uploads.index')
            ->with('success', 'Аккаунты успешно загружены!');
    }
}
