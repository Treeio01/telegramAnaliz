<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Загруженный архив #{{ $upload->id }}
        </x-slot>
        
        {{ $this->table }}
    </x-filament::card>
</x-filament::page>