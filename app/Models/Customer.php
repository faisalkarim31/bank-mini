<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Enums\CustomerCategory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_number',
        'full_name',
        'category',
        'balance',
        'is_active',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    protected $casts = [
        'category' => CustomerCategory::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->balance = 0;
            $customer->is_active = true;
            $customer->created_by = Auth::id();

            if (empty($customer->account_number)) {
                $categoryCode = $customer->category->code();
                $year = now()->format('y');
                $prefix = $categoryCode . $year;

                $lastNumber = static::where('account_number', 'like', $prefix . '%')
                    ->orderByDesc('account_number')
                    ->value('account_number');

                $sequence = $lastNumber
                    ? ((int) substr($lastNumber, 4) + 1)
                    : 1;

                $customer->account_number = $prefix . str_pad($sequence, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}
