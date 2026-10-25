<?php

namespace App\Enums;

enum CustomerCategory: string
{
    case Siswa   = 'siswa';
    case Guru    = 'guru';
    case Staff   = 'staff';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Siswa   => 'Siswa',
            self::Guru    => 'Guru',
            self::Staff   => 'Kelas',
            self::Lainnya => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Siswa   => 'success',
            self::Guru    => 'info',
            self::Staff   => 'warning',
            self::Lainnya => 'gray',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Siswa   => '01',
            self::Guru    => '02',
            self::Staff   => '03',
            self::Lainnya => '04',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
