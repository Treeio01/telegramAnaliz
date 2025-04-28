<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Account;

class GeoAllStatsWidget extends ChartWidget
{
    protected static ?string $heading = 'Статистика по всем GEO';

    protected static ?string $maxWidth = 'md';

    protected function getData(): array
    {
        // Получаем статистику по всем GEO (без фильтра по продавцу)
        $stats = Account::selectRaw('geo, COUNT(*) as count')
            ->whereNotNull('geo')
            ->groupBy('geo')
            ->pluck('count', 'geo')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Аккаунты',
                    'data' => array_values($stats),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => array_keys($stats),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
