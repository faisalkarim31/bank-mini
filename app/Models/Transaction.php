<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    protected $fillable = [
        'customer_id',
        'transaction_code',
        'type',
        'amount',
        'processed_by',
    ];

    protected $casts = [
        'type' => TransactionType::class,
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Saldo nasabah seandainya transaksi ini diubah menjadi jenis & nominal baru.
     * Dipakai untuk memeriksa apakah hasil edit membuat saldo menjadi minus.
     */
    public function balanceIfChangedTo(TransactionType $newType, int $newAmount): int
    {
        $balance = (int) $this->customer->balance;

        // Batalkan efek transaksi lama, lalu terapkan efek yang baru
        $balance -= $this->type->isCredit() ? (int) $this->amount : -(int) $this->amount;
        $balance += $newType->isCredit() ? $newAmount : -$newAmount;

        return $balance;
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (empty($transaction->transaction_code)) {
                $prefix = 'TRX' . ($transaction->created_at ?? now())->format('ymd');

                $lastNumber = static::where('transaction_code', 'like', $prefix . '%')
                    ->orderByDesc('transaction_code')
                    ->value('transaction_code');

                $sequence = $lastNumber
                    ? ((int) substr($lastNumber, strlen($prefix)) + 1)
                    : 1;

                // str_pad otomatis "meluas" ke 4 digit sendiri kalau sequence > 999
                $transaction->transaction_code = $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
            }
        });

        static::created(function (Transaction $transaction) {
            $customer = $transaction->customer;

            if ($transaction->type->isCredit()) {
                $customer->increment('balance', $transaction->amount);
            } else {
                $customer->decrement('balance', $transaction->amount);
            }
        });

        // Saat jenis/nominal/nasabah berubah, saldo ikut disesuaikan
        static::updated(function (Transaction $transaction) {
            if (! $transaction->wasChanged(['customer_id', 'type', 'amount'])) {
                return;
            }

            $oldType = $transaction->getOriginal('type');
            $oldType = $oldType instanceof TransactionType ? $oldType : TransactionType::from($oldType);
            $oldAmount = (int) $transaction->getOriginal('amount');

            DB::transaction(function () use ($transaction, $oldType, $oldAmount) {
                // Batalkan efek transaksi lama pada nasabah lama
                Customer::whereKey($transaction->getOriginal('customer_id'))
                    ->increment('balance', $oldType->isCredit() ? -$oldAmount : $oldAmount);

                // Terapkan efek transaksi baru pada nasabah (baru)
                $newAmount = (int) $transaction->amount;

                Customer::whereKey($transaction->customer_id)
                    ->increment('balance', $transaction->type->isCredit() ? $newAmount : -$newAmount);
            });
        });
    }
}
