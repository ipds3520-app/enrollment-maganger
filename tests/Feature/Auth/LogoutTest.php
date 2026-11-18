<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
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

    public function test_logout_admin_hanya_menghapus_guard_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $this->actingAs($admin, 'admin')->actingAs($operator, 'operator');

        $response = $this->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'))->assertSessionHas('status');
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($operator, 'operator');
    }

    public function test_logout_operator_hanya_menghapus_guard_operator(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $this->actingAs($admin, 'admin')->actingAs($operator, 'operator');

        $this->post(route('operator.logout'))->assertRedirect(route('operator.login'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('operator');
    }

    public function test_logout_intern_tidak_mengeluarkan_admin_dan_operator(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password-123']);
        $operator = User::factory()->operator()->create(['password' => 'password-123']);
        $intern = $this->createIntern('intern-logout@example.test');

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password-123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'password-123'])
            ->assertRedirect(route('operator.dashboard'));
        $this->post(route('intern.login.store'), ['email' => $intern->email, 'password' => 'intern-password'])
            ->assertRedirect(route('intern.dashboard'));

        $this->post(route('intern.logout'))->assertRedirect(route('intern.login'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');
        $this->assertGuest('intern');
    }

    public function test_logout_hanya_mengizinkan_post_dan_membutuhkan_guard_terkait(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin')->get(route('admin.logout'))->assertStatus(405);
        $this->post(route('operator.logout'))->assertRedirect(route('operator.login'));
    }

    public function test_csrf_token_diregenerasi_setelah_logout_guard(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create();
        $this->actingAs($admin, 'admin')->actingAs($operator, 'operator');
        $tokenBefore = $this->app['session']->token();

        $this->post(route('admin.logout'));

        $this->assertNotSame($tokenBefore, $this->app['session']->token());
        $this->assertAuthenticatedAs($operator, 'operator');
    }

    public function test_guest_diarahkan_ke_login_area_saat_membuka_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('operator.dashboard'))->assertRedirect(route('operator.login'));
        $this->get(route('intern.dashboard'))->assertRedirect(route('intern.login'));
    }

    private function createIntern(string $email): UserIntern
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan uji logout intern',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Intern Logout',
            'email' => $email,
        ]);
        $token = RegistrationToken::create([
            'applicant_id' => $applicant->id,
            'token_hash' => hash('sha256', random_bytes(32)),
            'status' => 'used',
            'expires_at' => now()->addDays(30),
            'used_at' => now(),
        ]);

        return UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => $token->id,
            'name' => $applicant->name,
            'email' => $email,
            'password' => 'intern-password',
        ]);
    }
}
