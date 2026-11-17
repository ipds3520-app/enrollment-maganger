<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    public function test_logout_mengakhiri_autentikasi_dan_menginvalidasi_session(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->withSession(['kunci_rahasia' => 'nilai'])
            ->post('/logout');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Anda telah berhasil keluar.');
        $response->assertSessionMissing('kunci_rahasia');

        $this->assertGuest();
    }

    public function test_csrf_token_diregenerasi_setelah_logout(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $tokenBefore = $this->app['session']->token();

        $this->post('/logout');

        $this->assertNotSame($tokenBefore, $this->app['session']->token());
    }

    public function test_get_logout_tidak_diizinkan(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/logout')
            ->assertStatus(405);
    }

    public function test_tamu_tidak_dapat_melakukan_logout(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_setelah_logout_dashboard_kembali_mengarah_ke_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/logout');

        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }
}
