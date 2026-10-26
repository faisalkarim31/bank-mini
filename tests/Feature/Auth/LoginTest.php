<?php

namespace Tests\Feature\Auth;

use App\Filament\Auth\Login;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class LoginTest extends BankTestCase
{
    public function test_login_berhasil_dan_tercatat_di_audit_log(): void
    {
        $user = User::factory()->create(['username' => 'kasir1', 'role' => 'teller']);
        Filament::setCurrentPanel(Filament::getPanel('auth'));

        Livewire::test(Login::class)
            ->fillForm(['username' => 'kasir1', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'Login']);
    }

    public function test_login_password_salah_gagal(): void
    {
        User::factory()->create(['username' => 'kasir1']);
        Filament::setCurrentPanel(Filament::getPanel('auth'));

        Livewire::test(Login::class)
            ->fillForm(['username' => 'kasir1', 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasErrors(['data.username']);

        $this->assertGuest();
        $this->assertSame(0, AuditLog::count());
    }

    public function test_login_user_nonaktif_gagal(): void
    {
        User::factory()->create(['username' => 'mantan', 'is_active' => false]);
        Filament::setCurrentPanel(Filament::getPanel('auth'));

        Livewire::test(Login::class)
            ->fillForm(['username' => 'mantan', 'password' => 'password'])
            ->call('authenticate');

        $this->assertGuest();
    }
}