<x-filament::page>

    <x-filament::card>
        <x-slot name="heading">
            Профиль продавца: {{ $vendor->name }}
        </x-slot>

        <div class="space-y-4">
            <div>
                <strong>Имя продавца:</strong> {{ $vendor->name }}
            </div>

            <div>
                <strong>Активных аккаунтов:</strong> {{ $vendor->aliveAccountsCount() }}
            </div>

            <div>
                <strong>Всего аккаунтов:</strong> {{ $vendor->totalAccountsCount() }}
            </div>

            <div>
                <strong>Дата создания:</strong> {{ $vendor->created_at->format('d.m.Y') }}
            </div>
        </div>
    </x-filament::card>
    {{ $this->table }}
</x-filament::page>