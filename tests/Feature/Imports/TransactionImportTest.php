<?php

namespace Tests\Feature\Imports;

use App\Filament\Imports\TransactionImporter;
use App\Models\Transaction;
use Tests\Support\BankTestCase;

class TransactionImportTest extends BankTestCase
{
    private function row(string $name, string $type, mixed $amount, mixed $date = null, string $by = 'Budi'): array
    {
        return [
            'tanggal_waktu' => $date ?? now()->subHour()->format('Y-m-d H:i'),
            'nama_nasabah' => $name,
            'nominal' => $amount,
            'jenis_transaksi' => $type,
            'diproses_oleh' => $by,
        ];
    }

    private function import(array $rows): TransactionImporter
    {
        $importer = new TransactionImporter();
        $importer->collection(collect($rows));

        return $importer;
    }

    private function reasons(TransactionImporter $importer): array
    {
        return array_column($importer->failures, 'reason');
    }

    public function test_import_setor_menambah_saldo_dan_menyimpan_data_dari_file(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi Santoso', 'balance' => 0]);
        $date = now()->subDays(2)->setTime(10, 30);

        $importer = $this->import([
            $this->row('Budi Santoso', 'setor_tunai', 150000, $date->format('Y-m-d H:i'), 'Rina Wulandari'),
        ]);

        $this->assertSame(1, $importer->imported);
        $this->assertEquals(150000, $this->balanceOf($customer));

        $trx = Transaction::first();
        $this->assertSame($date->format('Y-m-d H:i'), $trx->created_at->format('Y-m-d H:i'));
        $this->assertSame('Rina Wulandari', $trx->processed_by);
        $this->assertSame('TRX' . $date->format('ymd') . '001', $trx->transaction_code);
    }

    public function test_jenis_boleh_nilai_atau_label(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi', 'balance' => 500000]);

        $importer = $this->import([
            $this->row('Budi', 'Kredit', 10000),
            $this->row('Budi', 'Debit', 5000),
            $this->row('Budi', 'tarik_kas', 5000),
        ]);

        $this->assertSame(3, $importer->imported);
    }

    public function test_tarik_melebihi_saldo_ditolak(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi', 'balance' => 50000]);

        $importer = $this->import([$this->row('Budi', 'tarik_tunai', 60000)]);

        $this->assertSame(0, $importer->imported);
        $this->assertContains('Saldo tidak mencukupi', $this->reasons($importer));
        $this->assertEquals(50000, $this->balanceOf($customer));
        $this->assertSame(0, Transaction::count());
    }

    public function test_tarik_sama_dengan_saldo_diterima_dan_saldo_nol(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi', 'balance' => 50000]);

        $importer = $this->import([$this->row('Budi', 'tarik_tunai', 50000)]);

        $this->assertSame(1, $importer->imported);
        $this->assertEquals(0, $this->balanceOf($customer));
    }

    public function test_urutan_diproses_berdasarkan_tanggal_bukan_urutan_baris(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi', 'balance' => 0]);

        // Tarik ditulis duluan, tapi tanggalnya setelah setor.
        $importer = $this->import([
            $this->row('Budi', 'tarik_tunai', 50000, now()->subHour()->format('Y-m-d H:i')),
            $this->row('Budi', 'setor_tunai', 100000, now()->subHours(2)->format('Y-m-d H:i')),
        ]);

        $this->assertSame(2, $importer->imported);
        $this->assertEquals(50000, $this->balanceOf($customer));
    }

    public function test_saldo_tidak_pernah_minus_setelah_import_campuran(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi', 'balance' => 10000]);

        $this->import([
            $this->row('Budi', 'tarik_tunai', 8000, now()->subHours(3)->format('Y-m-d H:i')),
            $this->row('Budi', 'tarik_tunai', 8000, now()->subHours(2)->format('Y-m-d H:i')),
            $this->row('Budi', 'tarik_kas', 8000, now()->subHour()->format('Y-m-d H:i')),
        ]);

        $this->assertGreaterThanOrEqual(0, $this->balanceOf($customer));
    }

    public function test_nasabah_tidak_ditemukan_dilewati(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([$this->row('Tidak Ada', 'setor_tunai', 10000)]);

        $this->assertContains('Nasabah tidak ditemukan', $this->reasons($importer));
    }

