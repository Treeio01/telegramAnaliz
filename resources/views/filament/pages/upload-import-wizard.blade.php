<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Загрузка архивов
        </x-slot>

        <form wire:submit.prevent="submit" class="space-y-6">
            <div>
                <label class="block font-semibold text-black dark:text-white mb-2">Живые аккаунты (.zip)</label>
                <input type="file" multiple wire:model="validZipFiles" class="file-input file-input-bordered w-full bg-gray-800 rounded-md text-black dark:text-white mb-4" />
            </div>

            <div>
                <label class="block font-semibold text-black dark:text-white mb-2">Мертвые аккаунты (.zip)</label>
                <input type="file" multiple wire:model="deadZipFiles" class="file-input file-input-bordered w-full bg-gray-800 rounded-md text-black dark:text-white" />
            </div>

            <div>
                <label class="block font-semibold text-black dark:text-white mb-2">
                    <input type="checkbox" wire:model="isInvite" class="checkbox checkbox-primary" />
                   Инвайт
                </label>
            </div>

            <div wire:loading wire:target="validZipFiles,deadZipFiles" class="text-blue-500">
                Загрузка файлов...
            </div>

            <div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="validZipFiles,deadZipFiles"
                    style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600);"
                    class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-action fi-ac-btn-action"
                >
                    Загрузить и перейти к ценам
                </button>
            </div>

            @error('validZipFiles')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
            @error('deadZipFiles')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
        </form>
    </x-filament::card>
</x-filament::page>