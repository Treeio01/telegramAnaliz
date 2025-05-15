<x-filament::page>
    <x-filament::card>
        <x-slot name="heading">
            Профиль продавца инвайтов: {{ $inviteVendor->name }}
        </x-slot>

        <div class="space-y-4">
            <div>
                <strong>Имя продавца:</strong> {{ $inviteVendor->name }}
            </div>

            <div>
                <strong>Активных аккаунтов:</strong> {{ $inviteVendor->aliveInviteAccountsCount() }}
            </div>

            <div>
                <strong>Всего аккаунтов:</strong> {{ $inviteVendor->totalInviteAccountsCount() }}
            </div>

            <div>
                <strong>Дата создания:</strong> {{ $inviteVendor->created_at->format('d.m.Y') }}
            </div>
        </div>
    </x-filament::card>
    {{ $this->table }}
</x-filament::page>
