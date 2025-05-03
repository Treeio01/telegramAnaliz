<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Профиль продавца: {{ $this->tempVendor->name }}
        </x-slot>
        
        <div class="space-y-2">
            <p><strong>Аккаунтов:</strong> {{ $this->tempVendor->tempAccounts->count() }}</p>
            <p><strong>Валидных:</strong> {{ $this->tempVendor->tempAccounts->where('type', 'valid')->count() }}</p>
            <p><strong>Невалид:</strong> {{ $this->tempVendor->tempAccounts->where('type', 'dead')->count() }}</p>
            <p><strong>Чистых:</strong> {{ $this->tempVendor->tempAccounts->where('spamblock', 'free')->count() }}</p>
            <p><strong>Спам:</strong> {{ $this->tempVendor->tempAccounts->where('spamblock', '!=', 'free')->count() }}</p>
        </div>
    </x-filament::card>
    
    <div class="mt-4">
        {{ $this->table }}
    </div>
</x-filament::page>
