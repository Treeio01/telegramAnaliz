<?php
namespace App\Actions;

use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UploadZipAction
{
    public static function handle(Request $request): \App\Models\Upload
    {
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
            'type' => $request->input('type'),
            'meta' => ['original_name' => $request->file('zip_file')->getClientOriginalName()],
        ]);

        $files = array_filter(scandir($extractPath), fn($f) => str_ends_with($f, '.json'));
        $geoWithMissingPrices = [];
        $rawJsonList = [];

        foreach ($files as $file) {
            $jsonPath = $extractPath . '/' . $file;
            $json = json_decode(file_get_contents($jsonPath), true);

            if (!$json || !isset($json['phone'])) continue;

            $geo = self::getGeoFromPhone($json['phone']);
            $price = $json['price'] ?? null;

            if (empty($price) && $geo) {
                $geoWithMissingPrices[$geo] = true;
            }

            $rawJsonList[] = $json + ['geo' => $geo];
        }

        // 🔐 Надёжно сохраняем в сессию
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
