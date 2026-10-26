<?php

namespace Tests\Feature\Customers;

use App\Enums\CustomerCategory;
use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class CustomerTest extends BankTestCase
{
    public function test_admin_lihat_daftar_dan_search(): void
    {
        $this->actingAsRole('admin');
        $budi = $this->makeCustomer(['full_name' => 'Budi Santoso']);
        $sari = $this->makeCustomer(['full_name' => 'Sari Wulandari', 'category' => CustomerCategory::Guru]);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$budi, $sari])
            ->searchTable('Sari')
            ->assertCanSeeTableRecords([$sari])
            ->assertCanNotSeeTableRecords([$budi])
            ->searchTable($budi->account_number)
            ->assertCanSeeTableRecords([$budi])
            ->assertCanNotSeeTableRecords([$sari]);
    }

    public function test_filter_kategori_dan_status(): void
    {
        $this->actingAsRole('admin');
        $siswa = $this->makeCustomer(['full_name' => 'A', 'category' => CustomerCategory::Siswa]);
        $guru = $this->makeCustomer(['full_name' => 'B', 'category' => CustomerCategory::Guru]);
        $nonaktif = $this->makeCustomer(['full_name' => 'C', 'is_active' => false]);

        Livewire::test(ListCustomers::class)
            ->set('categoryFilter', 'guru')
            ->assertCanSeeTableRecords([$guru])
            ->assertCanNotSeeTableRecords([$siswa, $nonaktif])
            ->set('categoryFilter', null)
            ->set('statusFilter', 'nonaktif')
            ->assertCanSeeTableRecords([$nonaktif])
            ->assertCanNotSeeTableRecords([$siswa, $guru])
            ->set('statusFilter', 'aktif')
            ->assertCanSeeTableRecords([$siswa, $guru])
            ->assertCanNotSeeTableRecords([$nonaktif]);
    }

    public function test_admin_tambah_nasabah(): void
    {
        $admin = $this->actingAsRole('admin');

        Livewire::test(ListCustomers::class)
            ->callAction('create', ['full_name' => 'Nasabah Baru', 'category' => 'siswa'])
            ->assertHasNoActionErrors();

        $customer = Customer::where('full_name', 'Nasabah Baru')->firstOrFail();

        $this->assertSame('01' . now()->format('y') . '000001', $customer->account_number);
        $this->assertEquals(0, $customer->balance);
        $this->assertTrue((bool) $customer->is_active);
        $this->assertSame($admin->id, $customer->created_by);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'Tambah Nasabah',
            'target_name' => 'Nasabah Baru',
        ]);
    }

    public function test_nomor_rekening_naik_per_kategori(): void
    {
        $this->actingAsRole('admin');
        $y = now()->format('y');

        $a = $this->makeCustomer(['category' => CustomerCategory::Siswa]);
        $b = $this->makeCustomer(['category' => CustomerCategory::Siswa]);
        $g = $this->makeCustomer(['category' => CustomerCategory::Guru]);

        $this->assertSame("01{$y}000001", $a->account_number);
        $this->assertSame("01{$y}000002", $b->account_number);
        $this->assertSame("02{$y}000001", $g->account_number);
    }

    public function test_tambah_nasabah_validasi(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ListCustomers::class)
            ->callAction('create', ['full_name' => '', 'category' => null])
            ->assertHasActionErrors(['full_name' => 'required', 'category' => 'required']);

        $this->assertSame(0, Customer::count());
    }

    public function test_admin_edit_nasabah(): void
    {
        $admin = $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['full_name' => 'Lama']);

        Livewire::test(ListCustomers::class)
            ->callAction(TestAction::make('edit')->table($customer), ['full_name' => 'Baru', 'category' => 'guru'])
            ->assertHasNoActionErrors();

        $customer->refresh();
        $this->assertSame('Baru', $customer->full_name);
        $this->assertSame(CustomerCategory::Guru, $customer->category);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Edit Nasabah']);
    }

    public function test_admin_nonaktifkan_dan_aktifkan_nasabah(): void
    {
        $admin = $this->actingAsRole('admin');
        $customer = $this->makeCustomer();

        Livewire::test(ListCustomers::class)
            ->callAction(TestAction::make('toggleActive')->table($customer));

        $this->assertFalse((bool) $customer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Nonaktifkan Nasabah']);

        Livewire::test(ListCustomers::class)
            ->callAction(TestAction::make('toggleActive')->table($customer));

        $this->assertTrue((bool) $customer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Aktifkan Nasabah']);
    }

    public function test_admin_buka_detail_nasabah(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();

        $this->get("/admin/customers/{$customer->id}")->assertOk();
    }

    public function test_teller_tambah_dan_edit_nasabah(): void
    {
        $teller = $this->actingAsRole('teller');

        Livewire::test(ListCustomers::class)
            ->callAction('create', ['full_name' => 'Dari Teller', 'category' => 'staff'])
            ->assertHasNoActionErrors();

        $customer = Customer::where('full_name', 'Dari Teller')->firstOrFail();
        $this->assertSame($teller->id, $customer->created_by);

        Livewire::test(ListCustomers::class)
            ->callAction(TestAction::make('edit')->table($customer), ['full_name' => 'Diedit Teller', 'category' => 'staff'])
            ->assertHasNoActionErrors();

        $this->assertSame('Diedit Teller', $customer->fresh()->full_name);
    }

    public function test_teller_tidak_bisa_nonaktifkan_nasabah(): void
    {
        $this->actingAsRole('teller');
        $customer = $this->makeCustomer();

        Livewire::test(ListCustomers::class)
            ->assertActionHidden(TestAction::make('toggleActive')->table($customer));

        $this->assertTrue((bool) $customer->fresh()->is_active);
    }

    public function test_admin_lihat_tombol_nonaktifkan(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();

        Livewire::test(ListCustomers::class)
            ->assertActionVisible(TestAction::make('toggleActive')->table($customer));
    }
}