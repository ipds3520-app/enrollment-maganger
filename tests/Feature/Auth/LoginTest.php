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

    public function test_login_selector_dan_halaman_login_per_role_dapat_ditampilkan(): void
    {
        $selector = $this->get('/login')->assertOk()
            ->assertSee('Operator')
            ->assertSee('Peserta Magang');
        $selector->assertDontSee(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('Masuk sebagai Admin');
        $this->get(route('operator.login'))->assertOk()->assertSee('Masuk sebagai Operator');
        $this->get(route('intern.login'))->assertOk()->assertSee('Masuk sebagai Peserta Magang');
    }

    public function test_admin_login_menggunakan_guard_admin_dan_role_admin(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('operator');
    }

    public function test_operator_login_menggunakan_guard_operator_dan_role_operator(): void
    {
        $operator = User::factory()->operator(Institution::factory()->create())->create(['password' => 'rahasia123']);

        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('operator.dashboard'));

        $this->assertAuthenticatedAs($operator, 'operator');
        $this->assertGuest('admin');
    }

    public function test_login_role_salah_ditolak_tanpa_membuat_session_guard(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);
        $operator = User::factory()->operator()->create(['password' => 'rahasia123']);

        $this->from(route('operator.login'))
            ->post(route('operator.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('operator');

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $operator->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_operator_login_page_dapat_dibuka_saat_admin_sudah_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('operator.login'))
            ->assertOk()
            ->assertSee('Masuk sebagai Operator');
    }

    public function test_login_menggunakan_remember_cookie_khusus_guard(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);
        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'rahasia123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertCookie(Auth::guard('admin')->getRecallerName());
        $response->assertCookieMissing(Auth::guard('operator')->getRecallerName());
    }

    public function test_login_rate_limit_dibatasi_per_jenis_akun(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('admin.login.store'), ['email' => 'salah@example.test', 'password' => 'salah']);
        }

        $this->post(route('admin.login.store'), ['email' => 'salah@example.test', 'password' => 'salah'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $operator = User::factory()->operator()->create(['password' => 'rahasia123']);
        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('operator.dashboard'));
    }

    public function test_login_tidak_memverifikasi_email_admin_atau_operator(): void
    {
        $admin = User::factory()->admin()->unverified()->create(['password' => 'rahasia123']);
        $operator = User::factory()->operator()->unverified()->create(['password' => 'rahasia123']);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('operator.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');
    }
}
