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
    <div class="flex flex-row">
        <div class="max-w-xs mx-auto">
            @livewire(\App\Filament\Widgets\GeoStatsWidget::class, ['vendorId' => $vendor->id], key('geo-stats-widget-'.$vendor->id))
        </div>
        <div class="max-w-xs mx-auto">
            @livewire(\App\Filament\Widgets\AccountsStatsWidget::class, ['vendorId' => $vendor->id], key('accounts-stats-widget-'.$vendor->id))
        </div>
    </div>
    {{ $this->table }}
</x-filament::page>