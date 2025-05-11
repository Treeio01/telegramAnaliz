<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\Support\Facades\Log;

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
        Log::info('Property updated', ['property' => $propertyName]);
        
        // Для поддержки Livewire: убедимся, что это массивы
        if ($propertyName === 'validZipFiles' && !is_array($this->validZipFiles)) {
            $this->validZipFiles = [$this->validZipFiles];
            Log::debug('Converted validZipFiles to array', ['files' => $this->validZipFiles]);
        }
        if ($propertyName === 'deadZipFiles' && !is_array($this->deadZipFiles)) {
            $this->deadZipFiles = [$this->deadZipFiles];
            Log::debug('Converted deadZipFiles to array', ['files' => $this->deadZipFiles]);
        }

    }

    public function submit()
    {
        Log::info('Starting import submission');

        $this->validate([
            'validZipFiles.*' => 'file|mimes:zip|max:204800',
            'deadZipFiles.*' => 'file|mimes:zip|max:204800',
            'isInvite' => 'required|boolean',
        ]);

        $allNormalizedAccounts = [];
        $allGeoWithMissingPrices = [];
        $originalNames = [];

        if ($this->isInvite) {
            Log::info('Processing invite mode');
            // Обработка живых аккаунтов
            \Log::info('Проблемный архив', [
                'validZipFiles' => $this->validZipFiles,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ]);
            Log::info('Processing valid files for invite mode', ['count' => count($this->validZipFiles)]);
            if (!empty($this->validZipFiles)) {
                Log::debug('Processing valid files for invite mode', ['count' => count($this->validZipFiles)]);
                $this->processFiles($this->validZipFiles, $allNormalizedAccounts, $allGeoWithMissingPrices, $originalNames, 'valid');
            }
        } else {
            Log::info('Processing regular mode');
            // Обработка живых аккаунтов
            if (!empty($this->validZipFiles)) {
                Log::debug('Processing valid files', ['count' => count($this->validZipFiles)]);
                $this->processFiles($this->validZipFiles, $allNormalizedAccounts, $allGeoWithMissingPrices, $originalNames, 'valid');
            }

            // Обработка мертвых аккаунтов
            if (!empty($this->deadZipFiles)) {
                Log::debug('Processing dead files', ['count' => count($this->deadZipFiles)]);
                $this->processFiles($this->deadZipFiles, $allNormalizedAccounts, $allGeoWithMissingPrices, $originalNames, 'dead');
            }
        }

        // Убираем дубликаты гео
        $allGeoWithMissingPrices = array_unique($allGeoWithMissingPrices);
        Log::info('Unique geos with missing prices', ['geos' => $allGeoWithMissingPrices]);

        $upload = \App\Models\Upload::create([
            'type' => 'mixed', // теперь тип mixed, так как могут быть оба типа
            'meta' => ['original_name' => implode(', ', $originalNames)],
        ]);

        Log::info('Created upload record', ['upload_id' => $upload->id]);

        session()->put("upload_data_{$upload->id}", $allNormalizedAccounts);
        session()->put("geo_list_for_upload_{$upload->id}", $allGeoWithMissingPrices);
        session()->put("upload_type_{$upload->id}", 'mixed');
        session()->put("is_invite_{$upload->id}", $this->isInvite);

        Log::info('Import completed successfully', [
            'upload_id' => $upload->id,
            'accounts_count' => count($allNormalizedAccounts),
            'geos_count' => count($allGeoWithMissingPrices)
        ]);

        return redirect('/admin/upload-assign-geo-prices?uploadId=' . $upload->id);
    }

    private function processFiles($files, &$allNormalizedAccounts, &$allGeoWithMissingPrices, &$originalNames, $type)
    {

        
        foreach ($files as $zipFile) {
            Log::info('Processing zip file', [
                'original_name' => $zipFile->getClientOriginalName(),
                'type' => $type
            ]);

            $tempPath = $zipFile->getRealPath();
            $extractPath = storage_path('app/tmp/' . (string) Str::uuid());
            mkdir($extractPath, 0755, true);

            $zip = new \ZipArchive();
            if ($zip->open($tempPath) !== true) {
                Log::error('Failed to open archive', ['path' => $tempPath]);
                throw new \Exception("Не удалось открыть архив: " . $tempPath);
            }

            $zip->extractTo($extractPath);
            $zip->close();

            Log::debug('Archive extracted successfully', ['path' => $extractPath]);

            $files = array_filter(scandir($extractPath), fn($f) => str_ends_with($f, '.json'));
            Log::info('Found JSON files', ['count' => count($files)]);
            
            $geoWithMissingPrices = [];
            $normalizedAccounts = [];

            foreach ($files as $file) {
                $jsonPath = $extractPath . '/' . $file;
                $json = json_decode(file_get_contents($jsonPath), true);

                Log::debug('Processing JSON file', ['file' => $file]);
                
                // Проверяем, является ли $json массивом с одним элементом
                if (is_array($json) && count($json) === 1 && isset($json[0])) {
                    $json = $json[0];
                    Log::debug('Unwrapped single-element JSON array');
                }
                
                // Извлекаем данные из api_data если есть, иначе используем сам json
                $data = isset($json['api_data']) ? $json['api_data'] : $json;
                $phone = $data['phone'] ?? null;

                $geo = \App\Services\GeoDetectorService::getGeoFromPhone($phone);
                $price = $data['price'] ?? null;
                $role = $data['role'] ?? 'unknown';

                if (empty($price) && $geo) {
                    $geoWithMissingPrices[$geo] = true;
                    Log::warning('Missing price for geo', ['geo' => $geo]);
                }

                Log::debug('Account data normalized', [
                    'phone' => substr($phone, 0, 4) . '****', // Логируем только часть номера для безопасности
                    'geo' => $geo,
                    'role' => $role
                ]);

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
            Log::info('Zip file processing completed', [
                'accounts_processed' => count($normalizedAccounts),
                'missing_prices_geos' => count($geoWithMissingPrices)
            ]);
        }
    }

    public function processZipFile($file)
    {
        try {
            \Log::info('Начало обработки архива', [
                'имя' => $file->getClientOriginalName(),
                'размер' => $file->getSize(),
                'mime' => $file->getMimeType()
            ]);
            
            $zip = new \ZipArchive();
            $result = $zip->open($file->getRealPath());
            
            if ($result !== true) {
                \Log::error('Ошибка открытия архива', [
                    'код_ошибки' => $result,
                    'описание' => $this->getZipErrorMessage($result)
                ]);
                return false;
            }
            
            \Log::info('Архив успешно открыт', [
                'количество_файлов' => $zip->numFiles
            ]);
            
            // Вывести список файлов в архиве
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                \Log::info('Файл в архиве', [
                    'имя' => $stat['name'],
                    'размер' => $stat['size']
                ]);
            }
            
            $zip->close();
            return true;
        } catch (\Exception $e) {
            \Log::error('Исключение при обработке архива', [
                'сообщение' => $e->getMessage(),
                'файл' => $e->getFile(),
                'строка' => $e->getLine()
            ]);
            return false;
        }
    }

    private function getZipErrorMessage($code)
    {
        $messages = [
            \ZipArchive::ER_MULTIDISK => 'Многотомные архивы не поддерживаются',
            \ZipArchive::ER_RENAME => 'Ошибка переименования временного файла',
            \ZipArchive::ER_CLOSE => 'Ошибка закрытия архива',
            \ZipArchive::ER_SEEK => 'Ошибка поиска в архиве',
            \ZipArchive::ER_READ => 'Ошибка чтения архива',
            \ZipArchive::ER_WRITE => 'Ошибка записи в архив',
            \ZipArchive::ER_CRC => 'Ошибка CRC',
            \ZipArchive::ER_ZIPCLOSED => 'Архив был закрыт',
            \ZipArchive::ER_NOENT => 'Файл не найден',
            \ZipArchive::ER_EXISTS => 'Файл уже существует',
            \ZipArchive::ER_OPEN => 'Не удалось открыть файл',
            \ZipArchive::ER_TMPOPEN => 'Ошибка создания временного файла',
            \ZipArchive::ER_ZLIB => 'Ошибка Zlib',
            \ZipArchive::ER_MEMORY => 'Ошибка выделения памяти',
            \ZipArchive::ER_CHANGED => 'Запись была изменена',
            \ZipArchive::ER_COMPNOTSUPP => 'Метод сжатия не поддерживается',
            \ZipArchive::ER_EOF => 'Неожиданный конец файла',
            \ZipArchive::ER_INVAL => 'Недопустимый аргумент',
            \ZipArchive::ER_NOZIP => 'Не ZIP-архив',
            \ZipArchive::ER_INTERNAL => 'Внутренняя ошибка',
            \ZipArchive::ER_INCONS => 'Несогласованный ZIP-архив',
            \ZipArchive::ER_REMOVE => 'Не удалось удалить файл',
            \ZipArchive::ER_DELETED => 'Запись была удалена'
        ];
        
        return isset($messages[$code]) ? $messages[$code] : "Неизвестная ошибка ($code)";
    }

    protected static string $view = 'filament.pages.upload-import-wizard';

    protected function getFormSchema(): array
    {
        return [
            // ... ваши поля формы
        ];
    }

    protected function rules()
    {
        return [
            'validZipFiles.*' => 'file|mimes:zip|max:204800',
            'deadZipFiles.*' => 'file|mimes:zip|max:204800',
            'isInvite' => 'required|boolean',
        ];
    }

    protected $rules = [
        'validZipFiles.*' => 'file|mimes:zip|max:204800',
        'deadZipFiles.*' => 'file|mimes:zip|max:204800',
        'isInvite' => 'required|boolean',
    ];
}
