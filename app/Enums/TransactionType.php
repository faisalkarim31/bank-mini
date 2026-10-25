<?php

namespace App\Enums;

enum TransactionType: string
{
    case SetorTunai = 'setor_tunai';
    case TarikTunai = 'tarik_tunai';
    case TarikKas   = 'tarik_kas';

    public function label(): string
    {
        return match ($this) {
            self::SetorTunai => 'Kredit',
            self::TarikTunai => 'Debit',
            self::TarikKas   => 'Debit Kas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SetorTunai => 'success',
            self::TarikTunai => 'warning',
            self::TarikKas   => 'danger',
        };
    }

    public function isCredit(): bool
    {
        return $this === self::SetorTunai;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
