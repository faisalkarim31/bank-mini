<?php

namespace Tests\Unit\Exports;

use App\Enums\CustomerCategory;
use App\Enums\TransactionType;
use App\Filament\Exports\AuditLogExporter;
use App\Filament\Exports\CustomerExporter;
use App\Filament\Exports\TransactionExporter;
use App\Models\AuditLog;
use App\Models\User;
use Tests\Support\BankTestCase;

class ExporterQueryTest extends BankTestCase
{
    // ---------- CustomerExporter ----------

    public function test_export_nasabah_tanpa_filter_berisi_semua(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'A']);
        $this->makeCustomer(['full_name' => 'B', 'is_active' => false]);

        $this->assertCount(2, (new CustomerExporter())->query()->get());
    }

    public function test_export_nasabah_filter_kategori_dan_status(): void
    {
        $this->actingAsRole('admin');
        $siswa = $this->makeCustomer(['full_name' => 'A', 'category' => CustomerCategory::Siswa]);
        $guru = $this->makeCustomer(['full_name' => 'B', 'category' => CustomerCategory::Guru]);
        $nonaktif = $this->makeCustomer(['full_name' => 'C', 'is_active' => false]);

        $byCategory = (new CustomerExporter('guru', null))->query()->pluck('id')->all();
        $this->assertSame([$guru->id], $byCategory);

        $byStatus = (new CustomerExporter(null, 'nonaktif'))->query()->pluck('id')->all();
        $this->assertSame([$nonaktif->id], $byStatus);
    }

    public function test_export_nasabah_isi_baris(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi', 'category' => CustomerCategory::Guru, 'balance' => 75000]);

        $exporter = new CustomerExporter();
        $row = $exporter->map($exporter->query()->first());

        $this->assertSame('Budi', $row[1]);
        $this->assertSame('Guru', $row[2]);
        $this->assertSame(75000, $row[3]);
        $this->assertSame('Aktif', $row[4]);
    }

    // ---------- TransactionExporter ----------

    public function test_export_transaksi_filter_jenis(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 100000]);
        $this->makeTransaction($customer, TransactionType::SetorTunai, 10000);
        $tarik = $this->makeTransaction($customer, TransactionType::TarikTunai, 5000);

        $ids = (new TransactionExporter('tarik_tunai'))->query()->pluck('id')->all();

        $this->assertSame([$tarik->id], $ids);
    }

    public function test_export_transaksi_filter_tanggal(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $lama = $this->makeTransaction($customer, at: now()->subDays(10));
        $baru = $this->makeTransaction($customer);

        $dariBaru = (new TransactionExporter(null, now()->subDays(2)->toDateString()))->query()->pluck('id')->all();
        $this->assertSame([$baru->id], $dariBaru);

        $sampaiLama = (new TransactionExporter(null, null, now()->subDays(5)->toDateString()))->query()->pluck('id')->all();
        $this->assertSame([$lama->id], $sampaiLama);
    }

    public function test_export_transaksi_isi_baris(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Sari']);
        $this->makeTransaction($customer, TransactionType::SetorTunai, 10000);

        $exporter = new TransactionExporter();
        $row = $exporter->map($exporter->query()->first());

        $this->assertSame('Sari', $row[2]);
        $this->assertSame($customer->account_number, $row[3]);
        $this->assertSame('Kredit', $row[4]);
        $this->assertEquals(10000, (float) $row[5]);
    }

    public function test_kolom_diproses_oleh_berisi_nama_pegawai(): void
    {
        // processed_by berisi teks nama ("Tester"), tetapi export membaca relasi processor() ke tabel users.
        $this->actingAsRole('admin');
        $this->makeTransaction($this->makeCustomer());

        $exporter = new TransactionExporter();
        $row = $exporter->map($exporter->query()->first());

        $this->assertSame('Tester', $row[6]);
    }

    // ---------- AuditLogExporter ----------

    public function test_export_riwayat_filter_peran_dan_tanggal(): void
    {
        $admin = $this->actingAsRole('admin');
        $teller = User::factory()->create(['role' => 'teller']);

        $a = AuditLog::create(['user_id' => $admin->id, 'action' => 'Login']);
        $t = AuditLog::create(['user_id' => $teller->id, 'action' => 'Login']);
        $lama = AuditLog::create(['user_id' => $admin->id, 'action' => 'Login']);
        $lama->created_at = now()->subDays(10);
        $lama->save();

        $byRole = (new AuditLogExporter('teller'))->query()->pluck('id')->all();
        $this->assertSame([$t->id], $byRole);

        $byDate = (new AuditLogExporter(null, now()->subDays(2)->toDateString()))->query()->pluck('id')->sort()->values()->all();
        $this->assertSame([$a->id, $t->id], $byDate);
    }

    public function test_export_riwayat_isi_baris(): void
    {
        $admin = $this->actingAsRole('admin', 'admin', ['full_name' => 'Pak Admin']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'Tambah Nasabah', 'target_name' => 'Budi']);

        $exporter = new AuditLogExporter();
        $row = $exporter->map($exporter->query()->first());

        $this->assertSame('Pak Admin', $row[1]);
        $this->assertSame('Admin', $row[2]);
        $this->assertSame('Tambah Nasabah', $row[3]);
        $this->assertSame('Budi', $row[4]);
    }
}