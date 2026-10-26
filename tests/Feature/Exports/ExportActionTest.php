<?php

namespace Tests\Feature\Exports;

use App\Enums\CustomerCategory;
use App\Enums\TransactionType;
use App\Filament\Admin\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Exports\AuditLogExporter;
use App\Filament\Exports\CustomerExporter;
use App\Filament\Exports\TransactionExporter;
use App\Models\AuditLog;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\BankTestCase;

class ExportActionTest extends BankTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime(); // nama file memakai jam, bekukan supaya bisa dicocokkan
        Excel::fake();
    }

    public function test_export_nasabah_mengikuti_filter_kategori(): void
    {
        $this->actingAsRole('admin');
        $siswa = $this->makeCustomer(['full_name' => 'A', 'category' => CustomerCategory::Siswa]);
        $guru = $this->makeCustomer(['full_name' => 'B', 'category' => CustomerCategory::Guru]);

        Livewire::test(ListCustomers::class)
            ->set('categoryFilter', 'guru')
            ->callAction('export');

        Excel::assertDownloaded(
            'data-nasabah-' . now()->format('Y-m-d') . '.xlsx',
            fn (CustomerExporter $export) => $export->query()->pluck('id')->all() === [$guru->id],
        );
    }

    public function test_export_transaksi_admin_mengikuti_filter_jenis(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 100000]);
        $this->makeTransaction($customer, TransactionType::SetorTunai, 10000);
        $tarik = $this->makeTransaction($customer, TransactionType::TarikTunai, 5000);

        Livewire::test(ListTransactions::class)
            ->set('typeFilter', 'tarik_tunai')
            ->callAction('export');

        Excel::assertDownloaded(
            'transaksi-' . now()->format('Ymd-His') . '.xlsx',
            fn (TransactionExporter $export) => $export->query()->pluck('id')->all() === [$tarik->id],
        );
    }

    public function test_export_transaksi_teller_hanya_hari_ini(): void
    {
        // Teller hanya boleh lihat transaksi hari ini di tabel. Export tidak boleh membuka lebih dari itu.
        $this->actingAsRole('teller');
        $customer = $this->makeCustomer();
        $kemarin = $this->makeTransaction($customer, at: now()->subDay());
        $hariIni = $this->makeTransaction($customer);

        Livewire::test(ListTransactions::class)->callAction('export');

        Excel::assertDownloaded(
            'transaksi-' . now()->format('Ymd-His') . '.xlsx',
            fn (TransactionExporter $export) => $export->query()->pluck('id')->all() === [$hariIni->id],
        );
    }

    public function test_export_riwayat_mengikuti_filter_tanggal(): void
    {
        $admin = $this->actingAsRole('admin');
        $lama = AuditLog::create(['user_id' => $admin->id, 'action' => 'Login']);
        $lama->created_at = now()->subDays(10);
        $lama->save();
        $baru = AuditLog::create(['user_id' => $admin->id, 'action' => 'Login']);

        Livewire::test(ManageAuditLogs::class)
            ->set('dateFrom', now()->subDays(2)->toDateString())
            ->callAction('export');

        Excel::assertDownloaded(
            'riwayat-aktivitas-' . now()->format('Ymd-His') . '.xlsx',
            fn (AuditLogExporter $export) => $export->query()->pluck('id')->all() === [$baru->id],
        );
    }
}