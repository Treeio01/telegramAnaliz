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

    public $zipFile;
    public $type = 'valid'; // default

    public function submit()
    {
        $this->validate([
            'zipFile' => 'required|file|mimes:zip',
            'type' => 'required|in:valid,dead',
        ]);

        $tempPath = $this->zipFile->getRealPath(); // <--- вот реальный zip-файл

        $extractPath = storage_path('app/tmp/' . (string) Str::uuid());
        mkdir($extractPath, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            throw new \Exception("Не удалось открыть архив: " . $tempPath);
        }

        $zip->extractTo($extractPath);
        $zip->close();


        // Парсим JSON
        $files = array_filter(scandir($extractPath), fn($f) => str_ends_with($f, '.json'));
        $geoWithMissingPrices = [];
        $normalizedAccounts = [];

        foreach ($files as $file) {
            $jsonPath = $extractPath . '/' . $file;
            $json = json_decode(file_get_contents($jsonPath), true);

            $isDead = isset($json['api_data']);
            $data = $isDead ? $json['api_data'] : $json;

            $phone = $data['phone'] ?? null;
            if (!$phone) continue;

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
                'type' => $isDead ? 'dead' : 'alive',
            ];
        }

        $upload = \App\Models\Upload::create([
            'type' => $this->type,
            'meta' => ['original_name' => $this->zipFile->getClientOriginalName()],
        ]);

        session()->put("upload_data_{$upload->id}", $normalizedAccounts);
        session()->put("geo_list_for_upload_{$upload->id}", array_keys($geoWithMissingPrices));
        session()->put("upload_type_{$upload->id}", $this->type);

        // Удаляем архив после обработки
        Storage::disk('local')->delete($tempPath);

        return redirect('/admin/upload-assign-geo-prices?uploadId=' . $upload->id);
    }

    protected static string $view = 'filament.pages.upload-import-wizard';
}
