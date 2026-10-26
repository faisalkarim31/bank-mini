<?php

namespace Tests\Unit\Enums;

use App\Enums\CustomerCategory;
use PHPUnit\Framework\TestCase;

class CustomerCategoryTest extends TestCase
{
    public function test_kode_kategori(): void
    {
        $this->assertSame('01', CustomerCategory::Siswa->code());
        $this->assertSame('02', CustomerCategory::Guru->code());
        $this->assertSame('03', CustomerCategory::Staff->code());
        $this->assertSame('04', CustomerCategory::Lainnya->code());
    }

    public function test_kode_kategori_unik(): void
    {
        $codes = array_map(fn (CustomerCategory $c) => $c->code(), CustomerCategory::cases());

        $this->assertSame($codes, array_unique($codes));
    }

    public function test_options_berisi_semua_kategori(): void
    {
        $this->assertSame(
            ['siswa', 'guru', 'staff', 'lainnya'],
            array_keys(CustomerCategory::options()),
        );
    }
}