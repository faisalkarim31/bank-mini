<?php

namespace Tests\Feature\Imports;

use App\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use App\Filament\Admin\Resources\Transactions\Pages\ListTransactions;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

/**
 * CR-2: import hanya untuk admin. Teller tidak boleh melihat atau menjalankan import.
 * Dua test teller akan merah sampai CR-2 dikerjakan.
 */
class ImportAccessTest extends BankTestCase
{
    public function test_admin_melihat_import_nasabah(): void
    {
        $this->actingAsRole('admin', 'admin');

        Livewire::test(ListCustomers::class)
            ->assertActionVisible('import');
    }

    public function test_admin_melihat_import_transaksi(): void
    {
        $this->actingAsRole('admin', 'admin');

        Livewire::test(ListTransactions::class)
            ->assertActionVisible('import');
    }

    public function test_teller_tidak_melihat_import_nasabah(): void
    {
        $this->actingAsRole('teller', 'teller');

        Livewire::test(ListCustomers::class)
            ->assertActionHidden('import');
    }

    public function test_teller_tidak_melihat_import_transaksi(): void
    {
        $this->actingAsRole('teller', 'teller');

        Livewire::test(ListTransactions::class)
            ->assertActionHidden('import');
    }
}