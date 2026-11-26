<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public function test_login_dan_logout_admin_tidak_menghapus_autentikasi_intern(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'rahasia123']);
        $intern = $this->createIntern();

        $this->post(route('intern.login.store'), ['email' => $intern->email, 'password' => 'intern-password'])
            ->assertRedirect(route('intern.dashboard'));

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($intern, 'intern');

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($intern, 'intern');
        $this->get(route('intern.profile.show'))->assertOk();
    }

    public function test_login_dan_logout_operator_tidak_menghapus_autentikasi_intern(): void
    {
        $operator = User::factory()->operator()->create(['password' => 'rahasia123']);
        $intern = $this->createIntern();

        $this->post(route('intern.login.store'), ['email' => $intern->email, 'password' => 'intern-password'])
            ->assertRedirect(route('intern.dashboard'));

        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('operator.dashboard'));

        $this->assertAuthenticatedAs($operator, 'operator');
        $this->assertAuthenticatedAs($intern, 'intern');

        $this->post(route('operator.logout'))
            ->assertRedirect(route('operator.login'));

        $this->assertGuest('operator');
        $this->assertAuthenticatedAs($intern, 'intern');
        $this->get(route('intern.profile.show'))->assertOk();
    }

    private function createIntern(): UserIntern
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::query()->create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan Uji',
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Peserta Uji',
            'email' => 'peserta.uji@example.test',
        ]);

        return UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => null,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'password' => Hash::make('intern-password'),
        ]);
    }
}
