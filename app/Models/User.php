<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'employee_id',
        'username',
        'password',
        'full_name',
        'role',
        'is_active',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'  => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (blank($user->employee_id)) {
                $prefix = 'P' . now()->format('y');
                $last   = static::where('employee_id', 'like', $prefix . '%')->max('employee_id');
                $next   = $last ? ((int) substr($last, 3)) + 1 : 1;

                $user->employee_id = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
            }

            if (blank($user->created_by) && Auth::check()) {
                $user->created_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'auth'   => true,
            'admin'  => $this->role === 'admin',
            'teller' => $this->role === 'teller',
            default  => false,
        };
    }
}
