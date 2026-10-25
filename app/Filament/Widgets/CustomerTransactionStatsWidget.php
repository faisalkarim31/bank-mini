<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class CustomerTransactionStatsWidget extends BaseWidget
{
    public ?Customer $record = null;
    public ?string $dateFrom = null;
    public ?string $dateUntil = null;
    public ?string $typeFilter = null;

    #[On('transaction-filters-updated')]
    public function applyFilters($from = null, $until = null, $type = null): void
    {
        $this->dateFrom = $from;
        $this->dateUntil = $until;
        $this->typeFilter = $type;
    }

    protected function getStats(): array
    {
        $base = $this->record->transactions()
            ->when($this->dateFrom, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->dateUntil, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($this->typeFilter, fn ($q, $t) => $q->where('type', $t));

        $kreditQuery = $base->clone()->where('type', 'setor_tunai');
        $debitQuery = $base->clone()->where('type', 'tarik_tunai');
        $debitKasQuery = $base->clone()->where('type', 'tarik_kas');

        $trendTotal = collect(range(6, 0))->map(fn ($d) => $base->clone()->whereDate('created_at', now()->subDays($d))->count())->toArray();
        $trendKredit = collect(range(6, 0))->map(fn ($d) => $kreditQuery->clone()->whereDate('created_at', now()->subDays($d))->count())->toArray();
        $trendDebit = collect(range(6, 0))->map(fn ($d) => $debitQuery->clone()->whereDate('created_at', now()->subDays($d))->count())->toArray();
        $trendDebitKas = collect(range(6, 0))->map(fn ($d) => $debitKasQuery->clone()->whereDate('created_at', now()->subDays($d))->count())->toArray();

        return [
            Stat::make('Total Transaksi', $base->clone()->count())
                ->description('semua kategori')
                ->chart($trendTotal),

            Stat::make('Kredit', 'Rp ' . number_format($kreditQuery->clone()->sum('amount'), 0, ',', '.'))
                ->color('success')
                ->chart($trendKredit)
                ->description($kreditQuery->clone()->count() . ' transaksi'),

            Stat::make('Debit', 'Rp ' . number_format($debitQuery->clone()->sum('amount'), 0, ',', '.'))
                ->color('warning')
                ->chart($trendDebit)
                ->description($debitQuery->clone()->count() . ' transaksi'),

            Stat::make('Debit Kas', 'Rp ' . number_format($debitKasQuery->clone()->sum('amount'), 0, ',', '.'))
                ->color('danger')
                ->chart($trendDebitKas)
                ->description($debitKasQuery->clone()->count() . ' transaksi'),
        ];
    }
}
