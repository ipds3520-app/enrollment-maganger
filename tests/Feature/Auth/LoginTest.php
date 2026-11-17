<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    public function test_halaman_login_dapat_ditampilkan(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk')
            ->assertSee('Email')
            ->assertSee('Password')
            ->assertSee('Ingat saya');
    }

    public function test_admin_dapat_login_dan_diarahkan_ke_dashboard_admin(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_operator_dapat_login_dan_diarahkan_ke_dashboard_operator(): void
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create(['password' => 'rahasia123']);

        $response = $this->post('/login', [
            'email' => $operator->email,
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('operator.dashboard'));
        $this->assertAuthenticatedAs($operator);
    }

    public function test_password_salah_menghasilkan_pesan_generik(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $response = $this->from('/login')->post('/login', [
            'email' => $admin->email,
            'password' => 'password-salah',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();
    }

    public function test_email_tidak_terdaftar_menghasilkan_pesan_yang_sama(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'tidak-terdaftar@example.test',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();
    }

    public function test_email_dan_password_wajib_diisi(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Email wajib diisi.',
            'password' => 'Password wajib diisi.',
        ]);
        $this->assertGuest();
    }

    public function test_format_email_tidak_valid_ditolak(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'bukan-email',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
        $this->assertGuest();
    }

    public function test_remember_me_membuat_cookie_recaller(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'rahasia123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($admin);
    }

    public function test_tanpa_remember_me_tidak_membuat_cookie_recaller(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_percobaan_ketujuh_terkena_rate_limit(): void
    {
        $payload = [
            'email' => 'percobaan@example.test',
            'password' => 'password-salah',
        ];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/login', $payload);
        }

        $response = $this->post('/login', $payload);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 1 menit.',
        ]);
        $this->assertGuest();
    }

    public function test_session_diregenerasi_setelah_login_berhasil(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $this->get('/login');
        $sessionIdBefore = $this->app['session']->getId();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'rahasia123',
        ]);

        $this->assertNotSame($sessionIdBefore, $this->app['session']->getId());
    }

    public function test_pengguna_yang_sudah_login_tidak_dapat_membuka_halaman_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/login')
            ->assertRedirect('/');
    }

    public function test_operator_unverified_dapat_login_dan_mengakses_dashboard(): void
    {
        $operator = User::factory()->operator()->unverified()->create(['password' => 'rahasia123']);

        // Login tetap diperbolehkan sesuai keputusan bisnis.
        $response = $this->post('/login', [
            'email' => $operator->email,
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect(route('operator.dashboard'));
        $this->assertAuthenticatedAs($operator);

        $this->actingAs($operator)
            ->get('/operator/dashboard')
            ->assertOk();
    }

    public function test_admin_unverified_dapat_login_dan_mengakses_dashboard(): void
    {
        $admin = User::factory()->admin()->unverified()->create(['password' => 'rahasia123']);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'rahasia123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin/dashboard')->assertOk();
        $this->assertAuthenticatedAs($admin);
    }
}
