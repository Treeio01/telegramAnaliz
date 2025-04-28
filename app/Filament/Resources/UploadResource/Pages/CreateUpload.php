<?php

namespace App\Filament\Resources\UploadResource\Pages;

use App\Filament\Resources\UploadResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use App\Models\Account;
use App\Models\Vendor;
use ZipArchive;
use App\Services\GeoDetectorService;

class CreateUpload extends CreateRecord
{
    protected static string $resource = UploadResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        $zip = new ZipArchive();
        $path = storage_path('app/' . $record->file);

        if ($zip->open($path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $stream = $zip->getFromName($filename);

                $data = json_decode($stream, true);

                if (is_array($data)) {
                    $vendor = Vendor::firstOrCreate(['name' => $data['role'] ?? 'unknown']);

                    Account::create([
                        'vendor_id' => $vendor->id,
                        'phone' => $data['phone'] ?? null,
                        'geo' => GeoDetectorService::getGeoFromPhone($data['phone'] ?? null),
                        'spamblock' => $data['spamblock'] ?? 'unknown',
                        'session_created_at' => $data['session_created_date'] ?? null,
                        'last_connect_at' => $data['last_connect_date'] ?? null,
                        'stats_invites_count' => $data['stats_invites_count'] ?? 0,
                        'price' => $data['price'] ?? null,
                    ]);
                }
            }
            $zip->close();
        }
    }
}
