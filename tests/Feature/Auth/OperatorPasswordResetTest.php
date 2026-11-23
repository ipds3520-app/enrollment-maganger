<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class OperatorPasswordResetTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_operator_dengan_email_valid_melanjutkan_ke_form_password_tanpa_email_atau_token_broker(): void
    {
        Notification::fake();
        $operator = $this->operator();

        $this->get(route('operator.password.request'))
            ->assertOk()
            ->assertSee('Lupa Password Operator');

        $this->post(route('operator.password.email'), ['email' => $operator->email])
            ->assertRedirect(route('operator.password.reset'));

        $state = session()->get('operator_password_reset');
        $this->assertSame($operator->id, $state['user_id']);
        $this->assertIsInt($state['started_at']);
        $this->assertArrayNotHasKey('email', $state);

        $this->get(route('operator.password.reset'))
            ->assertOk()
            ->assertSee('Buat Password Baru')
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="user_id"', false);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_email_tidak_dikenal_admin_dan_intern_tidak_dapat_melanjutkan_reset_operator(): void
    {
        $admin = User::factory()->admin()->create();
        $intern = $this->intern();

        foreach (['unknown@example.test', $admin->email, $intern->email] as $email) {
            $this->post(route('operator.password.email'), ['email' => $email])
                ->assertRedirect(route('operator.password.request'))
                ->assertSessionHasErrors('email');

            $this->assertNull(session()->get('operator_password_reset'));
            $this->get(route('operator.password.reset'))
                ->assertRedirect(route('operator.password.request'));
        }

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_email_operator_dinormalisasi_dan_reset_password_berhasil(): void
    {
        $operator = $this->operator([
            'email' => 'operator-reset@example.test',
            'password' => 'password-lama-123',
            'institution_id' => Institution::factory()->create()->id,
        ]);
        $email = $operator->email;
        $institutionId = $operator->institution_id;
        $role = $operator->role;
        $rememberToken = $operator->remember_token;

        $this->post(route('operator.password.email'), ['email' => '  OPERATOR-RESET@EXAMPLE.TEST  '])
            ->assertRedirect(route('operator.password.reset'));

        $this->post(route('operator.password.update'), [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect(route('operator.login'))
            ->assertSessionHas('status');

        $operator->refresh();
        $this->assertTrue(Hash::check('password-baru-123', $operator->password));
        $this->assertFalse(Hash::check('password-lama-123', $operator->password));
        $this->assertNotSame($rememberToken, $operator->remember_token);
        $this->assertSame($role, $operator->role);
        $this->assertSame($email, $operator->email);
        $this->assertSame($institutionId, $operator->institution_id);
        $this->assertNull(session()->get('operator_password_reset'));

        $this->post(route('operator.login.store'), ['email' => $email, 'password' => 'password-lama-123'])
            ->assertSessionHasErrors('email');
        $this->post(route('operator.login.store'), ['email' => $email, 'password' => 'password-baru-123'])
            ->assertRedirect(route('operator.dashboard'));
        $this->assertAuthenticatedAs($operator->fresh(), 'operator');
    }

    public function test_konfirmasi_password_wajib_cocok(): void
    {
        $operator = $this->operator();
        $this->post(route('operator.password.email'), ['email' => $operator->email]);

        $this->post(route('operator.password.update'), [
            'password' => 'password-baru-123',
            'password_confirmation' => 'berbeda-123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password-lama-123', $operator->fresh()->password));
    }

    public function test_reset_tanpa_session_ditolak_dan_password_tetap(): void
    {
        $operator = $this->operator();

        $this->post(route('operator.password.update'), [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect(route('operator.password.request'))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password-lama-123', $operator->fresh()->password));
    }

    public function test_state_reset_kedaluwarsa_dihapus_dan_tidak_dapat_digunakan(): void
    {
        $operator = $this->operator();
        $this->post(route('operator.password.email'), ['email' => $operator->email]);
        $this->travel(16)->minutes();

        $this->get(route('operator.password.reset'))
            ->assertRedirect(route('operator.password.request'))
            ->assertSessionHasErrors('email');

        $this->assertNull(session()->get('operator_password_reset'));
        $this->post(route('operator.password.update'), [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect(route('operator.password.request'));
        $this->assertTrue(Hash::check('password-lama-123', $operator->fresh()->password));
    }

    public function test_request_tidak_dapat_mengganti_email_atau_id_operator_target(): void
    {
        $target = $this->operator(['email' => 'target@example.test']);
        $other = $this->operator(['email' => 'other@example.test']);
        $this->post(route('operator.password.email'), ['email' => $target->email]);

        $this->post(route('operator.password.update'), [
            'email' => $other->email,
            'user_id' => $other->id,
            'operator_id' => $other->id,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHasErrors(['email', 'user_id', 'operator_id']);

        $this->assertTrue(Hash::check('password-lama-123', $target->fresh()->password));
        $this->assertTrue(Hash::check('password-lama-123', $other->fresh()->password));
    }

    public function test_reset_operator_tidak_mengubah_session_admin_dan_intern(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = $this->operator();
        $intern = $this->intern();

        $this->actingAs($admin, 'admin')->actingAs($intern, 'intern');
        $this->post(route('operator.password.email'), ['email' => $operator->email])
            ->assertRedirect(route('operator.password.reset'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($intern, 'intern');

        $this->post(route('operator.password.update'), [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect(route('operator.login'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($intern, 'intern');
        $this->assertTrue(Hash::check('password-baru-123', $operator->fresh()->password));
    }

    private function operator(array $overrides = []): User
    {
        $institutionId = $overrides['institution_id'] ?? Institution::factory()->create()->id;
        unset($overrides['institution_id']);

        return User::factory()->operator(Institution::query()->findOrFail($institutionId))->create(array_replace([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password-lama-123',
        ], $overrides));
    }

    private function intern(): UserIntern
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan akun reset',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Intern Reset',
            'email' => 'intern-reset@example.test',
        ]);
        $registrationToken = RegistrationToken::create([
            'applicant_id' => $applicant->id,
            'token_hash' => hash('sha256', 'intern-reset-token'),
            'status' => 'used',
            'expires_at' => now()->addDays(30),
            'used_at' => now(),
        ]);

        return UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => $registrationToken->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'password' => 'intern-password-123',
        ]);
    }
}
