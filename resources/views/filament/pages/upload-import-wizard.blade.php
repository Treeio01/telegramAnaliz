<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Загрузка архива
        </x-slot>

        <form wire:submit.prevent="submit" class="space-y-6">
            <div>
                <label class="block font-semibold text-gray-700 mb-2">Тип архива</label>
               
                <div class="grid auto-cols-fr gap-y-2">
                    <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 [&amp;:not(:has(.fi-ac-action:focus))]:focus-within:ring-2 ring-gray-950/10 dark:ring-white/20 [&amp;:not(:has(.fi-ac-action:focus))]:focus-within:ring-primary-600 dark:[&amp;:not(:has(.fi-ac-action:focus))]:focus-within:ring-primary-500 fi-fo-select">
                        <div class="fi-input-wrp-input min-w-0 flex-1">
                            <select wire:model="type"  class="fi-select-input block w-full border-none bg-transparent py-1.5 pe-8 text-base text-gray-950 transition duration-75 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] dark:text-white dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] sm:text-sm sm:leading-6 [&amp;_optgroup]:bg-white [&amp;_optgroup]:dark:bg-gray-900 [&amp;_option]:bg-white [&amp;_option]:dark:bg-gray-900 ps-3">
                                <option value="valid" >Живые аккаунты</option>
                                <option value="dead">Мертвые аккаунты</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-2">Архив (.zip)</label>
                <input type="file"  wire:model.defer="zipFile" class="file-input file-input-bordered w-full bg-gray-800  rounded-md text-black dark:text-white" />
            </div>

            <div>
                <button type="submit" style="--c-400:var(--primary-400);--c-500:var(--primary-500);--c-600:var(--primary-600);" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-primary fi-color-primary fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-action fi-ac-btn-action">Загрузить и перейти к ценам</button>
            </div>

            @error('zipFile')
            <div class="text-red-500 mt-2">{{ $message }}</div>
            @enderror
        </form>
    </x-filament::card>

</x-filament::page>