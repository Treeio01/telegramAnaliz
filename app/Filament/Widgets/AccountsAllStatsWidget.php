<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Account;
use Illuminate\Support\Carbon;

class AccountsAllStatsWidget extends ChartWidget
{
    protected static ?string $heading = 'Динамика всех аккаунтов по датам';

    protected function getData(): array
    {
        // Получаем статистику по датам создания аккаунтов за последние 6 месяцев по всем аккаунтам
        $startDate = Carbon::now()->subMonths(5)->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // Получаем количество аккаунтов по месяцам
        $stats = Account::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Формируем список месяцев для оси X
        $months = [];
        $counts = [];
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1M'),
            $endDate->copy()->addMonth()->startOfMonth()
        );

        foreach ($period as $dt) {
            $label = $dt->format('Y-m');
            $months[] = \Carbon\Carbon::parse($dt->format('Y-m-d'))->translatedFormat('F Y');
            $counts[] = $stats[$label] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Аккаунты',
                    'data' => $counts,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                    'fill' => false,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
