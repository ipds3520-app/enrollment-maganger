<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternRegistrationTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{InternshipApplicant, RegistrationToken, string} */
    private function applicantWithToken(string $status = 'active', ?Carbon $expiresAt = null): array
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan magang',
            'description' => null,
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Nama Peserta',
            'email' => 'peserta@example.test',
            'phone' => '081234567890',
            'student_number' => 'SISWA-001',
        ]);
        $plainToken = bin2hex(random_bytes(32));
        $token = $applicant->registrationTokens()->create([
            'token_hash' => hash('sha256', $plainToken),
            'status' => $status,
            'expires_at' => $expiresAt ?? now()->addDays(30),
            'used_at' => $status === 'used' ? now() : null,
        ]);

        return [$applicant, $token, $plainToken];
    }

    /** @param array<string, mixed> $extra */
    private function postRegistration(string $token, array $extra = []): TestResponse
    {
        return $this->post(route('intern.register.store'), array_merge([
            'token' => $token,
            'password' => 'A-strong-password-123',
            'password_confirmation' => 'A-strong-password-123',
        ], $extra));
    }

    public function test_token_aktif_mendaftarkan_akun_dan_menggunakan_token_tanpa_login(): void
    {
        [$applicant, $token, $plainToken] = $this->applicantWithToken();

        $response = $this->postRegistration($plainToken)
            ->assertRedirect(route('intern.register'))
            ->assertSessionHas('status');

        $account = UserIntern::query()->sole();
        $this->assertSame($applicant->id, $account->applicant_id);
        $this->assertSame($token->id, $account->registration_token_id);
        $this->assertSame($applicant->name, $account->name);
        $this->assertSame($applicant->email, $account->email);
        $this->assertSame($applicant->phone, $account->phone);
        $this->assertNull($account->email_verified_at);
        $this->assertTrue(Hash::check('A-strong-password-123', $account->password));
        $this->assertSame('used', $token->fresh()->status);
        $this->assertNotNull($token->fresh()->used_at);
        $this->assertGuest();
        $response->assertDontSee($plainToken);
        $this->assertStringNotContainsString($plainToken, $response->headers->get('Location', ''));
        $this->assertFalse(str_contains(json_encode(session()->all()), $plainToken));
        $this->assertDatabaseMissing('registration_tokens', ['token_hash' => $plainToken]);
    }

    public function test_token_tidak_ditemukan_dan_token_salah_ditolak_dengan_pesan_generik(): void
    {
        [, , $plainToken] = $this->applicantWithToken();

        $this->postRegistration(str_repeat('b', 64))->assertSessionHasErrors('token');
        $this->postRegistration(str_repeat('c', 64))->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users_intern', 0);
        $this->assertDatabaseCount('registration_tokens', 1);
        $this->assertNotSame($plainToken, str_repeat('c', 64));
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        [, , $plainToken] = $this->applicantWithToken('active', now()->subSecond());

        $this->postRegistration($plainToken)->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users_intern', 0);
    }

    public function test_token_dicabut_ditolak(): void
    {
        [, , $plainToken] = $this->applicantWithToken('revoked');

        $this->postRegistration($plainToken)->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users_intern', 0);
    }

    public function test_token_yang_sudah_digunakan_ditolak(): void
    {
        [, , $plainToken] = $this->applicantWithToken('used');

        $this->postRegistration($plainToken)->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users_intern', 0);
    }

    public function test_peserta_yang_sudah_mempunyai_akun_ditolak(): void
    {
        [$applicant, $token, $plainToken] = $this->applicantWithToken();
        UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => $token->id,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'password' => Hash::make('existing-password'),
        ]);

        $this->postRegistration($plainToken)->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users_intern', 1);
        $this->assertSame('active', $token->fresh()->status);
    }

    public function test_password_tidak_valid_ditolak_dan_token_tidak_disimpan_di_session(): void
    {
        [, , $plainToken] = $this->applicantWithToken();

        $this->postRegistration($plainToken, [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password')
            ->assertSessionHas('_old_input', fn (array $input): bool => ! array_key_exists('token', $input)
                && ! array_key_exists('password', $input)
                && ! array_key_exists('password_confirmation', $input)
            );

        $this->assertDatabaseCount('users_intern', 0);
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        [, , $plainToken] = $this->applicantWithToken();

        $this->postRegistration($plainToken, ['password_confirmation' => 'different-password'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users_intern', 0);
    }

    public function test_applicant_id_dari_browser_diabaikan_dan_identitas_diambil_dari_token(): void
    {
        [$applicant, , $plainToken] = $this->applicantWithToken();
        [$otherApplicant] = $this->applicantWithToken();

        $this->postRegistration($plainToken, ['applicant_id' => $otherApplicant->id])->assertSessionHas('status');

        $account = UserIntern::query()->sole();
        $this->assertSame($applicant->id, $account->applicant_id);
        $this->assertSame($applicant->email, $account->email);
    }

    public function test_email_duplikat_membatalkan_pembuatan_akun_dan_pemakaian_token(): void
    {
        [$existingApplicant, $existingToken] = $this->applicantWithToken();
        UserIntern::create([
            'applicant_id' => $existingApplicant->id,
            'registration_token_id' => $existingToken->id,
            'name' => $existingApplicant->name,
            'email' => $existingApplicant->email,
            'password' => Hash::make('existing-password'),
        ]);
        [$applicant, $token, $plainToken] = $this->applicantWithToken();

        $this->postRegistration($plainToken)->assertSessionHasErrors('token');

        $this->assertDatabaseCount('users_intern', 1);
        $this->assertSame('active', $token->fresh()->status);
        $this->assertNull($token->fresh()->used_at);
        $this->assertSame(0, UserIntern::query()->where('applicant_id', $applicant->id)->count());
    }

    public function test_token_tidak_dimasukkan_ke_old_input_atau_url_setelah_kegagalan_token(): void
    {
        $plainToken = str_repeat('d', 64);

        $response = $this->postRegistration($plainToken)->assertSessionHasErrors('token');

        $response->assertDontSee($plainToken);
        $this->assertStringNotContainsString($plainToken, $response->headers->get('Location', ''));
        $response->assertSessionMissing('_old_input');
        $this->assertFalse(str_contains(json_encode(session()->all()), $plainToken));
    }

    public function test_endpoint_registrasi_menerapkan_throttle(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postRegistration(str_repeat('e', 64))->assertRedirect(route('intern.register'));
        }

        $this->postRegistration(str_repeat('f', 64))->assertTooManyRequests();
    }

    public function test_kegagalan_update_token_membatalkan_pembuatan_akun(): void
    {
        [$applicant, $token, $plainToken] = $this->applicantWithToken();
        RegistrationToken::updating(function (): void {
            throw new QueryException('sqlite', 'update registration_tokens', [], new \PDOException('simulated failure'));
        });

        $this->postRegistration($plainToken)->assertSessionHasErrors('token');

        $this->assertDatabaseCount('users_intern', 0);
        $this->assertSame('active', $token->fresh()->status);
        $this->assertNull($token->fresh()->used_at);
        $this->assertSame($applicant->id, $token->fresh()->applicant_id);
    }

    public function test_hal_registrasi_dapat_dibuka_tanpa_login(): void
    {
        $this->get(route('intern.register'))->assertOk()->assertViewIs('auth.intern-register');
    }
}
