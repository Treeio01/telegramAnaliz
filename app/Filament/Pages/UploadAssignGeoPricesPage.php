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

class UploadAssignGeoPricesPage extends Page
{
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
                'type' => $type ?? null,
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
