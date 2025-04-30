<?php

namespace App\Filament\Resources\UploadResource\Pages;

use App\Filament\Resources\UploadResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use App\Models\Upload;
use Illuminate\Support\Str;
use ZipArchive;

class CreateUploadWithAssignPrices extends CreateRecord
{
    protected static string $resource = UploadResource::class;

    protected $customRedirectUrl = null; // 👈 добавляем переменную

    protected function afterSave(): void
    {
        $record = $this->record;
        $path = storage_path('app/' . $record->file);

        $extractPath = storage_path('app/tmp/' . (string) Str::uuid());
        mkdir($extractPath, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \Exception("Не удалось открыть архив: " . $path);
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $type = $record->type;

        $files = array_filter(scandir($extractPath), fn($f) => str_ends_with($f, '.json'));

        $geoWithMissingPrices = [];
        $normalizedAccounts = [];

        foreach ($files as $file) {
            $jsonPath = $extractPath . '/' . $file;
            $json = json_decode(file_get_contents($jsonPath), true);

            $data = $type === 'dead' && isset($json['api_data']) ? $json['api_data'] : $json;

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
            ];
        }

        session()->put("upload_data_{$record->id}", $normalizedAccounts);
        session()->put("geo_list_for_upload_{$record->id}", array_keys($geoWithMissingPrices));
        

        // сохраняем кастомный редирект
        $this->customRedirectUrl = '/admin/upload-assign-geo-prices?uploadId=' . $record->id;
    }

    protected function getRedirectUrl(): string
    {
        return $this->customRedirectUrl ?? parent::getRedirectUrl();
    }
}
