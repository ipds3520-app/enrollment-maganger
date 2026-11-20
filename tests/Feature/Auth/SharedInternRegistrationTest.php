<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class SharedInternRegistrationTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
        Storage::fake('local');
    }

    /** @return array{InternshipRequest, User, string} */
    private function approvedRequest(int $quota = 2): array
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Program Magang Bersama',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
            'participant_count' => $quota,
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter' => UploadedFile::fake()->create('balasan.pdf', 1, 'application/pdf'),
        ]);

        $response->assertOk()->assertViewIs('admin.shared-registration-tokens.issued');
        $plaintext = $response->viewData('plaintextToken');
        $response->assertSee($plaintext);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertFalse(session()->has('plaintextToken'));

        return [$request->fresh(), $admin, $plaintext];
    }

    /** @return array<string, string> */
    private function profilePayload(array $overrides = []): array
    {
        return array_replace([
            'nik' => '3201234567890123',
            'student_number' => 'NIM-'.fake()->unique()->numerify('#####'),
            'school_name' => 'Universitas Contoh',
            'school_major' => 'Teknik Informatika',
            'phone' => '081234567890',
            'address' => 'Jl. Contoh No. 1',
            'birth_date' => '2003-04-05',
            'gender' => 'Perempuan',
        ], $overrides);
    }

    private function register(string $token, string $email, array $extra = []): TestResponse
    {
        return $this->post(route('intern.register.store'), array_replace([
            'token' => $token,
            'name' => 'Peserta '.$email,
            'email' => $email,
            'password' => 'A-strong-password-123',
            'password_confirmation' => 'A-strong-password-123',
        ], $extra));
    }

    public function test_approval_menerbitkan_satu_token_dengan_kuota_yang_diajukan(): void
    {
        [$request, , $plaintext] = $this->approvedRequest(4);
        $token = SharedRegistrationToken::query()->sole();

        $this->assertSame($request->id, $token->internship_request_id);
        $this->assertSame(4, $token->quota);
        $this->assertSame(0, $token->used_count);
        $this->assertSame('active', $token->status);
        $this->assertSame(hash('sha256', $plaintext), $token->token_hash);
        $this->assertDatabaseCount('registration_tokens', 0);
    }

    public function test_hasil_penerbitan_token_memiliki_header_privasi_dan_detail_tidak_membocorkan_plaintext(): void
    {
        [$request, , $plaintext] = $this->approvedRequest();
        $this->assertSame(2, SharedRegistrationToken::query()->sole()->quota);
        $detail = $this->get(route('admin.internship-requests.show', $request));
        $detail->assertOk()->assertDontSee($plaintext);
        $this->assertStringContainsString('no-store', $detail->headers->get('Cache-Control', ''));
        $this->assertSame('no-referrer', $detail->headers->get('Referrer-Policy'));
        $issued = $this->get(route('admin.internship-requests.show', $request));
        $this->assertStringNotContainsString($plaintext, $issued->getContent());
    }

    public function test_admin_tidak_dapat_mengubah_kuota_saat_approval(): void
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan kuota tetap',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
            'participant_count' => 2,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'participant_count' => 99,
                'reply_letter' => UploadedFile::fake()->create('balasan.pdf', 1, 'application/pdf'),
            ])
            ->assertSessionHasErrors('participant_count');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(2, $request->fresh()->participant_count);
        $this->assertDatabaseCount('shared_registration_tokens', 0);
    }

    public function test_approval_ganda_tidak_menerbitkan_token_kedua(): void
    {
        [$request, , $plaintext] = $this->approvedRequest(2);

        $this->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter' => UploadedFile::fake()->create('balasan-kedua.pdf', 1, 'application/pdf'),
            ])
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseCount('shared_registration_tokens', 1);
        $this->assertSame(hash('sha256', $plaintext), SharedRegistrationToken::query()->sole()->token_hash);
    }

    public function test_rollback_stage6_ditolak_sebelum_menghapus_data_pengajuan_dan_token(): void
    {
        [$request] = $this->approvedRequest(2);
        $migration = require database_path('migrations/2026_11_20_012003_create_shared_registration_claims_table.php');

        try {
            $migration->down();
            $this->fail('Rollback seharusnya ditolak selama data shared token masih ada.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot roll back Stage 6', $exception->getMessage());
        }

        $this->assertDatabaseHas('internship_requests', ['id' => $request->id, 'participant_count' => 2]);
        $this->assertDatabaseCount('shared_registration_tokens', 1);
        $this->assertDatabaseCount('shared_registration_claims', 0);
    }

    public function test_shared_token_mendaftarkan_beberapa_akun_tanpa_mengurangi_kuota(): void
    {
        [, , $plaintext] = $this->approvedRequest(2);
        $token = SharedRegistrationToken::query()->sole();

        $this->register($plaintext, 'one@example.test')
            ->assertRedirect(route('intern.profile.show'))
            ->assertSessionHas('status');
        $first = UserIntern::query()->where('email', 'one@example.test')->sole();
        $this->assertAuthenticatedAs($first, 'intern');
        $this->get(route('intern.profile.show'))->assertOk()->assertSee('Lengkapi Data Pribadi');

        $this->register($plaintext, 'two@example.test')->assertRedirect(route('intern.profile.show'));

        $this->assertDatabaseCount('users_intern', 2);
        $this->assertDatabaseCount('internship_applicants', 2);
        $this->assertDatabaseCount('shared_registration_claims', 2);
        $this->assertSame(0, $token->fresh()->used_count);
        $this->assertSame('active', $token->fresh()->status);
        $this->assertSame(2, UserIntern::query()->pluck('applicant_id')->unique()->count());
        $this->assertDatabaseHas('users_intern', ['email' => 'one@example.test', 'registration_token_id' => null]);
        $this->assertDatabaseHas('users_intern', ['email' => 'two@example.test', 'registration_token_id' => null]);
    }

    public function test_profil_lengkap_mengurangi_kuota_satu_kali_dan_menyimpan_data_pada_applicant(): void
    {
        [, , $plaintext] = $this->approvedRequest(1);
        $this->register($plaintext, 'profile@example.test');
        $intern = UserIntern::query()->sole();
        $applicant = $intern->applicant;
        $token = SharedRegistrationToken::query()->sole();
        $profile = $this->profilePayload();

        $this->actingAs($intern, 'intern')->put(route('intern.profile.update'), $profile)
            ->assertRedirect(route('intern.profile.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('internship_applicants', [
            'id' => $applicant->id,
            'nik' => '3201234567890123',
            'student_number' => $profile['student_number'],
            'school_name' => 'Universitas Contoh',
            'phone' => '081234567890',
        ]);
        $this->assertSame('081234567890', $intern->fresh()->phone);
        $this->assertSame(1, $token->fresh()->used_count);
        $this->assertSame('exhausted', $token->fresh()->status);
        $this->assertSame('completed', SharedRegistrationClaim::query()->sole()->status);
        $profileResponse = $this->get(route('intern.profile.show'));
        $profileResponse->assertOk()->assertViewIs('intern.profile.show')->assertSee('Universitas Contoh');
        $this->assertStringContainsString('no-store', $profileResponse->headers->get('Cache-Control', ''));
        $this->assertSame('no-referrer', $profileResponse->headers->get('Referrer-Policy'));

        $this->actingAs($intern, 'intern')->put(route('intern.profile.update'), $this->profilePayload())
            ->assertForbidden();
        $this->assertSame(1, $token->fresh()->used_count);
    }

    public function test_profil_tidak_lengkap_tidak_mengurangi_kuota(): void
    {
        [, , $plaintext] = $this->approvedRequest(2);
        $this->register($plaintext, 'incomplete@example.test');
        $intern = UserIntern::query()->sole();

        $this->actingAs($intern, 'intern')->put(route('intern.profile.update'), [
            'nik' => '123',
            'student_number' => '',
        ])->assertSessionHasErrors(['nik', 'student_number', 'school_name', 'school_major', 'phone', 'address', 'birth_date', 'gender']);

        $this->assertSame(0, SharedRegistrationToken::query()->sole()->used_count);
        $this->assertSame('pending_profile', SharedRegistrationClaim::query()->sole()->status);
    }

    public function test_dua_akun_yang_berebut_slot_terakhir_tidak_dapat_melampaui_kuota(): void
    {
        [, , $plaintext] = $this->approvedRequest(1);
        $this->register($plaintext, 'winner@example.test');
        $winner = UserIntern::query()->where('email', 'winner@example.test')->sole();
        $this->register($plaintext, 'waiting@example.test');
        $waiting = UserIntern::query()->where('email', 'waiting@example.test')->sole();

        $this->actingAs($winner, 'intern')->put(route('intern.profile.update'), $this->profilePayload())
            ->assertRedirect(route('intern.profile.show'));
        $this->actingAs($waiting, 'intern')->put(route('intern.profile.update'), $this->profilePayload([
            'student_number' => 'NIM-WAITING',
            'nik' => '3201234567890456',
        ]))->assertSessionHasErrors('profile');

        $token = SharedRegistrationToken::query()->sole();
        $this->assertSame(1, $token->used_count);
        $this->assertSame('exhausted', $token->status);
        $this->assertSame('pending_profile', $waiting->sharedRegistrationClaim()->value('status'));
    }

    public function test_kegagalan_simpan_profil_membatalkan_pengurangan_kuota(): void
    {
        [, , $plaintext] = $this->approvedRequest(1);
        $this->register($plaintext, 'rollback@example.test');
        $intern = UserIntern::query()->sole();
        Event::listen('eloquent.updating: '.InternshipApplicant::class, function (): void {
            throw new RuntimeException('Simulated profile persistence failure.');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($intern, 'intern')->put(route('intern.profile.update'), $this->profilePayload());
            $this->fail('Expected profile persistence failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated profile persistence failure.', $exception->getMessage());
        }

        $this->assertSame(0, SharedRegistrationToken::query()->sole()->used_count);
        $this->assertSame('active', SharedRegistrationToken::query()->sole()->status);
        $this->assertSame('pending_profile', SharedRegistrationClaim::query()->sole()->status);
        $this->assertDatabaseHas('internship_applicants', ['id' => $intern->applicant_id, 'nik' => null]);
    }

    public function test_email_duplikat_tidak_membuat_akun_dan_token_tetap_tersimpan_sebagai_hash(): void
    {
        [, , $plaintext] = $this->approvedRequest(2);
        $this->register($plaintext, 'duplicate@example.test')->assertRedirect(route('intern.profile.show'));
        Auth::guard('intern')->logout();

        $this->register($plaintext, 'duplicate@example.test')->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users_intern', 1);
        $this->assertDatabaseMissing('shared_registration_tokens', ['token_hash' => $plaintext]);
        $this->assertSame(hash('sha256', $plaintext), SharedRegistrationToken::query()->sole()->token_hash);
    }

    public function test_token_yang_dicabut_atau_habis_ditolak(): void
    {
        [, $admin, $plaintext] = $this->approvedRequest(1);
        $request = InternshipRequest::query()->where('participant_count', 1)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.internship-requests.shared-registration-token.revoke', $request))
            ->assertRedirect(route('admin.internship-requests.show', $request));
        $this->register($plaintext, 'revoked@example.test')->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users_intern', 0);
        $this->assertSame('revoked', SharedRegistrationToken::query()->sole()->status);
    }

    public function test_pengguna_hanya_melihat_profilnya_sendiri_dan_guest_ditolak(): void
    {
        [, , $plaintext] = $this->approvedRequest(2);
        $this->register($plaintext, 'first@example.test');
        $first = UserIntern::query()->where('email', 'first@example.test')->sole();
        $this->register($plaintext, 'second@example.test');
        $second = UserIntern::query()->where('email', 'second@example.test')->sole();

        $this->actingAs($first, 'intern')->get(route('intern.profile.show'))->assertOk()->assertSee('first@example.test')->assertDontSee('second@example.test');
        $this->get('/intern/profile/2')->assertNotFound();
        auth('intern')->logout();
        $this->get(route('intern.profile.show'))->assertRedirect(route('intern.login'));
        $this->assertTrue(Hash::check('A-strong-password-123', $first->fresh()->password));
        $this->assertNotSame($first->id, $second->id);
    }
}
