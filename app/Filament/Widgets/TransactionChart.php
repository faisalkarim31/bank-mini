<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesDateFilter;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TransactionChart extends ChartWidget
{
    use AppliesDateFilter;

    protected ?string $heading = 'Tren Transaksi';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    protected ?string $maxHeight = '300px';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        if (Auth::user()?->role === 'teller') {
            $start = now()->startOfDay();
            $end = now()->endOfDay();

            $totals = Transaction::query()
                ->whereBetween('created_at', [$start, $end])
                ->get(['created_at', 'amount'])
                ->groupBy(fn ($t) => $t->created_at->format('H'))
                ->map(fn ($group) => (int) $group->sum('amount'));

            for ($hour = 0; $hour <= 23; $hour++) {
                $key = sprintf('%02d', $hour);
                $labels[] = $key . ':00';
                $data[] = $totals->get($key, 0);
            }
        }
        else {
            $endInput = $this->pageFilters['endDate'] ?? null;
            $startInput = $this->pageFilters['startDate'] ?? null;

            $end = ($endInput ? Carbon::parse($endInput) : now())->endOfDay();
            $start = ($startInput ? Carbon::parse($startInput) : $end->copy()->subDays(13))->startOfDay();

            $perBulan = $start->diffInDays($end) > 31;
            $format = $perBulan ? 'Y-m' : 'Y-m-d';

            $totals = Transaction::query()
                ->whereBetween('created_at', [$start, $end])
                ->get(['created_at', 'amount'])
                ->groupBy(fn ($t) => $t->created_at->format($format))
                ->map(fn ($group) => (int) $group->sum('amount'));

            $cursor = $perBulan ? $start->copy()->startOfMonth() : $start->copy();

            while ($cursor->lte($end)) {
                $labels[] = $cursor->translatedFormat($perBulan ? 'M Y' : 'd M');
                $data[] = $totals->get($cursor->format($format), 0);

                $perBulan ? $cursor->addMonth() : $cursor->addDay();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tren Transaksi',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => 'start',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
