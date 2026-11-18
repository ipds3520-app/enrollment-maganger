<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\InternPasswordResetRequest;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternPasswordResetTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_permohonan_reset_publik_generik_membuat_pending_tanpa_mengirim_email(): void
    {
        Notification::fake();
        $intern = $this->createIntern('reset-request@example.test');

        $response = $this->post(route('intern.password.email'), ['email' => $intern->email]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Jika akun tersebut terdaftar, permintaan reset password telah dicatat untuk diperiksa Admin.');
        $this->assertDatabaseHas('intern_password_reset_requests', [
            'user_intern_id' => $intern->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('password_reset_tokens_intern', 0);
        Notification::assertNothingSent();
    }

    public function test_email_tidak_terdaftar_dan_duplikat_memberi_respons_yang_sama(): void
    {
        $intern = $this->createIntern('duplicate-reset@example.test');
        $known = $this->post(route('intern.password.email'), ['email' => $intern->email]);
        $duplicate = $this->post(route('intern.password.email'), ['email' => $intern->email]);
        $unknown = $this->post(route('intern.password.email'), ['email' => 'unknown@example.test']);

        $known->assertRedirect()->assertSessionHas('status', $unknown->getSession()->get('status'));
        $duplicate->assertRedirect()->assertSessionHas('status', $unknown->getSession()->get('status'));
        $this->assertSame(1, InternPasswordResetRequest::query()->count());
    }

    public function test_email_request_menggunakan_limit_dan_tidak_menerima_id_akun_palsu(): void
    {
        $intern = $this->createIntern('id-spoof@example.test');

        $this->post(route('intern.password.email'), [
            'email' => 'unknown@example.test',
            'user_intern_id' => $intern->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('intern_password_reset_requests', 0);
        $this->assertContains('throttle:intern-password-reset', app('router')->getRoutes()->getByName('intern.password.email')->middleware());
    }

    public function test_rate_limit_membatasi_lima_permohonan_per_menit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('intern.password.email'), ['email' => 'limited@example.test'])->assertRedirect();
        }

        $this->post(route('intern.password.email'), ['email' => 'limited@example.test'])->assertTooManyRequests();
    }

    public function test_rate_limit_ip_membatasi_email_berbeda_dari_ip_yang_sama(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post(route('intern.password.email'), [
                'email' => 'distinct-'.$attempt.'@example.test',
            ])->assertRedirect();
        }

        $this->post(route('intern.password.email'), ['email' => 'twenty-first@example.test'])
            ->assertTooManyRequests();
    }

    public function test_constraint_menolak_token_pada_permintaan_pending(): void
    {
        $intern = $this->createIntern('constraint-pending@example.test');

        $this->expectException(QueryException::class);
        DB::table('intern_password_reset_requests')->insert([
            'user_intern_id' => $intern->id,
            'status' => 'pending',
            'token_hash' => hash('sha256', random_bytes(32)),
        ]);
    }

    public function test_constraint_menolak_token_yang_used_dan_revoked_bersamaan(): void
    {
        $intern = $this->createIntern('constraint-token@example.test');
        $admin = User::factory()->admin()->create();

        $this->expectException(QueryException::class);
        DB::table('intern_password_reset_requests')->insert([
            'user_intern_id' => $intern->id,
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'token_hash' => hash('sha256', random_bytes(32)),
            'token_expires_at' => now()->addMinutes(30),
            'token_used_at' => now(),
            'token_revoked_at' => now(),
        ]);
    }

    public function test_token_reset_hanya_hash_disimpan_dan_hanya_dapat_digunakan_sekali(): void
    {
        $admin = User::factory()->admin()->create();
        $intern = $this->createIntern('reset-once@example.test');
        $resetRequest = $this->createPending($intern);

        $issued = $this->actingAs($admin, 'admin')->post(route('admin.intern-password-reset-requests.approve', $resetRequest));
        $issued->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $issued->headers->get('Cache-Control'));

        $this->get(route('intern.password.reset'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('window.location.hash')
            ->assertDontSee('<script src=')
            ->assertDontSee('<link rel="stylesheet"');

        $resetUrl = $issued->getOriginalContent()->getData()['resetUrl'];
        $this->assertSame('/intern/reset-password', parse_url($resetUrl, PHP_URL_PATH));
        $plaintextToken = (string) parse_url($resetUrl, PHP_URL_FRAGMENT);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/i', $plaintextToken);
        $resetRequest->refresh();
        $this->assertSame(hash('sha256', $plaintextToken), $resetRequest->token_hash);
        $this->assertNotSame($plaintextToken, $resetRequest->token_hash);
        $this->assertEquals(now()->addMinutes(30)->timestamp, $resetRequest->token_expires_at->timestamp);
        $this->assertSame($admin->id, $resetRequest->processed_by);

        $this->post(route('intern.password.update'), [
            'token' => $plaintextToken,
            'password' => 'intern-new-password',
            'password_confirmation' => 'intern-new-password',
        ])->assertRedirect(route('intern.login'));

        $this->assertTrue(Hash::check('intern-new-password', $intern->fresh()->password));
        $this->assertNotNull($resetRequest->fresh()->token_used_at);
        $this->post(route('intern.password.update'), [
            'token' => $plaintextToken,
            'password' => 'another-new-password',
            'password_confirmation' => 'another-new-password',
        ])->assertSessionHasErrors('token');
    }

    public function test_token_kedaluwarsa_ditolak_dan_token_tidak_masuk_old_input(): void
    {
        $intern = $this->createIntern('expired-reset@example.test');
        $plaintextToken = bin2hex(random_bytes(32));
        $resetRequest = InternPasswordResetRequest::create([
            'user_intern_id' => $intern->id,
            'status' => 'approved',
            'processed_by' => User::factory()->admin()->create()->id,
            'processed_at' => now(),
            'token_hash' => hash('sha256', $plaintextToken),
            'token_expires_at' => now()->subSecond(),
        ]);

        $this->post(route('intern.password.update'), [
            'token' => $plaintextToken,
            'password' => 'another-new-password',
            'password_confirmation' => 'another-new-password',
        ])->assertSessionHasErrors('token');

        $this->assertArrayNotHasKey('token', session('_old_input', []));
        $this->assertNull($resetRequest->fresh()->token_used_at);
    }

    public function test_token_salah_atau_dicabut_ditolak(): void
    {
        $intern = $this->createIntern('revoked-reset@example.test');
        $admin = User::factory()->admin()->create();
        $plaintextToken = bin2hex(random_bytes(32));
        InternPasswordResetRequest::create([
            'user_intern_id' => $intern->id,
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'token_hash' => hash('sha256', $plaintextToken),
            'token_expires_at' => now()->addMinutes(30),
            'token_revoked_at' => now(),
        ]);

        foreach ([bin2hex(random_bytes(32)), $plaintextToken] as $token) {
            $this->post(route('intern.password.update'), [
                'token' => $token,
                'password' => 'replacement-password',
                'password_confirmation' => 'replacement-password',
            ])->assertSessionHasErrors('token');
        }

        $this->assertTrue(Hash::check('intern-password', $intern->fresh()->password));
    }

    private function createPending(UserIntern $intern): InternPasswordResetRequest
    {
        return InternPasswordResetRequest::create([
            'user_intern_id' => $intern->id,
            'status' => 'pending',
        ]);
    }

    private function createIntern(string $email, string $password = 'intern-password'): UserIntern
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Permohonan uji reset',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Intern Reset',
            'email' => $email,
            'student_number' => 'NIM-RESET-01',
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
            'password' => $password,
        ]);
    }
}
