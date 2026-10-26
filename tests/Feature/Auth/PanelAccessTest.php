<?php

namespace Tests\Feature\Auth;

use Tests\Support\BankTestCase;

class PanelAccessTest extends BankTestCase
{
    public function test_guest_diarahkan_ke_login(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->get('/teller')->assertRedirect();
    }

    public function test_admin_bisa_buka_panel_admin(): void
    {
        $this->actingAsRole('admin');

        $this->get('/admin/customers')->assertOk();
        $this->get('/admin/transactions')->assertOk();
        $this->get('/admin/audit-logs')->assertOk();

    }
    public function test_halaman_pegawai_sengaja_ditutup_untuk_semua(): void
    {
        // UserResource::canAccess() mengembalikan false, jadi fitur Pegawai dinonaktifkan.
        $this->actingAsRole('admin');

        $this->get('/admin/users')->assertForbidden();
    }

    public function test_teller_bisa_buka_panel_teller(): void
    {
        $this->actingAsRole('teller');

        $this->get('/teller/customers')->assertOk();
        $this->get('/teller/transactions')->assertOk();
    }

    public function test_teller_tidak_bisa_buka_panel_admin(): void
    {
        $this->actingAsRole('teller');

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/audit-logs')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_admin_tidak_bisa_buka_panel_teller(): void
    {
        $this->actingAsRole('admin');

        $this->get('/teller')->assertForbidden();
    }

    public function test_user_nonaktif_ditolak(): void
    {
        $this->actingAsRole('admin', 'admin', ['is_active' => false]);

        $this->get('/admin/customers')->assertForbidden();
    }
}