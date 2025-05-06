<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UploadImportWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';
    protected static ?string $title = 'Импорт аккаунтов';
    protected static ?string $slug = 'upload-import-wizard';
    protected static ?string $navigationLabel = 'Импорт аккаунтов';
    protected static ?string $navigationGroup = 'Управление';

    public $validZipFiles = [];
    public $deadZipFiles = [];
    public $isInvite = false;
    public function updated($propertyName)
    {
        // Для поддержки Livewire: убедимся, что это массивы
        if ($propertyName === 'validZipFiles' && !is_array($this->validZipFiles)) {
            $this->validZipFiles = [$this->validZipFiles];
        }
        if ($propertyName === 'deadZipFiles' && !is_array($this->deadZipFiles)) {
            $this->deadZipFiles = [$this->deadZipFiles];
        }

    }

    public function submit()
    {

        $this->validate([
            'validZipFiles.*' => 'nullable|file|mimes:zip',
            'deadZipFiles.*' => 'nullable|file|mimes:zip',
            'isInvite' => 'required|boolean',
        ]);

        $allNormalizedAccounts = [];
        $allGeoWithMissingPrices = [];
        $originalNames = [];

        if ($this->isInvite) {
            // Обработка живых аккаунтов
            if (!empty($this->validZipFiles)) {
                $this->processFiles($this->validZipFiles, $allNormalizedAccounts, $allGeoWithMissingPrices, $originalNames, 'valid');
            }
        } else {
            // Обработка живых аккаунтов
            if (!empty($this->validZipFiles)) {
                $this->processFiles($this->validZipFiles, $allNormalizedAccounts, $allGeoWithMissingPrices, $originalNames, 'valid');
            }

            // Обработка мертвых аккаунтов
            if (!empty($this->deadZipFiles)) {
                $this->processFiles($this->deadZipFiles, $allNormalizedAccounts, $allGeoWithMissingPrices, $originalNames, 'dead');
            }
        }

        // Убираем дубликаты гео
        $allGeoWithMissingPrices = array_unique($allGeoWithMissingPrices);

        $upload = \App\Models\Upload::create([
            'type' => 'mixed', // теперь тип mixed, так как могут быть оба типа
            'meta' => ['original_name' => implode(', ', $originalNames)],
        ]);

        session()->put("upload_data_{$upload->id}", $allNormalizedAccounts);
        session()->put("geo_list_for_upload_{$upload->id}", $allGeoWithMissingPrices);
        session()->put("upload_type_{$upload->id}", 'mixed');
        session()->put("is_invite_{$upload->id}", $this->isInvite);

        return redirect('/admin/upload-assign-geo-prices?uploadId=' . $upload->id);
    }

    private function processFiles($files, &$allNormalizedAccounts, &$allGeoWithMissingPrices, &$originalNames, $type)
    {
        foreach ($files as $zipFile) {

            $tempPath = $zipFile->getRealPath();
            $extractPath = storage_path('app/tmp/' . (string) Str::uuid());
            mkdir($extractPath, 0755, true);

            $zip = new \ZipArchive();
            if ($zip->open($tempPath) !== true) {

                throw new \Exception("Не удалось открыть архив: " . $tempPath);
            }

            $zip->extractTo($extractPath);
            $zip->close();

            $files = array_filter(scandir($extractPath), fn($f) => str_ends_with($f, '.json'));
            $geoWithMissingPrices = [];
            $normalizedAccounts = [];

            foreach ($files as $file) {
                $jsonPath = $extractPath . '/' . $file;
                $json = json_decode(file_get_contents($jsonPath), true);

                
                \Illuminate\Support\Facades\Log::info($json);
                \Illuminate\Support\Facades\Log::info(isset($json['api_data']));
                // Проверяем наличие api_data и извлекаем данные соответственно
                $data = isset($json['api_data']) ? $json['api_data'] : $json;
                $phone = $data['phone'] ?? null;

                $geo = \App\Services\GeoDetectorService::getGeoFromPhone($phone);
                $price = $data['price'] ?? null;
                $role = $data['role'] ?? 'unknown';

                if (empty($price) && $geo) {
                    $geoWithMissingPrices[$geo] = true;
                }


                $normalizedAccounts[] = [
                    'geo' => $geo,
                    'price' => $price,
                    'phone' => $phone,
                    'spamblock' => $data['spamblock'] ?? null,
                    'role' => $role,
                    'session_created_date' => $data['session_created_date'] ?? null,
                    'last_connect_date' => $data['last_connect_date'] ?? null,
                    'stats_invites_count' => $data['stats_invites_count'] ?? 0,
                    'type' => $type, // Используем тип из параметра функции, независимо от формата данных
                ];
            }
            $allNormalizedAccounts = array_merge($allNormalizedAccounts, $normalizedAccounts);
            $allGeoWithMissingPrices = array_merge($allGeoWithMissingPrices, array_keys($geoWithMissingPrices));
            $originalNames[] = $zipFile->getClientOriginalName();

            Storage::disk('local')->delete($tempPath);
        }
    }

    protected static string $view = 'filament.pages.upload-import-wizard';
}
