<?php

namespace Tests\Feature\Transactions;

use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use App\Models\Transaction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class TransactionTest extends BankTestCase
{
    public function test_admin_tambah_setor_menambah_saldo(): void
    {
        $admin = $this->actingAsRole('admin');
        $customer = $this->makeCustomer();

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'setor_tunai',
                'customer_id' => $customer->id,
                'amount' => 50000,
                'processed_by' => 'Andi',
            ])
            ->assertHasNoActionErrors();

        $trx = Transaction::firstOrFail();
        $this->assertSame('TRX' . now()->format('ymd') . '001', $trx->transaction_code);
        $this->assertSame(50000.0, $this->balanceOf($customer));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Tambah Transaksi']);
    }

    public function test_tarik_mengurangi_saldo(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 100000]);

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'tarik_tunai',
                'customer_id' => $customer->id,
                'amount' => 30000,
                'processed_by' => 'Andi',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(70000.0, $this->balanceOf($customer));
    }

    public function test_tarik_melebihi_saldo_ditolak(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 20000]);

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'tarik_tunai',
                'customer_id' => $customer->id,
                'amount' => 50000,
                'processed_by' => 'Andi',
            ])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(0, Transaction::count());
        $this->assertSame(20000.0, $this->balanceOf($customer));
    }

    public function test_tarik_kas_melebihi_saldo_ditolak(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 1000]);

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'tarik_kas',
                'customer_id' => $customer->id,
                'amount' => 5000,
                'processed_by' => 'Andi',
            ])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(0, Transaction::count());
    }

    public function test_nominal_di_bawah_minimum_ditolak(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'setor_tunai',
                'customer_id' => $customer->id,
                'amount' => 500,
                'processed_by' => 'Andi',
            ])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(0, Transaction::count());
    }

    public function test_field_wajib(): void
    {
        $this->actingAsRole('admin');

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'setor_tunai',
                'customer_id' => null,
                'amount' => null,
                'processed_by' => '',
            ])
            ->assertHasActionErrors(['customer_id' => 'required', 'amount' => 'required', 'processed_by' => 'required']);
    }

    public function test_nasabah_nonaktif_ditolak(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['is_active' => false]);

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'setor_tunai',
                'customer_id' => $customer->id,
                'amount' => 10000,
                'processed_by' => 'Andi',
            ])
            ->assertHasActionErrors(['customer_id']);

        $this->assertSame(0, Transaction::count());
    }

    public function test_teller_tambah_transaksi(): void
    {
        $this->actingAsRole('teller');
        $customer = $this->makeCustomer();

        Livewire::test(ListTransactions::class)
            ->callAction('create', [
                'type' => 'setor_tunai',
                'customer_id' => $customer->id,
                'amount' => 25000,
                'processed_by' => 'Teller',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(25000.0, $this->balanceOf($customer));
    }

    public function test_search_berdasarkan_nama_nasabah(): void
    {
        $this->actingAsRole('admin');
        $budi = $this->makeCustomer(['full_name' => 'Budi Santoso']);
        $sari = $this->makeCustomer(['full_name' => 'Sari Wulandari']);
        $t1 = $this->makeTransaction($budi);
        $t2 = $this->makeTransaction($sari);

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$t1, $t2])
            ->searchTable('Sari')
            ->assertCanSeeTableRecords([$t2])
            ->assertCanNotSeeTableRecords([$t1]);
    }

    public function test_filter_jenis_transaksi(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 100000]);
        $setor = $this->makeTransaction($customer, TransactionType::SetorTunai, 10000);
        $tarik = $this->makeTransaction($customer, TransactionType::TarikTunai, 5000);

        Livewire::test(ListTransactions::class)
            ->set('typeFilter', 'tarik_tunai')
            ->assertCanSeeTableRecords([$tarik])
            ->assertCanNotSeeTableRecords([$setor])
            ->set('typeFilter', null)
            ->assertCanSeeTableRecords([$setor, $tarik]);
    }

    public function test_filter_tanggal_admin(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $lama = $this->makeTransaction($customer, at: now()->subDays(10));
        $baru = $this->makeTransaction($customer);

        Livewire::test(ListTransactions::class)
            ->set('dateFrom', now()->subDays(2)->toDateString())
            ->assertCanSeeTableRecords([$baru])
            ->assertCanNotSeeTableRecords([$lama])
            ->set('dateFrom', null)
            ->set('dateUntil', now()->subDays(5)->toDateString())
            ->assertCanSeeTableRecords([$lama])
            ->assertCanNotSeeTableRecords([$baru]);
    }

    public function test_teller_hanya_lihat_transaksi_hari_ini(): void
    {
        $this->actingAsRole('teller');
        $customer = $this->makeCustomer();
        $kemarin = $this->makeTransaction($customer, at: now()->subDay());
        $hariIni = $this->makeTransaction($customer);

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$hariIni])
            ->assertCanNotSeeTableRecords([$kemarin])
            ->assertActionHidden('filterTanggal');
    }

    public function test_admin_lihat_semua_transaksi_dan_filter_tanggal(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $kemarin = $this->makeTransaction($customer, at: now()->subDay());
        $hariIni = $this->makeTransaction($customer);

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$kemarin, $hariIni])
            ->assertActionVisible('filterTanggal');
    }

    public function test_teller_tidak_bisa_edit_transaksi(): void
    {
        $this->actingAsRole('teller');
        $trx = $this->makeTransaction($this->makeCustomer());

        Livewire::test(ListTransactions::class)
            ->assertActionHidden(TestAction::make('edit')->table($trx));
    }

    public function test_edit_transaksi_nasabah_nonaktif_tersembunyi(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $trx = $this->makeTransaction($customer);
        $customer->update(['is_active' => false]);

        Livewire::test(ListTransactions::class)
            ->assertActionHidden(TestAction::make('edit')->table($trx));
    }

    public function test_admin_edit_nominal_menyesuaikan_saldo(): void
    {
        $admin = $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $trx = $this->makeTransaction($customer, TransactionType::SetorTunai, 10000);

        Livewire::test(ListTransactions::class)
            ->assertActionVisible(TestAction::make('edit')->table($trx))
            ->callAction(TestAction::make('edit')->table($trx), [
                'type' => 'setor_tunai',
                'amount' => 25000,
                'processed_by' => 'Tester',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(25000.0, $this->balanceOf($customer));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'Edit Transaksi']);
    }

    public function test_admin_edit_jenis_menyesuaikan_saldo(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 50000]);
        $trx = $this->makeTransaction($customer, TransactionType::SetorTunai, 10000); // saldo 60000

        Livewire::test(ListTransactions::class)
            ->callAction(TestAction::make('edit')->table($trx), [
                'type' => 'tarik_tunai',
                'amount' => 10000,
                'processed_by' => 'Tester',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(40000.0, $this->balanceOf($customer));
    }

    public function test_edit_yang_bikin_saldo_minus_ditolak(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $trx = $this->makeTransaction($customer, TransactionType::SetorTunai, 10000); // saldo 10000

        Livewire::test(ListTransactions::class)
            ->callAction(TestAction::make('edit')->table($trx), [
                'type' => 'tarik_tunai',
                'amount' => 20000,
                'processed_by' => 'Tester',
            ]);

        $this->assertSame(10000.0, $this->balanceOf($customer));
        $this->assertSame(TransactionType::SetorTunai, $trx->fresh()->type);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Edit Transaksi']);
    }
}