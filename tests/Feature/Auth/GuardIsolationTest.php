<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class GuardIsolationTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_admin_lalu_operator_login_dalam_browser_session_yang_sama(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password-123']);
        $operator = User::factory()->operator()->create(['password' => 'password-123']);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password-123'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertCookie(config('session.cookie'));
        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'password-123'])
            ->assertRedirect(route('operator.dashboard'))
            ->assertCookie(config('session.cookie'));

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('operator.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');
    }

    public function test_operator_lalu_admin_login_tidak_menghapus_sesi_operator(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password-123']);
        $operator = User::factory()->operator()->create(['password' => 'password-123']);

        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'password-123'])
            ->assertRedirect(route('operator.dashboard'));
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password-123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('operator.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');
    }

    public function test_intern_login_tidak_mengubah_session_admin_dan_operator(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password-123']);
        $operator = User::factory()->operator()->create(['password' => 'password-123']);
        $intern = $this->createIntern('intern-login@example.test');

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password-123']);
        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'password-123']);
        $this->post(route('intern.login.store'), ['email' => $intern->email, 'password' => 'intern-password']);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('operator.dashboard'))->assertOk();
        $this->get(route('intern.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');
        $this->assertAuthenticatedAs($intern, 'intern');
    }

    public function test_intern_tidak_dapat_mengakses_dashboard_admin_atau_operator(): void
    {
        $intern = $this->createIntern('intern-role@example.test');
        $this->actingAs($intern, 'intern');

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('operator.dashboard'))->assertForbidden();
        $this->get(route('intern.dashboard'))->assertOk();
    }

    private function createIntern(string $email): UserIntern
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan uji guard',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Intern Guard',
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
