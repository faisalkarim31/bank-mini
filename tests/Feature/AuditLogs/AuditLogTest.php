<?php

namespace Tests\Feature\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\BankTestCase;

class AuditLogTest extends BankTestCase
{
    private function log(User $user, string $action, ?string $target = null, $at = null): AuditLog
    {
        $log = AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'target_name' => $target,
        ]);

        if ($at) {
            $log->created_at = $at;
            $log->save();
        }

        return $log;
    }

    public function test_admin_lihat_dan_search_riwayat(): void
    {
        $admin = $this->actingAsRole('admin');
        $a = $this->log($admin, 'Tambah Nasabah', 'Budi');
        $b = $this->log($admin, 'Edit Transaksi', 'TRX001');

        Livewire::test(ManageAuditLogs::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->searchTable('Edit Transaksi')
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a]);
    }

    public function test_search_berdasarkan_nama_pengguna(): void
    {
        $admin = $this->actingAsRole('admin', 'admin', ['full_name' => 'Pak Admin']);
        $teller = User::factory()->create(['role' => 'teller', 'full_name' => 'Bu Teller']);
        $a = $this->log($admin, 'Login');
        $t = $this->log($teller, 'Login');

        Livewire::test(ManageAuditLogs::class)
            ->searchTable('Bu Teller')
            ->assertCanSeeTableRecords([$t])
            ->assertCanNotSeeTableRecords([$a]);
    }

    public function test_filter_peran(): void
    {
        $admin = $this->actingAsRole('admin');
        $teller = User::factory()->create(['role' => 'teller']);
        $a = $this->log($admin, 'Login');
        $t = $this->log($teller, 'Login');

        Livewire::test(ManageAuditLogs::class)
            ->set('roleFilter', 'teller')
            ->assertCanSeeTableRecords([$t])
            ->assertCanNotSeeTableRecords([$a])
            ->set('roleFilter', 'admin')
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$t]);
    }

    public function test_filter_tanggal(): void
    {
        $admin = $this->actingAsRole('admin');
        $lama = $this->log($admin, 'Login', null, now()->subDays(10));
        $baru = $this->log($admin, 'Login');

        Livewire::test(ManageAuditLogs::class)
            ->set('dateFrom', now()->subDays(2)->toDateString())
            ->assertCanSeeTableRecords([$baru])
            ->assertCanNotSeeTableRecords([$lama]);
    }
}