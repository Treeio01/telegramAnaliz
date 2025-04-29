<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Account;
use Livewire\Attributes\On;

class GeoStatsWidget extends ChartWidget
{
    public $vendorId;

    protected static ?string $heading = 'Статистика по GEO';

    // Устанавливаем максимальную ширину для виджета через свойство $maxWidth
    protected static ?string $maxWidth = 'sm';

    protected function getData(): array
    {
        // Получаем статистику по GEO
        $stats = Account::selectRaw('geo, COUNT(*) as count')
            ->where('vendor_id', request()->route('vendorId')) // получаем ID из URL
            ->whereNotNull('geo')
            ->groupBy('geo')
            ->pluck('count', 'geo')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Аккаунты',
                    'data' => array_values($stats),
                    'backgroundColor' => '#3b82f6', // Можно поменять цвет
                ],
            ],
            'labels' => array_keys($stats),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
