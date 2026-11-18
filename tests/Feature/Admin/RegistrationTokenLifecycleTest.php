<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class RegistrationTokenLifecycleTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{User, InternshipRequest, InternshipApplicant} */
    private function approvedApplicant(string $name = 'Peserta Token'): array
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $admin = User::factory()->admin()->create();
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->id,
            'title' => 'Pengajuan disetujui',
            'description' => null,
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);
        $request->forceFill([
            'status' => 'approved',
            'processed_by' => $admin->id,
            'decided_at' => now(),
        ])->save();
        $applicant = $request->applicants()->create([
            'name' => $name,
            'email' => str_replace(' ', '.', strtolower($name)).'@example.test',
            'student_number' => 'SISWA-'.random_int(1000, 9999),
        ]);

        return [$admin, $request, $applicant];
    }

    private function token(InternshipApplicant $applicant, string $status = 'active', ?string $expires = null): RegistrationToken
    {
        return $applicant->registrationTokens()->create([
            'token_hash' => hash('sha256', fake()->unique()->sha256),
            'status' => $status,
            'expires_at' => $expires ?? now()->addDays(30),
            'used_at' => $status === 'used' ? now() : null,
        ]);
    }

    public function test_admin_mengganti_token_kedaluwarsa_dengan_menyimpan_histori(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 09:30:00'));
        [$admin, $request, $applicant] = $this->approvedApplicant();
        $oldToken = $this->token($applicant, 'active', now()->subSecond());

        $response = $this->actingAs($admin)->post(route('admin.internship-requests.registration-tokens.replace', [$request, $applicant]));

        $response->assertOk()->assertViewIs('admin.registration-tokens.issued');
        $oldToken->refresh();
        $this->assertSame('revoked', $oldToken->status);
        $this->assertDatabaseCount('registration_tokens', 2);
        $this->assertSame(1, $applicant->registrationTokens()->where('status', 'active')->count());

        $issued = $response->viewData('issuedTokens')[0];
        $newToken = $applicant->registrationTokens()->where('status', 'active')->firstOrFail();
        $this->assertSame(hash('sha256', $issued['token']), $newToken->token_hash);
        $this->assertTrue($newToken->expires_at->equalTo(now()->addDays(30)));
        $this->assertFalse(session()->has('issuedTokens'));
        $this->get(route('admin.internship-requests.show', $request))->assertDontSee($issued['token']);
    }

    public function test_admin_dapat_mencabut_token_dan_menerbitkan_pengganti(): void
    {
        [$admin, $request, $applicant] = $this->approvedApplicant();
        $oldToken = $this->token($applicant);

        $this->actingAs($admin)->post(route('admin.internship-requests.registration-tokens.revoke', [$request, $oldToken]))
            ->assertRedirect(route('admin.internship-requests.show', $request));
        $this->assertSame('revoked', $oldToken->fresh()->status);

        $this->post(route('admin.internship-requests.registration-tokens.replace', [$request, $applicant]))
            ->assertOk();

        $this->assertDatabaseCount('registration_tokens', 2);
        $this->assertSame(1, $applicant->registrationTokens()->where('status', 'active')->count());
    }

    public function test_token_used_tidak_dapat_diganti(): void
    {
        [$admin, $request, $applicant] = $this->approvedApplicant();
        $this->token($applicant, 'used');

        $this->actingAs($admin)->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.registration-tokens.replace', [$request, $applicant]))
            ->assertSessionHasErrors('token');

        $this->assertDatabaseCount('registration_tokens', 1);
        $this->assertSame(0, $applicant->registrationTokens()->where('status', 'active')->count());
    }

    public function test_token_tidak_dapat_diganti_jika_akun_peserta_sudah_terbentuk(): void
    {
        [$admin, $request, $applicant] = $this->approvedApplicant();
        $token = $this->token($applicant);
        DB::table('users_intern')->insert([
            'applicant_id' => $applicant->id,
            'registration_token_id' => $token->id,
            'name' => $applicant->name,
            'email' => 'akun.peserta@example.test',
            'password' => 'test-hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.registration-tokens.replace', [$request, $applicant]))
            ->assertSessionHasErrors('token');

        $this->assertDatabaseCount('registration_tokens', 1);
        $this->assertSame('active', $token->fresh()->status);
    }

    public function test_operator_dan_guest_tidak_dapat_mengelola_token(): void
    {
        [$admin, $request, $applicant] = $this->approvedApplicant();
        $operator = User::factory()->operator($request->institution)->create();
        $token = $this->token($applicant);

        $this->actingAs($operator)
            ->post(route('admin.internship-requests.registration-tokens.replace', [$request, $applicant]))
            ->assertForbidden();
        $this->actingAs($operator)
            ->post(route('admin.internship-requests.registration-tokens.revoke', [$request, $token]))
            ->assertForbidden();
        $this->actingAs($operator)
            ->get(route('operator.internship-requests.show', $request))
            ->assertOk()
            ->assertDontSee('Registration Token');

        Auth::guard('operator')->logout();
        $this->post(route('admin.internship-requests.registration-tokens.replace', [$request, $applicant]))
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.internship-requests.registration-tokens.revoke', [$request, $token]))
            ->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('registration_tokens', 1);
    }

    public function test_peserta_dari_pengajuan_lain_mendapat_404(): void
    {
        [$admin, $request] = $this->approvedApplicant();
        [, , $otherApplicant] = $this->approvedApplicant('Peserta Lain');

        $this->actingAs($admin)
            ->post(route('admin.internship-requests.registration-tokens.replace', [$request, $otherApplicant]))
            ->assertNotFound();
    }

    public function test_database_mengizinkan_histori_tetapi_membatasi_satu_token_aktif_dan_hash_unik(): void
    {
        [, , $applicant] = $this->approvedApplicant();
        $active = $this->token($applicant);
        $applicant->registrationTokens()->create([
            'token_hash' => hash('sha256', fake()->unique()->sha256),
            'status' => 'revoked',
            'expires_at' => now()->addDays(30),
            'used_at' => null,
        ]);

        try {
            $this->token($applicant);
            $this->fail('Unique partial index seharusnya menolak token aktif kedua.');
        } catch (QueryException) {
            $this->assertSame(1, $applicant->registrationTokens()->where('status', 'active')->count());
        }

        [, , $otherApplicant] = $this->approvedApplicant('Peserta Hash Sama');
        try {
            $otherApplicant->registrationTokens()->create([
                'token_hash' => $active->token_hash,
                'status' => 'active',
                'expires_at' => now()->addDays(30),
                'used_at' => null,
            ]);
            $this->fail('Unique token_hash seharusnya menolak hash ganda.');
        } catch (QueryException) {
            $this->assertDatabaseCount('registration_tokens', 2);
        }
    }

    public function test_database_menolak_status_dan_konsistensi_used_at_yang_tidak_valid(): void
    {
        [, , $applicant] = $this->approvedApplicant();

        foreach ([
            ['status' => 'expired', 'used_at' => null],
            ['status' => 'used', 'used_at' => null],
            ['status' => 'revoked', 'used_at' => now()],
        ] as $invalid) {
            try {
                $applicant->registrationTokens()->create(array_merge($invalid, [
                    'token_hash' => hash('sha256', fake()->unique()->sha256),
                    'expires_at' => now()->addDays(30),
                ]));
                $this->fail('Constraint status dan used_at seharusnya menolak record token tidak valid.');
            } catch (QueryException) {
                $this->assertDatabaseCount('registration_tokens', 0);
            }
        }
    }
}
