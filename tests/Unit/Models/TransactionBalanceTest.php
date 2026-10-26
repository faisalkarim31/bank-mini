<?php

namespace Tests\Unit\Models;

use App\Enums\TransactionType;
use Tests\Support\BankTestCase;

class TransactionBalanceTest extends BankTestCase
{
    public function test_balance_if_changed_ubah_nominal_setor(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $trx = $this->makeTransaction($customer, TransactionType::SetorTunai, 10000); // saldo 10000

        $this->assertSame(25000, $trx->balanceIfChangedTo(TransactionType::SetorTunai, 25000));
    }

    public function test_balance_if_changed_ubah_setor_ke_tarik(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();
        $trx = $this->makeTransaction($customer, TransactionType::SetorTunai, 10000); // saldo 10000

        $this->assertSame(-5000, $trx->balanceIfChangedTo(TransactionType::TarikTunai, 5000));
    }

    public function test_saldo_otomatis_berubah_saat_transaksi_dibuat(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer(['balance' => 50000]);

        $this->makeTransaction($customer, TransactionType::SetorTunai, 10000);
        $this->makeTransaction($customer, TransactionType::TarikKas, 4000);

        $this->assertSame(56000.0, $this->balanceOf($customer));
    }

    public function test_kode_transaksi_naik_per_hari(): void
    {
        $this->actingAsRole('admin');
        $customer = $this->makeCustomer();

        $a = $this->makeTransaction($customer);
        $b = $this->makeTransaction($customer);

        $prefix = 'TRX' . now()->format('ymd');
        $this->assertSame($prefix . '001', $a->transaction_code);
        $this->assertSame($prefix . '002', $b->transaction_code);
    }
}