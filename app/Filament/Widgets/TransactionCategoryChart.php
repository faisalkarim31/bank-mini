<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\AppliesDateFilter;
use App\Models\Transaction;
use Filament\Widgets\ChartWidget;

class TransactionCategoryChart extends ChartWidget
{
    use AppliesDateFilter;

    protected ?string $heading = 'Kategori Transaksi';

    protected int | string | array $columnSpan = [
    'default' => 'full',
    'lg' => 1,
    ];

    protected ?string $maxHeight = '300px';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $counts = $this->applyDateFilter(Transaction::query())
            ->selectRaw('type, COUNT(*) as jumlah')
            ->groupBy('type')
            ->get();

        $cases = collect(TransactionType::cases());

        return [
            'datasets' => [
                [
                    'label' => 'Kategori Transaksi',
                    'data' => $cases
                        ->map(fn (TransactionType $t) => (int) $counts->first(fn ($r) => $r->type === $t)?->jumlah)
                        ->all(),
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => $cases->map(fn (TransactionType $t) => $t->label())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
