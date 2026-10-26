<?php

namespace Tests\Feature\AboutBank;

use App\Filament\Admin\Pages\AboutBank;
use Tests\Support\BankTestCase;

class AboutBankTest extends BankTestCase
{
    public function test_peraturan_tentang_bank_tidak_berisi_teks_placeholder(): void
    {
        $peraturan = (new AboutBank())->getPeraturan();

        foreach ($peraturan as $item) {
            $this->assertStringNotContainsString('Isi peraturan', $item['teks']);
        }
    }
}