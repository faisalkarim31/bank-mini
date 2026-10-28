<?php

namespace Tests\Feature\Transactions;

use App\Enums\TransactionType;
use App\Filament\Widgets\TransactionStats;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class TransactionStatsTest extends BankTestCase
{
    private function siapkanData(): void
    {
        $customer = $this->makeCustomer(['full_name' => 'Budi Santoso', 'balance' => 1000000]);

        $this->makeTransaction($customer, TransactionType::SetorTunai, 100000);
        $this->makeTransaction($customer, TransactionType::TarikTunai, 30000, now()->subDays(3));
        $this->makeTransaction($customer, TransactionType::TarikKas, 5000, now()->subDays(10));
    }

    /** Kontrak filter sama seperti halaman detail nasabah: event 'transaction-filters-updated'. */
    private function stats(?string $from = null, ?string $until = null, ?string $type = null): array
    {
        $component = Livewire::test(TransactionStats::class);

        if ($from || $until || $type) {
            try {
                $component->dispatch('transaction-filters-changed', typeFilter: $type, dateFrom: $from, dateUntil: $until);
            } catch (\Throwable $e) {
                // Widget belum punya pendengar event: filter tidak diterapkan, test gagal di angka (bukan error).
                if (! str_contains($e::class, 'EventHandlerDoesNotExist')) {
                    throw $e;
                }
            }
        }

        $stats = (fn () => $this->getStats())->call($component->instance());

        $result = [];
        foreach ($stats as $stat) {
            $result[(string) $stat->getLabel()] = (string) $stat->getValue();
        }

        return $result;
    }

    public function test_admin_kartu_menjumlah_semua_transaksi_tanpa_filter(): void
    {
        $this->actingAsRole('admin', 'admin');
        $this->siapkanData();

        $stats = $this->stats();

        $this->assertSame('Rp 100.000', $stats['Kredit']);
        $this->assertSame('Rp 30.000', $stats['Debit']);
        $this->assertSame('Rp 5.000', $stats['Debit Kas']);
        $this->assertSame('Rp 65.000', $stats['Total Saldo']);
    }

    public function test_admin_kartu_mengikuti_filter_tanggal(): void
    {
        $this->actingAsRole('admin', 'admin');
        $this->siapkanData();

        $stats = $this->stats(now()->subDays(4)->toDateString(), now()->toDateString());

        $this->assertSame('Rp 100.000', $stats['Kredit']);
        $this->assertSame('Rp 30.000', $stats['Debit']);
        $this->assertSame('Rp 0', $stats['Debit Kas']);
    }

    public function test_admin_kartu_mengikuti_filter_jenis(): void
    {
        $this->actingAsRole('admin', 'admin');
        $this->siapkanData();

        $stats = $this->stats(null, null, 'tarik_tunai');

        $this->assertSame('Rp 0', $stats['Kredit']);
        $this->assertSame('Rp 30.000', $stats['Debit']);
        $this->assertSame('Rp 0', $stats['Debit Kas']);
    }

    public function test_teller_kartu_tetap_hari_ini_walau_filter_dikirim(): void
    {
        $this->actingAsRole('teller', 'teller');
        $this->siapkanData();

        $stats = $this->stats(now()->subDays(30)->toDateString(), now()->toDateString());

        $this->assertSame('Rp 100.000', $stats['Kredit Hari Ini']);
        $this->assertSame('Rp 0', $stats['Debit Hari Ini']);
    }
}