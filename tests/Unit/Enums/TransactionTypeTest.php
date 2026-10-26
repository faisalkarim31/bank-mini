<?php

namespace Tests\Unit\Enums;

use App\Enums\TransactionType;
use PHPUnit\Framework\TestCase;

class TransactionTypeTest extends TestCase
{
    public function test_hanya_setor_tunai_yang_kredit(): void
    {
        $this->assertTrue(TransactionType::SetorTunai->isCredit());
        $this->assertFalse(TransactionType::TarikTunai->isCredit());
        $this->assertFalse(TransactionType::TarikKas->isCredit());
    }

    public function test_options_berisi_semua_jenis(): void
    {
        $this->assertSame(
            ['setor_tunai', 'tarik_tunai', 'tarik_kas'],
            array_keys(TransactionType::options()),
        );
    }
}