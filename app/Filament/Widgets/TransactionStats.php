<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class TransactionStats extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $teller = Auth::user()?->role === 'teller';

        $query = Transaction::query();

        if ($teller) {
            $query->whereDate('created_at', today()->toDateString());
        }

        $sum = fn (TransactionType $type) => (float) (clone $query)->where('type', $type->value)->sum('amount');
        $count = fn (TransactionType $type) => (clone $query)->where('type', $type->value)->count();

        $kredit = $sum(TransactionType::SetorTunai);
        $debit = $sum(TransactionType::TarikTunai);
        $debitKas = $sum(TransactionType::TarikKas);
        $saldo = $kredit - $debit - $debitKas;

        [$saldoTrend, $kreditTrend, $debitTrend, $kasTrend] = $teller
            ? [null, null, null, null]
            : $this->getTrends();

        $suffix = $teller ? ' Hari Ini' : '';

        return [
            $this->card(
                $teller ? 'Saldo Hari Ini' : 'Total Saldo',
                $saldo,
                (clone $query)->count(),
                'gray',
                $saldoTrend,
            ),
            $this->card(
                'Kredit' . $suffix,
                $kredit,
                $count(TransactionType::SetorTunai),
                'success',
                $kreditTrend,
            ),
            $this->card(
                'Debit' . $suffix,
                $debit,
                $count(TransactionType::TarikTunai),
                'warning',
                $debitTrend,
            ),
            $this->card(
                'Debit Kas' . $suffix,
                $debitKas,
                $count(TransactionType::TarikKas),
                'danger',
                $kasTrend,
            ),
        ];
    }

    private function card(string $label, float $value, int $count, string $color, ?array $chart): Stat
    {
        $stat = Stat::make($label, 'Rp ' . number_format($value, 0, ',', '.'))
            ->description($count . ' transaksi')
            ->color($color);

        return $chart ? $stat->chart($chart) : $stat;
    }

    private function getTrends(): array
    {
        $start = now()->subDays(6)->startOfDay();

        $daily = Transaction::where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, type, SUM(amount) as total')
            ->groupBy('day', 'type')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->day . '|' . $r->getRawOriginal('type') => (float) $r->total]);

        $running = (float) Transaction::where('created_at', '<', $start)
            ->selectRaw("SUM(CASE WHEN type = 'setor_tunai' THEN amount ELSE -amount END) as saldo")
            ->value('saldo');

        $saldoTrend = $kreditTrend = $debitTrend = $kasTrend = [];

        foreach (range(6, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->toDateString();

            $k = $daily["$day|setor_tunai"] ?? 0;
            $d = $daily["$day|tarik_tunai"] ?? 0;
            $c = $daily["$day|tarik_kas"] ?? 0;

            $running += $k - $d - $c;

            $saldoTrend[] = $running;
            $kreditTrend[] = $k;
            $debitTrend[] = $d;
            $kasTrend[] = $c;
        }

        return [$saldoTrend, $kreditTrend, $debitTrend, $kasTrend];
    }

    #[On('transaction-saved')]
    public function refreshStats(): void
    {
    }
}
