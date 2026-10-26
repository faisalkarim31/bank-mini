<?php

namespace Tests\Support;

use App\Enums\CustomerCategory;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class BankTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** Login sebagai role tertentu dan set panel Filament aktif. */
    protected function actingAsRole(string $role, ?string $panel = null, array $attrs = []): User
    {
        $user = User::factory()->create(['role' => $role] + $attrs);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel($panel ?? $role));

        return $user;
    }

    /**
     * Model Customer memaksa balance=0, is_active=true, created_by=Auth::id() saat creating.
     * Jadi saldo/status diset lewat update setelah create.
     */
    protected function makeCustomer(array $attrs = []): Customer
    {
        if (auth()->guest()) {
            $this->actingAs(User::factory()->create(['role' => 'admin']));
        }

        $balance = $attrs['balance'] ?? 0;
        $active = $attrs['is_active'] ?? true;
        unset($attrs['balance'], $attrs['is_active']);

        $customer = Customer::create($attrs + [
            'full_name' => 'Budi Santoso',
            'category' => CustomerCategory::Siswa,
        ]);

        $customer->forceFill(['balance' => $balance, 'is_active' => $active])->save();

        return $customer->refresh();
    }

    /** Hook created otomatis ubah saldo nasabah. */
    protected function makeTransaction(
        Customer $customer,
        TransactionType $type = TransactionType::SetorTunai,
        int $amount = 10000,
        ?Carbon $at = null,
    ): Transaction {
        $trx = new Transaction([
            'customer_id' => $customer->id,
            'type' => $type,
            'amount' => $amount,
            'processed_by' => 'Tester',
        ]);

        if ($at) {
            $trx->created_at = $at;
            $trx->updated_at = $at;
        }

        $trx->save();

        return $trx->refresh();
    }

    protected function balanceOf(Customer $customer): float
    {
        return (float) $customer->fresh()->balance;
    }
}