<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Загрузка архивов
        </x-slot>

        <form wire:submit.prevent="submit" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label class="block font-semibold text-black dark:text-white mb-2">Живые аккаунты (.zip)</label>
                <input type="file" multiple wire:model="validZipFiles" accept=".zip" class="file-input file-input-bordered w-full bg-gray-800 rounded-md text-black dark:text-white mb-4" />
                
                <div wire:loading wire:target="validZipFiles" class="text-blue-500 text-sm mt-1">
                    Загрузка файлов...
                </div>
            </div>

            <div>
                <label class="block font-semibold text-black dark:text-white mb-2">Мертвые аккаунты (.zip)</label>
                <input type="file" multiple wire:model="deadZipFiles" accept=".zip" class="file-input file-input-bordered w-full bg-gray-800 rounded-md text-black dark:text-white" />
                
                <div wire:loading wire:target="deadZipFiles" class="text-blue-500 text-sm mt-1">
                    Загрузка файлов...
                </div>
            </div>

            <div>
                <label class="block font-semibold text-black dark:text-white mb-2">
                    <input type="checkbox" wire:model="isInvite" class="checkbox checkbox-primary" />
                   Инвайт
                </label>
            </div>

            <div wire:loading wire:target="submit" class="text-blue-500">
                Обработка файлов...
            </div>

            <div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600);"
                    class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-action fi-ac-btn-action"
                >
                    Загрузить и перейти к ценам
                </button>
            </div>

            @error('validZipFiles.*')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
            @error('validZipFiles')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
            @error('deadZipFiles.*')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
            @error('deadZipFiles')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
        </form>
    </x-filament::card>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            const maxSize = 200 * 1024 * 1024; // 200MB в байтах
            
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const files = this.files;
                    for (let i = 0; i < files.length; i++) {
                        if (files[i].size > maxSize) {
                            alert(`Файл "${files[i].name}" слишком большой (${Math.round(files[i].size / 1024 / 1024)}MB). Максимальный размер: 200MB.`);
                            this.value = ''; // Очищаем поле
                            return;
                        }
                    }
                });
            });
        });
    </script>
</x-filament::page>