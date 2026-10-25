<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Admin Utama
        User::create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'full_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Akun Teller
        User::create([
            'username' => 'teller',
            'password' => Hash::make('teller123'),
            'full_name' => 'Teller',
            'role' => 'teller',
            'is_active' => true,
        ]);
    }
}
