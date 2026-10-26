<?php

namespace Tests\Feature\Imports;

use App\Enums\CustomerCategory;
use App\Filament\Imports\CustomerImporter;
use App\Models\Customer;
use Tests\Support\BankTestCase;

class CustomerImportTest extends BankTestCase
{
    private function import(array $rows): CustomerImporter
    {
        $importer = new CustomerImporter();
        $importer->collection(collect($rows));

        return $importer;
    }

    private function reasons(CustomerImporter $importer): array
    {
        return array_column($importer->failures, 'reason');
    }

    public function test_import_nasabah_berhasil(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([
            ['nama' => 'Budi Santoso', 'kategori' => 'siswa'],
            ['nama' => 'Ani Lestari', 'kategori' => 'guru'],
        ]);

        $this->assertSame(2, $importer->imported);
        $this->assertSame([], $importer->failures);
        $this->assertSame(2, Customer::count());
        $this->assertSame(CustomerCategory::Siswa, Customer::where('full_name', 'Budi Santoso')->first()->category);
    }

    public function test_kategori_boleh_nilai_atau_label_huruf_besar_kecil(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([
            ['nama' => 'Satu', 'kategori' => 'GURU'],
            ['nama' => 'Dua', 'kategori' => CustomerCategory::Staff->label()],
            ['nama' => 'Tiga', 'kategori' => 'lainnya'],
        ]);

        $this->assertSame(3, $importer->imported);
        $this->assertSame(CustomerCategory::Staff, Customer::where('full_name', 'Dua')->first()->category);
    }

    public function test_nasabah_baru_saldo_nol_aktif_dan_punya_nomor_rekening(): void
    {
        $user = $this->actingAsRole('admin');

        $this->import([['nama' => 'Budi', 'kategori' => 'siswa']]);

        $customer = Customer::first();

        $this->assertEquals(0, $customer->balance);
        $this->assertTrue((bool) $customer->is_active);
        $this->assertNotEmpty($customer->account_number);
        $this->assertSame($user->id, (int) $customer->created_by);
    }

    public function test_nama_yang_sudah_ada_dilewati_tanpa_peduli_huruf_dan_spasi(): void
    {
        $this->actingAsRole('admin');
        $this->makeCustomer(['full_name' => 'Budi Santoso']);

        $importer = $this->import([['nama' => '  budi   SANTOSO ', 'kategori' => 'siswa']]);

        $this->assertSame(0, $importer->imported);
        $this->assertContains('Nama sudah terdaftar', $this->reasons($importer));
        $this->assertSame(1, Customer::count());
    }

    public function test_nama_kembar_di_dalam_file_hanya_masuk_sekali(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([
            ['nama' => 'Budi', 'kategori' => 'siswa'],
            ['nama' => 'Budi', 'kategori' => 'guru'],
        ]);

        $this->assertSame(1, $importer->imported);
        $this->assertContains('Nama sudah terdaftar', $this->reasons($importer));
    }

    public function test_kategori_tidak_dikenali_dilewati(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([['nama' => 'Budi', 'kategori' => 'alien']]);

        $this->assertSame(0, $importer->imported);
        $this->assertContains('Kategori tidak dikenali', $this->reasons($importer));
    }

    public function test_nama_kosong_dilewati_dan_baris_kosong_diabaikan(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([
            ['nama' => '', 'kategori' => 'siswa'],
            ['nama' => '', 'kategori' => ''],
            ['nama' => 'Budi', 'kategori' => 'siswa'],
        ]);

        $this->assertSame(1, $importer->imported);
        $this->assertSame(['Nama kosong'], $this->reasons($importer));
    }

    public function test_nama_lebih_dari_255_karakter_ditolak(): void
    {
        $this->actingAsRole('admin');

        $importer = $this->import([['nama' => str_repeat('a', 256), 'kategori' => 'siswa']]);

        $this->assertSame(0, $importer->imported);
        $this->assertContains('Nama terlalu panjang (maksimal 255 karakter)', $this->reasons($importer));
    }
}