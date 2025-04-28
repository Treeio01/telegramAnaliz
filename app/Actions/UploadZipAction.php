<?php
namespace App\Actions;

use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UploadZipAction
{
    public static function handle(Request $request): Upload
    {
        $type = $request->input('type'); // valid или dead

        $path = $request->file('zip_file')->store('zips', 'local');
        $realPath = Storage::disk('local')->path($path);
        $extractPath = storage_path("app/tmp/" . Str::uuid());
        mkdir($extractPath, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($realPath) !== TRUE) {
            throw new \Exception("Не удалось открыть архив");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $upload = Upload::create([
            'type' => $type,
            'meta' => ['original_name' => $request->file('zip_file')->getClientOriginalName()],
        ]);

        $files = array_filter(scandir($extractPath), fn($f) => str_ends_with($f, '.json'));
        $geoWithMissingPrices = [];
        $rawJsonList = [];

        foreach ($files as $file) {
            $jsonPath = $extractPath . '/' . $file;
            $json = json_decode(file_get_contents($jsonPath), true);

            // ⚙️ dead аккаунты — в api_data
            $data = $type === 'dead' && isset($json['api_data']) ? $json['api_data'] : $json;

            $phone = $data['phone'] ?? null;
            if (!$phone) continue;

            $geo = self::getGeoFromPhone($phone);
            $price = $data['price'] ?? null;
            $role = $data['role'] ?? 'unknown';

            if (empty($price) && $geo) {
                $geoWithMissingPrices[$geo] = true;
            }

            $normalized = [
                'geo' => $geo,
                'price' => $price,
                'phone' => $phone,
                'spamblock' => $data['spamblock'] ?? null,
                'role' => $role,
                'session_created_date' => $data['session_created_date'] ?? null,
                'last_connect_date' => $data['last_connect_date'] ?? null,
                'stats_invites_count' => $data['stats_invites_count'] ?? 0,
            ];

            $rawJsonList[] = $normalized;
        }

        // сохраняем в сессию
        session()->put("upload_data_{$upload->id}", $rawJsonList);
        session()->put("geo_list_for_upload_{$upload->id}", array_keys($geoWithMissingPrices));

        return $upload;
    }

    private static function getGeoFromPhone(string $phone): ?string
    {
        try {
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $parsed = $phoneUtil->parse("+" . $phone);
            return $phoneUtil->getRegionCodeForNumber($parsed);
        } catch (\Exception $e) {
            return null;
        }
    }
}
