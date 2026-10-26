<?php

namespace Tests\Feature\Dashboard;

use App\Enums\TransactionType;
use App\Filament\Widgets\LatestTransactions;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TransactionCategoryChart;
use App\Filament\Widgets\TransactionChart;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class DashboardTest extends BankTestCase
{
    /** Satu transaksi hari ini (100.000) dan dua transaksi lama (700.000 dan 50.000). */
    private function seedTransaksi(): array
    {
        $customer = $this->makeCustomer(['full_name' => 'Budi Santoso', 'balance' => 5000000]);

        $hariIni = $this->makeTransaction($customer, TransactionType::SetorTunai, 100000);
        $lama1 = $this->makeTransaction($customer, TransactionType::SetorTunai, 700000, now()->subDays(3));
        $lama2 = $this->makeTransaction($customer, TransactionType::TarikTunai, 50000, now()->subDays(5));

        return [$hariIni, $lama1, $lama2];
    }

    private function stats(string $widget): array
    {
        $instance = Livewire::test($widget)->instance();

        $stats = (fn () => $this->getStats())->call($instance);

        $result = [];
        foreach ($stats as $stat) {
            $result[(string) $stat->getLabel()] = (string) $stat->getValue();
        }

        return $result;
    }

    private function chartData(string $widget): array
    {
        $instance = Livewire::test($widget)->instance();

        return (fn () => $this->getData())->call($instance);
    }

    // ---------- Kartu statistik ----------

    public function test_teller_kartu_statistik_hanya_hitung_hari_ini(): void
    {
        $this->actingAsRole('teller', 'teller');
        $this->seedTransaksi();

        $stats = $this->stats(StatsOverview::class);

        $this->assertSame('Rp 100.000', $stats['Kredit Hari Ini']);
        $this->assertSame('Rp 0', $stats['Debit Hari Ini']);
    }

    public function test_admin_kartu_statistik_hitung_semua_tanggal(): void
    {
        $this->actingAsRole('admin', 'admin');
        $this->seedTransaksi();

        $stats = $this->stats(StatsOverview::class);

        $this->assertSame('Rp 800.000', $stats['Kredit']);
        $this->assertSame('Rp 50.000', $stats['Debit']);
    }

    // ---------- Teller tidak boleh lihat data lama di dashboard ----------

    public function test_teller_transaksi_terbaru_hanya_hari_ini(): void
    {
        $this->actingAsRole('teller', 'teller');
        [$hariIni, $lama1, $lama2] = $this->seedTransaksi();

        Livewire::test(LatestTransactions::class)
            ->assertCanSeeTableRecords([$hariIni])
            ->assertCanNotSeeTableRecords([$lama1, $lama2]);
    }

    public function test_teller_tren_transaksi_hanya_hari_ini(): void
    {
        $this->actingAsRole('teller', 'teller');
        $this->seedTransaksi();

        $data = $this->chartData(TransactionChart::class);

        $this->assertSame(100000, array_sum($data['datasets'][0]['data']));
    }

    public function test_teller_kategori_transaksi_hanya_hari_ini(): void
    {
        $this->actingAsRole('teller', 'teller');
        $this->seedTransaksi();

        $data = $this->chartData(TransactionCategoryChart::class);

        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
    }

    // ---------- Admin tetap lihat semua ----------

    public function test_admin_transaksi_terbaru_semua_tanggal(): void
    {
        $this->actingAsRole('admin', 'admin');
        [$hariIni, $lama1, $lama2] = $this->seedTransaksi();

        Livewire::test(LatestTransactions::class)
            ->assertCanSeeTableRecords([$hariIni, $lama1, $lama2]);
    }

    public function test_admin_kategori_transaksi_menghitung_semua(): void
    {
        $this->actingAsRole('admin', 'admin');
        $this->seedTransaksi();

        $data = $this->chartData(TransactionCategoryChart::class);

        $this->assertSame(3, array_sum($data['datasets'][0]['data']));
    }
}