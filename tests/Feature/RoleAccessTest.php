<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\User;
use App\Models\UserIntern;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    public function test_admin_dapat_mengakses_dashboard_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Admin')
            ->assertSee($admin->name)
            ->assertSee($admin->email)
            ->assertSee('Admin');
    }

    public function test_operator_dapat_mengakses_dashboard_operator(): void
    {
        $institution = Institution::factory()->create(['name' => 'SMK Negeri Uji']);
        $operator = User::factory()->operator($institution)->create();

        $this->actingAs($operator)
            ->get('/operator/dashboard')
            ->assertOk()
            ->assertSee('Dashboard Operator')
            ->assertSee($operator->name)
            ->assertSee($operator->email)
            ->assertSee('SMK Negeri Uji');
    }

    public function test_operator_tidak_dapat_mengakses_dashboard_admin(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)
            ->get('/admin/dashboard')
            ->assertForbidden()
            ->assertSee('Akses Ditolak');
    }

    public function test_admin_tidak_dapat_mengakses_dashboard_operator(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/operator/dashboard')
            ->assertForbidden()
            ->assertSee('Akses Ditolak');
    }

    public function test_pengguna_belum_login_tidak_dapat_membuka_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
        $this->get('/operator/dashboard')->assertRedirect(route('operator.login'));
        $this->get('/intern/dashboard')->assertRedirect(route('intern.login'));
    }

    public function test_role_tidak_valid_ditolak(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['role' => 'invalid'])->save();

        $this->actingAs($user, 'admin')->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($user, 'admin')->get('/operator/dashboard')->assertForbidden();
        $this->actingAs($user, 'admin')->get('/')->assertForbidden();
    }

    public function test_root_menampilkan_landing_page_ketika_admin_login(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertViewIs('welcome');
    }

    public function test_root_menampilkan_landing_page_ketika_operator_login(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)
            ->get('/')
            ->assertOk()
            ->assertViewIs('welcome');
    }

    public function test_root_menampilkan_landing_page_untuk_tamu(): void
    {
        $this->get('/')->assertOk()->assertViewIs('welcome');
    }

    public function test_root_menampilkan_landing_page_ketika_intern_login(): void
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
        $intern = UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => null,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'password' => 'password',
        ]);

        $this->actingAs($intern, 'intern')
            ->get('/')
            ->assertOk()
            ->assertViewIs('welcome');
    }

    public function test_root_menampilkan_landing_page_jika_beberapa_guard_aktif(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();

        $this->actingAs($admin, 'admin')->actingAs($operator, 'operator')
            ->get('/')
            ->assertOk()
            ->assertViewIs('welcome');
    }

    public function test_role_tidak_dapat_diubah_melalui_mass_assignment(): void
    {
        $institution = Institution::factory()->create();

        $user = User::create([
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'rahasia123',
            'role' => 'admin', // harus diabaikan karena role tidak fillable
            'institution_id' => $institution->id,
        ]);

        $this->assertSame('operator', $user->fresh()->role);
        $this->assertFalse($user->isFillable('role'));
    }
}
