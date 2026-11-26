<?php

namespace Tests\Feature\Intern;

use App\Http\Middleware\EnsureInternAuthenticationVersion;
use App\Models\Institution;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternProfileEditTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_shared_account_completed_dapat_mengedit_profil_tanpa_mengubah_kuota_token_atau_relasi(): void
    {
        [$intern, $applicant, $request, $token, $claim] = $this->sharedAccount('completed');
        $tokenSnapshot = $token->only(['id', 'token_hash', 'status', 'quota', 'used_count']);
        $tokenCiphertext = $token->getRawOriginal('token_encrypted');
        $completedAt = $claim->completed_at->toDateTimeString();

        $this->actingAs($intern, 'intern')
            ->get(route('intern.profile.edit'))
            ->assertOk()
            ->assertViewIs('intern.profile.edit-account')
            ->assertSee('Edit Data Pribadi');

        $this->actingAs($intern, 'intern')
            ->put(route('intern.profile.update-details'), $this->editPayload())
            ->assertRedirect(route('intern.profile.show'))
            ->assertSessionHas('status');

        $this->assertSame($applicant->id, $intern->fresh()->applicant_id);
        $this->assertSame($request->id, $applicant->fresh()->internship_request_id);
        $this->assertSame('Nama Baru', $applicant->fresh()->name);
        $this->assertSame('Nama Baru', $intern->fresh()->name);
        $this->assertSame('081299999999', $applicant->fresh()->phone);
        $this->assertSame('081299999999', $intern->fresh()->phone);
        $this->assertSame('NIM-BARU-01', $applicant->fresh()->student_number);
        $this->assertSame('Teknik Sipil', $applicant->fresh()->school_major);
        $this->assertSame('Alamat terbaru', $applicant->fresh()->address);
        $this->assertSame('2004-05-06', $applicant->fresh()->birth_date->format('Y-m-d'));
        $this->assertSame('Laki-laki', $applicant->fresh()->gender);
        $this->assertSame('3201234567890123', $applicant->fresh()->nik);
        $this->assertSame('institusi-asal', $applicant->fresh()->school_name);
        $this->assertSame('peserta@example.test', $intern->fresh()->email);
        $this->assertSame($tokenSnapshot, $token->fresh()->only(array_keys($tokenSnapshot)));
        $this->assertSame($tokenCiphertext, $token->fresh()->getRawOriginal('token_encrypted'));
        $this->assertSame('completed', $claim->fresh()->status);
        $this->assertSame($completedAt, $claim->fresh()->completed_at->toDateTimeString());
        $this->assertSame(1, InternshipApplicant::query()->count());
    }

    public function test_menyimpan_profil_intern_tidak_mengubah_session_admin(): void
    {
        [$intern] = $this->sharedAccount('completed');
        $admin = User::factory()->admin()->create(['password' => 'admin-password']);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'admin-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->post(route('intern.login.store'), [
            'email' => $intern->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('intern.dashboard'));

        $this->put(route('intern.profile.update-details'), $this->editPayload())
            ->assertRedirect(route('intern.profile.show'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($intern, 'intern');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Dashboard Admin');
        $this->get(route('intern.profile.show'))->assertOk();
    }

    public function test_shared_account_pending_profile_tetap_harus_menyelesaikan_profil_awal(): void
    {
        [$intern, , , $token, $claim] = $this->sharedAccount('pending_profile');

        $this->post(route('intern.login.store'), [
            'email' => $intern->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('intern.dashboard'));
        $this->get(route('intern.profile.edit'))
            ->assertRedirect(route('intern.profile.show'));

        $this->put(route('intern.profile.update-details'), $this->editPayload())
            ->assertForbidden();

        $this->put(route('intern.profile.update'), $this->completionPayload())
            ->assertRedirect(route('intern.profile.show'));

        $this->assertSame(1, $token->fresh()->used_count);
        $this->assertSame('completed', $claim->fresh()->status);
    }

    public function test_profile_completion_rejects_stale_session_version_before_changing_profile_claim_or_quota(): void
    {
        [$intern, $applicant, , $token, $claim] = $this->sharedAccount('pending_profile');
        $oldVersion = (int) $intern->auth_version;
        $intern->forceFill(['auth_version' => $oldVersion + 1])->save();
        $applicantFields = [
            'name', 'nik', 'student_number', 'school_name', 'school_major', 'phone',
            'address', 'birth_date', 'gender', 'participant_status', 'completed_at',
        ];
        $applicantBefore = collect($applicantFields)
            ->mapWithKeys(fn (string $field): array => [$field => $applicant->getRawOriginal($field)])
            ->all();
        $claimFields = ['status', 'completed_at', 'internship_applicant_id'];
        $claimBefore = collect($claimFields)
            ->mapWithKeys(fn (string $field): array => [$field => $claim->getRawOriginal($field)])
            ->all();
        $tokenBefore = $token->only(['token_hash', 'token_encrypted', 'status', 'quota', 'used_count']);

        $this->withoutMiddleware(EnsureInternAuthenticationVersion::class)
            ->actingAs($intern, 'intern')
            ->withSession(['intern_auth_version' => $oldVersion])
            ->put(route('intern.profile.update'), $this->completionPayload())
            ->assertForbidden();

        $freshApplicant = $applicant->fresh();
        $freshClaim = $claim->fresh();
        $this->assertSame($applicantBefore, collect($applicantFields)
            ->mapWithKeys(fn (string $field): array => [$field => $freshApplicant->getRawOriginal($field)])
            ->all());
        $this->assertSame($claimBefore, collect($claimFields)
            ->mapWithKeys(fn (string $field): array => [$field => $freshClaim->getRawOriginal($field)])
            ->all());
        $this->assertSame($tokenBefore, $token->fresh()->only(array_keys($tokenBefore)));
        $this->assertSame($oldVersion + 1, (int) $intern->fresh()->auth_version);
        $this->assertSame(0, InternshipApplicant::query()->where('participant_status', 'active')->count());
    }

    public function test_completed_claim_rejects_stale_session_after_account_lock_without_changing_data(): void
    {
        [$intern, $applicant, , $token, $claim] = $this->sharedAccount('completed');
        $oldVersion = (int) $intern->auth_version;
        $intern->forceFill(['auth_version' => $oldVersion + 1])->save();

        $applicantFields = [
            'name', 'nik', 'student_number', 'school_name', 'school_major', 'phone',
            'address', 'birth_date', 'gender', 'participant_status', 'completed_at',
        ];
        $applicantBefore = collect($applicantFields)
            ->mapWithKeys(fn (string $field): array => [$field => $applicant->getRawOriginal($field)])
            ->all();
        $claimFields = ['status', 'completed_at', 'internship_applicant_id', 'shared_registration_token_id'];
        $claimBefore = collect($claimFields)
            ->mapWithKeys(fn (string $field): array => [$field => $claim->getRawOriginal($field)])
            ->all();
        $tokenBefore = $token->only(['token_hash', 'status', 'quota', 'used_count']);
        $tokenCiphertext = $token->getRawOriginal('token_encrypted');

        $response = $this->withoutMiddleware(EnsureInternAuthenticationVersion::class)
            ->actingAs($intern, 'intern')
            ->withSession(['intern_auth_version' => $oldVersion])
            ->put(route('intern.profile.update'), $this->completionPayload());

        $response->assertForbidden()->assertSessionMissing('status');

        $freshApplicant = $applicant->fresh();
        $freshClaim = $claim->fresh();
        $freshToken = $token->fresh();
        $this->assertSame($applicantBefore, collect($applicantFields)
            ->mapWithKeys(fn (string $field): array => [$field => $freshApplicant->getRawOriginal($field)])
            ->all());
        $this->assertSame($claimBefore, collect($claimFields)
            ->mapWithKeys(fn (string $field): array => [$field => $freshClaim->getRawOriginal($field)])
            ->all());
        $this->assertSame($tokenBefore, $freshToken->only(array_keys($tokenBefore)));
        $this->assertSame($tokenCiphertext, $freshToken->getRawOriginal('token_encrypted'));
        $this->assertSame($oldVersion + 1, (int) $intern->fresh()->auth_version);
    }

    public function test_completed_claim_with_current_session_returns_idempotent_result_without_changes(): void
    {
        [$intern, $applicant, , $token, $claim] = $this->sharedAccount('completed');
        $this->post(route('intern.login.store'), [
            'email' => $intern->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('intern.dashboard'));
        $authVersionBefore = (int) $intern->fresh()->auth_version;

        $applicantFields = [
            'name', 'nik', 'student_number', 'school_name', 'school_major', 'phone',
            'address', 'birth_date', 'gender', 'participant_status', 'completed_at',
        ];
        $applicantBefore = collect($applicantFields)
            ->mapWithKeys(fn (string $field): array => [$field => $applicant->getRawOriginal($field)])
            ->all();
        $claimFields = ['status', 'completed_at', 'internship_applicant_id', 'shared_registration_token_id'];
        $claimBefore = collect($claimFields)
            ->mapWithKeys(fn (string $field): array => [$field => $claim->getRawOriginal($field)])
            ->all();
        $tokenBefore = $token->only(['token_hash', 'status', 'quota', 'used_count']);
        $tokenCiphertext = $token->getRawOriginal('token_encrypted');

        $this->put(route('intern.profile.update'), $this->completionPayload())
            ->assertRedirect(route('intern.profile.show'))
            ->assertSessionHas('status', 'Profil Anda sudah lengkap.');

        $freshApplicant = $applicant->fresh();
        $freshClaim = $claim->fresh();
        $freshToken = $token->fresh();
        $this->assertSame($applicantBefore, collect($applicantFields)
            ->mapWithKeys(fn (string $field): array => [$field => $freshApplicant->getRawOriginal($field)])
            ->all());
        $this->assertSame($claimBefore, collect($claimFields)
            ->mapWithKeys(fn (string $field): array => [$field => $freshClaim->getRawOriginal($field)])
            ->all());
        $this->assertSame($tokenBefore, $freshToken->only(array_keys($tokenBefore)));
        $this->assertSame($tokenCiphertext, $freshToken->getRawOriginal('token_encrypted'));
        $this->assertSame($authVersionBefore, (int) $intern->fresh()->auth_version);
    }

    public function test_akun_legacy_dapat_melihat_dan_mengedit_profil_tanpa_shared_claim(): void
    {
        [$intern, $applicant, $request, $registrationToken] = $this->legacyAccount();
        $tokenStatus = $registrationToken->status;

        $this->actingAs($intern, 'intern')
            ->get(route('intern.profile.show'))
            ->assertOk()
            ->assertViewIs('intern.profile.show')
            ->assertSee($applicant->school_name);

        $this->actingAs($intern, 'intern')
            ->get(route('intern.profile.edit'))
            ->assertOk()
            ->assertViewIs('intern.profile.edit-account');

        $this->actingAs($intern, 'intern')
            ->put(route('intern.profile.update-details'), $this->editPayload())
            ->assertRedirect(route('intern.profile.show'));

        $this->assertSame('Nama Baru', $intern->fresh()->name);
        $this->assertSame('Nama Baru', $applicant->fresh()->name);
        $this->assertSame('081299999999', $intern->fresh()->phone);
        $this->assertSame('081299999999', $applicant->fresh()->phone);
        $this->assertSame($request->id, $applicant->fresh()->internship_request_id);
        $this->assertSame('active', $applicant->fresh()->participant_status);
        $this->assertNull($applicant->fresh()->completed_at);
        $this->assertSame($tokenStatus, $registrationToken->fresh()->status);
        $this->assertDatabaseCount('shared_registration_claims', 0);
    }

    public function test_nim_nis_duplikat_ditolak_dan_nilai_milik_sendiri_diizinkan(): void
    {
        [$intern, $applicant, $request] = $this->sharedAccount('completed');
        $other = $request->applicants()->create([
            'name' => 'Peserta Lain',
            'email' => 'lain@example.test',
            'student_number' => 'NIM-OTHER',
        ]);

        $this->actingAs($intern, 'intern')
            ->put(route('intern.profile.update-details'), $this->editPayload(['student_number' => $other->student_number]))
            ->assertSessionHasErrors('student_number');

        $payload = $this->editPayload(['student_number' => $applicant->student_number]);
        $this->actingAs($intern, 'intern')
            ->put(route('intern.profile.update-details'), $payload)
            ->assertRedirect(route('intern.profile.show'));

        $this->assertSame($applicant->student_number, $applicant->fresh()->student_number);
    }

    public function test_nik_email_institusi_dan_identitas_kepemilikan_ditolak_dari_request_edit(): void
    {
        [$intern, $applicant] = $this->sharedAccount('completed');
        $otherApplicant = $applicant->internshipRequest()->firstOrFail()->applicants()->create([
            'name' => 'Peserta Lain',
            'email' => 'peserta-lain@example.test',
            'student_number' => 'NIM-LAIN',
        ]);

        $this->actingAs($intern, 'intern')
            ->put(route('intern.profile.update-details'), $this->editPayload([
                'nik' => '0000000000000000',
                'email' => 'attacker@example.test',
                'school_name' => 'Institusi Lain',
                'applicant_id' => $otherApplicant->id,
                'internship_request_id' => $applicant->internship_request_id + 100,
            ]))
            ->assertSessionHasErrors(['nik', 'email', 'school_name', 'applicant_id', 'internship_request_id']);

        $this->assertSame('3201234567890123', $applicant->fresh()->nik);
        $this->assertSame('institusi-asal', $applicant->fresh()->school_name);
        $this->assertSame('Peserta Lain', $otherApplicant->fresh()->name);
        $this->assertSame('peserta@example.test', $intern->fresh()->email);
        $this->assertSame($applicant->id, $intern->fresh()->applicant_id);
    }

    public function test_update_gagal_di_tengah_transaksi_merollback_perubahan_applicant(): void
    {
        [$intern, $applicant] = $this->sharedAccount('completed');
        Event::listen('eloquent.updating: '.UserIntern::class, function (): void {
            throw new RuntimeException('Simulated account sync failure.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($intern, 'intern')->put(route('intern.profile.update-details'), $this->editPayload());
            $this->fail('Expected account sync failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated account sync failure.', $exception->getMessage());
        }

        $this->assertSame('Nama Sebelumnya', $applicant->fresh()->name);
        $this->assertSame('081200000000', $applicant->fresh()->phone);
        $this->assertNull($applicant->fresh()->participant_status);
        $this->assertSame('Nama Sebelumnya', $intern->fresh()->name);
        $this->assertSame('081200000000', $intern->fresh()->phone);
    }

    /** @return array{UserIntern, InternshipApplicant, InternshipRequest, SharedRegistrationToken, SharedRegistrationClaim} */
    private function sharedAccount(string $claimStatus): array
    {
        $request = $this->internshipRequest();
        $token = SharedRegistrationToken::forceCreate([
            'internship_request_id' => $request->id,
            'token_hash' => hash('sha256', 'shared-token-'.$request->id),
            'quota' => 2,
            'used_count' => $claimStatus === 'completed' ? 1 : 0,
            'status' => 'active',
            'token_encrypted' => Crypt::encryptString('shared-token-'.$request->id),
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Nama Sebelumnya',
            'email' => 'peserta@example.test',
            'phone' => '081200000000',
            'student_number' => 'NIM-LAMA',
            'nik' => '3201234567890123',
            'school_name' => 'institusi-asal',
            'school_major' => 'Teknik Informatika',
            'address' => 'Alamat lama',
            'birth_date' => '2003-04-05',
            'gender' => 'Perempuan',
        ]);
        $intern = UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => null,
            'name' => 'Nama Sebelumnya',
            'email' => 'peserta@example.test',
            'password' => Hash::make('secret-password'),
            'phone' => '081200000000',
        ]);
        $claim = SharedRegistrationClaim::create([
            'shared_registration_token_id' => $token->id,
            'user_intern_id' => $intern->id,
            'internship_applicant_id' => $applicant->id,
            'status' => $claimStatus,
            'claimed_at' => now()->subDay(),
            'completed_at' => $claimStatus === 'completed' ? now()->subHour() : null,
        ]);

        return [$intern, $applicant, $request, $token, $claim];
    }

    /** @return array{UserIntern, InternshipApplicant, InternshipRequest, RegistrationToken} */
    private function legacyAccount(): array
    {
        $request = $this->internshipRequest();
        $applicant = $request->applicants()->create([
            'name' => 'Nama Sebelumnya',
            'email' => 'legacy@example.test',
            'phone' => '081200000000',
            'student_number' => 'NIM-LEGACY',
            'nik' => '3201234567890123',
            'school_name' => 'Sekolah Legacy',
            'school_major' => 'Teknik Informatika',
            'address' => 'Alamat lama',
            'birth_date' => '2003-04-05',
            'gender' => 'Perempuan',
        ]);
        $registrationToken = RegistrationToken::create([
            'applicant_id' => $applicant->id,
            'token_hash' => hash('sha256', 'legacy-token-'.$applicant->id),
            'status' => 'used',
            'expires_at' => now()->addDays(30),
            'used_at' => now()->subDay(),
        ]);
        $intern = UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => $registrationToken->id,
            'name' => 'Nama Sebelumnya',
            'email' => 'legacy@example.test',
            'password' => Hash::make('secret-password'),
            'phone' => '081200000000',
        ]);

        return [$intern, $applicant, $request, $registrationToken];
    }

    private function internshipRequest(): InternshipRequest
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $admin = User::factory()->admin()->create();

        return InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan edit profil',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
            'participant_count' => 2,
            'status' => 'approved',
            'processed_by' => $admin->id,
            'decided_at' => now()->subDays(2),
        ]);
    }

    /** @return array<string, string> */
    private function editPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Nama Baru',
            'student_number' => 'NIM-BARU-01',
            'school_major' => 'Teknik Sipil',
            'phone' => '081299999999',
            'address' => 'Alamat terbaru',
            'birth_date' => '2004-05-06',
            'gender' => 'Laki-laki',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function completionPayload(): array
    {
        return [
            'nik' => '3201234567890123',
            'student_number' => 'NIM-LENGKAP',
            'school_major' => 'Teknik Informatika',
            'phone' => '081234567890',
            'address' => 'Alamat lengkap',
            'birth_date' => '2003-04-05',
            'gender' => 'Perempuan',
        ];
    }
}