    public function test_nasabah_nonaktif_dilewati(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi', 'is_active' => false]);

        $importer = $this->import([$this->row('Budi', 'setor_tunai', 10000)]);

        $this->assertContains('Nasabah nonaktif', $this->reasons($importer));
    }

    public function test_dua_nasabah_aktif_nama_sama_dilewati(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi']);
        $this->makeCustomer(['full_name' => 'Budi']);

        $importer = $this->import([$this->row('Budi', 'setor_tunai', 10000)]);

        $this->assertSame(0, $importer->imported);
        $this->assertContains('Nama nasabah kembar', $this->reasons($importer));
    }

    public function test_tanggal_masa_depan_ditolak(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi']);

        $importer = $this->import([
            $this->row('Budi', 'setor_tunai', 10000, now()->addDay()->format('Y-m-d H:i')),
        ]);

        $this->assertContains('Tanggal di masa depan', $this->reasons($importer));
    }

    public function test_format_tanggal_indonesia_dikenali(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi']);
        $date = now()->subDays(3)->setTime(9, 15);

        $importer = $this->import([
            $this->row('Budi', 'setor_tunai', 10000, $date->format('d/m/Y H:i')),
        ]);

        $this->assertSame(1, $importer->imported);
        $this->assertSame($date->format('Y-m-d H:i'), Transaction::first()->created_at->format('Y-m-d H:i'));
    }

    public function test_tanggal_tidak_dikenali_atau_kosong_ditolak(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi']);

        $importer = $this->import([
            $this->row('Budi', 'setor_tunai', 10000, 'kemarin sore'),
            $this->row('Budi', 'setor_tunai', 10000, ''),
        ]);

        $this->assertSame(0, $importer->imported);
        $this->assertContains('Format tanggal tidak dikenali', $this->reasons($importer));
        $this->assertContains('Tanggal kosong', $this->reasons($importer));
    }

    public function test_nominal_format_rupiah_diterima(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi', 'balance' => 0]);

        $importer = $this->import([$this->row('Budi', 'setor_tunai', 'Rp 1.500.000')]);

        $this->assertSame(1, $importer->imported);
        $this->assertEquals(1500000, $this->balanceOf($customer));
    }

    public function test_nominal_tidak_valid_ditolak(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi']);

        $importer = $this->import([
            $this->row('Budi', 'setor_tunai', 'abc'),
            $this->row('Budi', 'setor_tunai', 1500.5),
            $this->row('Budi', 'setor_tunai', -5000),
            $this->row('Budi', 'setor_tunai', 999),
        ]);

        $this->assertSame(0, $importer->imported);
        $this->assertSame(0, Transaction::count());
    }

    public function test_jenis_tidak_dikenali_dan_diproses_oleh_kosong_ditolak(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi']);

        $importer = $this->import([
            $this->row('Budi', 'transfer', 10000),
            $this->row('Budi', 'setor_tunai', 10000, null, ''),
        ]);

        $this->assertContains('Jenis transaksi tidak dikenali', $this->reasons($importer));
        $this->assertContains('Diproses oleh kosong', $this->reasons($importer));
        $this->assertSame(0, $importer->imported);
    }

    public function test_transaksi_sama_persis_dilewati_sebagai_duplikat(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Budi', 'balance' => 0]);
        $date = now()->subHour()->format('Y-m-d H:i');

        $importer = $this->import([
            $this->row('Budi', 'setor_tunai', 10000, $date),
            $this->row('Budi', 'setor_tunai', 10000, $date),
        ]);

        $this->assertSame(1, $importer->imported);
        $this->assertContains('Transaksi sudah ada', $this->reasons($importer));
        $this->assertEquals(10000, $this->balanceOf($customer));
    }

    public function test_baris_kosong_diabaikan_tanpa_gagal(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([
            ['tanggal_waktu' => null, 'nama_nasabah' => '', 'nominal' => null, 'jenis_transaksi' => '', 'diproses_oleh' => ''],
        ]);

        $this->assertSame(0, $importer->imported);
        $this->assertSame([], $importer->failures);
    }
}