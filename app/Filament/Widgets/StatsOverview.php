<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\AppliesDateFilter;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StatsOverview extends StatsOverviewWidget
{
    use AppliesDateFilter;

    protected function getStats(): array
    {
        $teller = $this->isTeller();

        $totals = $this->query()
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as jumlah')
            ->groupBy('type')
            ->get();

        $sum = fn (TransactionType $t): int => (int) $totals->first(fn ($r) => $r->type === $t)?->total;
        $count = fn (TransactionType $t): int => (int) $totals->first(fn ($r) => $r->type === $t)?->jumlah;

        $kredit = $sum(TransactionType::SetorTunai);
        $debit = $sum(TransactionType::TarikTunai);
        $kas = $sum(TransactionType::TarikKas);

        $jumlahKredit = $count(TransactionType::SetorTunai);
        $jumlahDebit = $count(TransactionType::TarikTunai);
        $jumlahKas = $count(TransactionType::TarikKas);

        $spark = $teller ? null : $this->sparklines();

        return [
            $this->card(
                $teller ? 'Saldo Hari Ini' : 'Total Saldo',
                $kredit - $debit - $kas,
                $jumlahKredit + $jumlahDebit + $jumlahKas,
                'gray',
                $spark['saldo'] ?? null,
            ),
            $this->card(
                TransactionType::SetorTunai->label() . ($teller ? ' Hari Ini' : ''),
                $kredit,
                $jumlahKredit,
                'success',
                $spark['kredit'] ?? null,
            ),
            $this->card(
                TransactionType::TarikTunai->label() . ($teller ? ' Hari Ini' : ''),
                $debit,
                $jumlahDebit,
                'warning',
                $spark['debit'] ?? null,
            ),
            $this->card(
                TransactionType::TarikKas->label() . ($teller ? ' Hari Ini' : ''),
                $kas,
                $jumlahKas,
                'danger',
                $spark['kas'] ?? null,
            ),
        ];
    }

    private function isTeller(): bool
    {
        return Auth::user()?->role === 'teller';
    }

    private function query(): Builder
    {
        $query = Transaction::query();

        if ($this->isTeller()) {
            return $query->whereDate('created_at', today()->toDateString());
        }

        return $this->applyDateFilter($query);
    }

    private function card(string $label, int $value, int $count, string $color, ?array $chart): Stat
    {
        $stat = Stat::make($label, 'Rp ' . number_format($value, 0, ',', '.'))
            ->description($count . ' transaksi')
            ->descriptionColor($color)
            ->color($color);

        return $chart ? $stat->chart($chart) : $stat;
    }

    private function sparklines(): array
    {
        $endInput = $this->pageFilters['endDate'] ?? null;
        $end = ($endInput ? Carbon::parse($endInput) : now())->startOfDay();
        $start = $end->copy()->subDays(6);

        $rows = Transaction::query()
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->selectRaw('DATE(created_at) as day, type, SUM(amount) as total')
            ->groupBy('day', 'type')
            ->get();

        $series = ['saldo' => [], 'kredit' => [], 'debit' => [], 'kas' => []];

        foreach (range(0, 6) as $i) {
            $day = $start->copy()->addDays($i)->toDateString();
            $forDay = $rows->filter(fn ($r) => (string) $r->day === $day);
            $get = fn (TransactionType $t): int => (int) $forDay->first(fn ($r) => $r->type === $t)?->total;

            $kredit = $get(TransactionType::SetorTunai);
            $debit = $get(TransactionType::TarikTunai);
            $kas = $get(TransactionType::TarikKas);

            $series['kredit'][] = $kredit;
            $series['debit'][] = $debit;
            $series['kas'][] = $kas;
            $series['saldo'][] = $kredit - $debit - $kas;
        }

        return $series;
    }
}
