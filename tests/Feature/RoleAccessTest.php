<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
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
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/operator/dashboard')->assertRedirect(route('login'));
    }

    public function test_role_tidak_valid_ditolak(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['role' => 'invalid'])->save();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/operator/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/')->assertForbidden();
    }

    public function test_root_mengarahkan_admin_ke_dashboard_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_root_mengarahkan_operator_ke_dashboard_operator(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)
            ->get('/')
            ->assertRedirect(route('operator.dashboard'));
    }

    public function test_root_mengarahkan_tamu_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
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
