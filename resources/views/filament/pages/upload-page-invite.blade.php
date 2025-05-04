<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Загруженный архив (Invite) #{{ $upload->id }}
        </x-slot>
        
        {{ $this->table }}
    </x-filament::card>
</x-filament::page>