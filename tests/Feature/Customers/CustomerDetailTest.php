<?php

namespace Tests\Feature\Customers;

use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Admin\Resources\Customers\RelationManagers\TransactionsRelationManager;
use App\Filament\Widgets\CustomerTransactionStatsWidget;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class CustomerDetailTest extends BankTestCase
{
    private Customer $budi;

    private array $trx = [];

    private function siapkanData(): void
    {
        $this->actingAsRole('admin', 'admin');

        $this->budi = $this->makeCustomer(['full_name' => 'Budi Santoso', 'balance' => 1000000]);
        $ani = $this->makeCustomer(['full_name' => 'Ani Lestari', 'balance' => 1000000]);

        $this->trx = [
            'kredit_hari_ini' => $this->makeTransaction($this->budi, TransactionType::SetorTunai, 100000),
            'debit_3_hari' => $this->makeTransaction($this->budi, TransactionType::TarikTunai, 30000, now()->subDays(3)),
            'kas_10_hari' => $this->makeTransaction($this->budi, TransactionType::TarikKas, 5000, now()->subDays(10)),
            'milik_ani' => $this->makeTransaction($ani, TransactionType::SetorTunai, 999000),
        ];
    }

    private function riwayat()
    {
        return Livewire::test(TransactionsRelationManager::class, [
            'ownerRecord' => $this->budi,
            'pageClass' => ViewCustomer::class,
        ]);
    }

    private function stats(?string $from = null, ?string $until = null, ?string $type = null): array
    {
        $component = Livewire::test(CustomerTransactionStatsWidget::class, ['record' => $this->budi])
            ->call('applyFilters', $from, $until, $type);

        $stats = (fn () => $this->getStats())->call($component->instance());

        $result = [];
        foreach ($stats as $stat) {
            $result[(string) $stat->getLabel()] = (string) $stat->getValue();
        }

        return $result;
    }

    // ---------- Halaman detail ----------

    public function test_admin_membuka_halaman_detail_nasabah(): void
    {
        $this->siapkanData();

        Livewire::test(ViewCustomer::class, ['record' => $this->budi->getKey()])
            ->assertSuccessful()
            ->assertSee('Budi Santoso');
    }

    // ---------- Riwayat transaksi ----------

    public function test_riwayat_hanya_berisi_transaksi_nasabah_itu(): void
    {
        $this->siapkanData();

        $this->riwayat()
            ->assertCanSeeTableRecords([
                $this->trx['kredit_hari_ini'],
                $this->trx['debit_3_hari'],
                $this->trx['kas_10_hari'],
            ])
            ->assertCanNotSeeTableRecords([$this->trx['milik_ani']]);
    }

    public function test_riwayat_filter_jenis(): void
    {
        $this->siapkanData();

        $this->riwayat()
            ->call('applyFilters', null, null, 'tarik_tunai')
            ->assertCanSeeTableRecords([$this->trx['debit_3_hari']])
            ->assertCanNotSeeTableRecords([$this->trx['kredit_hari_ini'], $this->trx['kas_10_hari']]);
    }

    public function test_riwayat_filter_tanggal(): void
    {
        $this->siapkanData();

        $this->riwayat()
            ->call('applyFilters', now()->subDays(4)->toDateString(), now()->toDateString(), null)
            ->assertCanSeeTableRecords([$this->trx['kredit_hari_ini'], $this->trx['debit_3_hari']])
            ->assertCanNotSeeTableRecords([$this->trx['kas_10_hari']]);
    }

    public function test_riwayat_filter_jenis_dan_tanggal_digabung(): void
    {
        $this->siapkanData();

        $this->riwayat()
            ->call('applyFilters', now()->subDays(4)->toDateString(), null, 'setor_tunai')
            ->assertCanSeeTableRecords([$this->trx['kredit_hari_ini']])
            ->assertCanNotSeeTableRecords([$this->trx['debit_3_hari'], $this->trx['kas_10_hari']]);
    }

    public function test_riwayat_reset_filter_menampilkan_semua_lagi(): void
    {
        $this->siapkanData();

        $this->riwayat()
            ->call('applyFilters', null, null, 'tarik_tunai')
            ->call('applyFilters', null, null, null)
            ->assertCanSeeTableRecords([
                $this->trx['kredit_hari_ini'],
                $this->trx['debit_3_hari'],
                $this->trx['kas_10_hari'],
            ]);
    }

    // ---------- Kartu statistik nasabah ----------

    public function test_kartu_statistik_menjumlah_per_jenis(): void
    {
        $this->siapkanData();

        $stats = $this->stats();

        $this->assertSame('3', $stats['Total Transaksi']);
        $this->assertSame('Rp 100.000', $stats['Kredit']);
        $this->assertSame('Rp 30.000', $stats['Debit']);
        $this->assertSame('Rp 5.000', $stats['Debit Kas']);
    }

    public function test_kartu_statistik_tidak_menghitung_transaksi_nasabah_lain(): void
    {
        $this->siapkanData();

        $this->assertNotSame('Rp 1.099.000', $this->stats()['Kredit']);
        $this->assertSame('Rp 100.000', $this->stats()['Kredit']);
    }

    public function test_kartu_statistik_mengikuti_filter_tanggal(): void
    {
        $this->siapkanData();

        $stats = $this->stats(now()->subDays(4)->toDateString(), now()->toDateString());

        $this->assertSame('2', $stats['Total Transaksi']);
        $this->assertSame('Rp 0', $stats['Debit Kas']);
    }

    public function test_kartu_statistik_mengikuti_filter_jenis(): void
    {
        $this->siapkanData();

        $stats = $this->stats(null, null, 'setor_tunai');

        $this->assertSame('1', $stats['Total Transaksi']);
        $this->assertSame('Rp 100.000', $stats['Kredit']);
        $this->assertSame('Rp 0', $stats['Debit']);
    }
}