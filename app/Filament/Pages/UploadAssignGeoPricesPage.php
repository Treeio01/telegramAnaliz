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
use Filament\Tables\Filters\BaseFilter;
use App\Models\TempVendor;
use App\Models\TempAccount;
use Carbon\Carbon;

class UploadAssignGeoPricesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $slug = 'upload-assign-geo-prices';
    protected static string $view = 'filament.pages.upload-assign-geo-prices';

    public $geoPrices = [];
    public $geoList = [];
    public $uploadId;
    public $isInvite;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->uploadId = request()->query('uploadId');
        $geoList = session()->get("geo_list_for_upload_{$this->uploadId}", []);
        $this->isInvite = session()->get("is_invite_{$this->uploadId}", false);
        

        $this->geoList = $geoList;
        $this->geoPrices = [];

        foreach ($geoList as $geo) {
            $this->geoPrices[$geo] = GeoPrice::where('geo', $geo)->value('price') ?? null;
        }
    }

    /**
     * Преобразует строку даты в формат, совместимый с MySQL DATETIME.
     * Если строка невалидна или пуста, возвращает null.
     */
    protected function normalizeDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        // Попробуем распарсить с помощью Carbon
        try {
            // Carbon сам распознает большинство ISO 8601 форматов
            $dt = Carbon::parse($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // Если не удалось, возвращаем null
            return null;
        }
    }

    public function submit()
    {
        $missingPrices = collect($this->geoPrices)->filter(fn($price) => $price === null || $price === '');

        if ($missingPrices->isNotEmpty()) {
            $this->addError('geoPrices', 'Необходимо задать цену для всех стран.');
            return;
        }

        foreach ($this->geoPrices as $geo => $price) {
            GeoPrice::updateOrCreate(
                ['geo' => $geo],
                ['price' => $price]
            );
        }

        $uploadData = session()->get("upload_data_{$this->uploadId}", []);
        
        // Группируем данные по ролям (вендорам)
        $groupedData = collect($uploadData)->groupBy('role');

        DB::transaction(function () use ($groupedData) {
            foreach ($groupedData as $role => $accounts) {
                $tempVendor = TempVendor::create([
                    'name' => $role ?? 'unknown',
                    'upload_id' => $this->uploadId,
                ]);

                $accountsData = [];
                foreach ($accounts as $data) {
                    $accountsData[] = [
                        'temp_vendor_id' => $tempVendor->id,
                        'upload_id' => $this->uploadId,
                        'phone' => $data['phone'],
                        'geo' => $data['geo'] ?? null,
                        'price' => isset($this->geoPrices[$data['geo']]) ? $this->geoPrices[$data['geo']] : null,
                        'spamblock' => $data['spamblock'] ?? null,
                        'type' => $data['type'] ?? null,
                        'session_created_date' => $this->normalizeDateTime($data['session_created_date'] ?? null),
                        'last_connect_date' => $this->normalizeDateTime($data['last_connect_date'] ?? null),
                        'stats_invites_count' => $data['stats_invites_count'] ?? 45,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                

                // Массовая вставка аккаунтов
                TempAccount::insert($accountsData);
            }
        });

        session()->forget([
            "upload_data_{$this->uploadId}",
            "geo_list_for_upload_{$this->uploadId}",
            "upload_type_{$this->uploadId}",
        ]);

        if ($this->isInvite) {
            return redirect()->route('filament.pages.upload-page-invite', ['id' => $this->uploadId])
                ->with('success', 'Аккаунты успешно загружены!');
        } else {
            return redirect()->route('filament.pages.upload-profile', ['id' => $this->uploadId])
                ->with('success', 'Аккаунты успешно загружены!');
        }
    }
}
