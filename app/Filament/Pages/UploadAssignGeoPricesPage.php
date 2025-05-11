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
use Illuminate\Support\Facades\Log;

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
        Log::info('Mounting UploadAssignGeoPricesPage');
        
        $this->uploadId = request()->query('uploadId');
        Log::debug('Upload ID received', ['uploadId' => $this->uploadId]);
        
        $geoList = session()->get("geo_list_for_upload_{$this->uploadId}", []);
        $this->isInvite = session()->get("is_invite_{$this->uploadId}", false);
        
        Log::debug('Session data retrieved', [
            'geoList' => $geoList,
            'isInvite' => $this->isInvite
        ]);

        $this->geoList = $geoList;
        $this->geoPrices = [];

        foreach ($geoList as $geo) {
            $price = GeoPrice::where('geo', $geo)->value('price') ?? null;
            $this->geoPrices[$geo] = $price;
            Log::debug('Retrieved price for geo', [
                'geo' => $geo,
                'price' => $price
            ]);
        }
    }

    /**
     * Преобразует строку даты в формат, совместимый с MySQL DATETIME.
     * Если строка невалидна или пуста, возвращает null.
     */
    protected function normalizeDateTime($value)
    {
        if (empty($value)) {
            Log::debug('Empty date value received');
            return null;
        }

        try {
            $dt = Carbon::parse($value);
            $formatted = $dt->format('Y-m-d H:i:s');
            Log::debug('Date normalized successfully', [
                'input' => $value,
                'output' => $formatted
            ]);
            return $formatted;
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', [
                'input' => $value,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function submit()
    {
        Log::info('Starting submit process');

        $missingPrices = collect($this->geoPrices)->filter(fn($price) => $price === null || $price === '');

        if ($missingPrices->isNotEmpty()) {
            Log::warning('Missing prices detected', ['missing' => $missingPrices->keys()]);
            $this->addError('geoPrices', 'Необходимо задать цену для всех стран.');
            return;
        }

        Log::info('Updating GeoPrice records');
        foreach ($this->geoPrices as $geo => $price) {
            GeoPrice::updateOrCreate(
                ['geo' => $geo],
                ['price' => $price]
            );
            Log::debug('GeoPrice updated', ['geo' => $geo, 'price' => $price]);
        }

        $uploadData = session()->get("upload_data_{$this->uploadId}", []);
        Log::debug('Retrieved upload data from session', ['count' => count($uploadData)]);
        
        $groupedData = collect($uploadData)->groupBy('role');
        Log::info('Data grouped by role', ['roles' => $groupedData->keys()]);

        DB::transaction(function () use ($groupedData) {
            Log::info('Starting database transaction');
            
            foreach ($groupedData as $role => $accounts) {
                Log::debug('Processing role', ['role' => $role, 'accounts_count' => count($accounts)]);
                
                $tempVendor = TempVendor::create([
                    'name' => $role ?? 'unknown',
                    'upload_id' => $this->uploadId,
                ]);
                Log::debug('Created TempVendor', ['id' => $tempVendor->id, 'name' => $tempVendor->name]);
                
                $mostCommonGeo = $accounts->map(fn($acc) => $acc['geo'])
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();
                Log::debug('Determined most common geo', ['geo' => $mostCommonGeo]);

                $accountsData = [];
                foreach ($accounts as $data) {
                    $geo = $data['geo'] ?? $mostCommonGeo;
                    
                    $accountsData[] = [
                        'temp_vendor_id' => $tempVendor->id,
                        'upload_id' => $this->uploadId,
                        'phone' => $data['phone'] ?? null,
                        'geo' => $geo ?? null,
                        'price' => $this->geoPrices[$geo] ?? null,
                        'spamblock' => $data['spamblock'] ?? null,
                        'type' => $data['type'] ?? null,
                        'session_created_date' => $this->normalizeDateTime($data['session_created_date'] ?? null),
                        'last_connect_date' => $this->normalizeDateTime($data['last_connect_date'] ?? null),
                        'stats_invites_count' => $data['stats_invites_count'] ?? 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                Log::debug('Inserting accounts batch', ['count' => count($accountsData)]);
                TempAccount::insert($accountsData);
            }
            Log::info('Database transaction completed successfully');
        });

        Log::debug('Cleaning up session data');
        session()->forget([
            "upload_data_{$this->uploadId}",
            "geo_list_for_upload_{$this->uploadId}",
            "upload_type_{$this->uploadId}",
        ]);

        Log::info('Submit process completed', ['isInvite' => $this->isInvite]);
        
        if ($this->isInvite) {
            return redirect()->route('filament.pages.upload-page-invite', ['id' => $this->uploadId])
                ->with('success', 'Аккаунты успешно загружены!');
        } else {
            return redirect()->route('filament.pages.upload-profile', ['id' => $this->uploadId])
                ->with('success', 'Аккаунты успешно загружены!');
        }
    }
}
