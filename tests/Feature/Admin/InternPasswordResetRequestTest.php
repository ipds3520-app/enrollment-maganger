<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternPasswordResetRequest;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Support\Facades\Notification;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternPasswordResetRequestTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_admin_melihat_detail_verifikasi_dan_memproses_reject_dengan_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $intern = $this->createIntern('admin-review@example.test');
        $resetRequest = InternPasswordResetRequest::create(['user_intern_id' => $intern->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.intern-password-reset-requests.index'))
            ->assertOk()
            ->assertSee($intern->name)
            ->assertSee($intern->email);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.intern-password-reset-requests.show', $resetRequest))
            ->assertOk()
            ->assertSee('NIM-RESET-01')
            ->assertSee('Institusi Reset Test')
            ->assertSee('Pengajuan Reset Test');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.intern-password-reset-requests.reject', $resetRequest))
            ->assertRedirect(route('admin.intern-password-reset-requests.show', $resetRequest))
            ->assertSessionHas('status', 'Permintaan reset password berhasil ditolak.');

        $this->assertDatabaseHas('intern_password_reset_requests', [
            'id' => $resetRequest->id,
            'status' => 'rejected',
            'processed_by' => $admin->id,
        ]);
        $this->assertNotNull($resetRequest->fresh()->processed_at);
        $this->assertSame(1, InternPasswordResetRequest::query()->count());
    }

    public function test_hanya_guard_admin_dapat_mengakses_route_admin(): void
    {
        $requestId = 1;

        $this->get(route('admin.intern-password-reset-requests.index'))->assertRedirect();

        $operator = User::factory()->operator(Institution::factory()->create())->create();
        $this->actingAs($operator, 'operator')
            ->get(route('admin.intern-password-reset-requests.index'))
            ->assertRedirect();

        $this->actingAs($operator, 'operator')
            ->post(route('admin.intern-password-reset-requests.approve', $requestId))
            ->assertRedirect();

        $intern = $this->createIntern('forbidden-admin-route@example.test');
        $this->actingAs($intern, 'intern')
            ->get(route('admin.intern-password-reset-requests.index'))
            ->assertRedirect();
    }

    public function test_approval_ulang_tidak_menampilkan_token_dan_approval_baru_mencabut_token_lama(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $intern = $this->createIntern('replace-reset@example.test');
        $first = InternPasswordResetRequest::create(['user_intern_id' => $intern->id]);

        $firstResponse = $this->actingAs($admin, 'admin')->post(route('admin.intern-password-reset-requests.approve', $first));
        $firstResponse->assertSee('pagehide');
        $firstToken = $this->plaintextTokenFromResponse($firstResponse);
        $first->refresh();
        $this->get(route('admin.intern-password-reset-requests.show', $first))
            ->assertOk()
            ->assertDontSee($firstToken);
        Notification::assertNothingSent();

        $this->post(route('admin.intern-password-reset-requests.approve', $first))
            ->assertRedirect(route('admin.intern-password-reset-requests.show', $first))
            ->assertSessionHas('status')
            ->assertDontSee($firstToken);

        $second = InternPasswordResetRequest::create(['user_intern_id' => $intern->id]);
        $secondResponse = $this->post(route('admin.intern-password-reset-requests.approve', $second));
        $secondToken = $this->plaintextTokenFromResponse($secondResponse);
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertNotNull($first->fresh()->token_revoked_at);
        $this->assertSame(2, InternPasswordResetRequest::query()->count());
    }

    public function test_permintaan_baru_setelah_reject_menyimpan_riwayat_lama(): void
    {
        $admin = User::factory()->admin()->create();
        $intern = $this->createIntern('history-reset@example.test');
        $previous = InternPasswordResetRequest::create(['user_intern_id' => $intern->id]);

        $this->actingAs($admin, 'admin')->post(route('admin.intern-password-reset-requests.reject', $previous));
        $next = InternPasswordResetRequest::create(['user_intern_id' => $intern->id]);

        $this->assertSame('rejected', $previous->fresh()->status);
        $this->assertSame('pending', $next->fresh()->status);
        $this->assertSame(2, InternPasswordResetRequest::query()->count());
    }

    public function test_reject_ulang_pada_status_rejected_atau_approved_tidak_mengubah_data(): void
    {
        $admin = User::factory()->admin()->create();
        $intern = $this->createIntern('already-processed@example.test');

        $rejected = InternPasswordResetRequest::create([
            'user_intern_id' => $intern->id,
            'status' => 'rejected',
            'processed_by' => $admin->id,
            'processed_at' => now()->subMinute(),
        ]);
        $approved = InternPasswordResetRequest::create([
            'user_intern_id' => $intern->id,
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now()->subMinute(),
            'token_hash' => hash('sha256', random_bytes(32)),
            'token_expires_at' => now()->addMinutes(29),
        ]);
        $rejectedUpdatedAt = $rejected->fresh()->updated_at;
        $approvedUpdatedAt = $approved->fresh()->updated_at;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.intern-password-reset-requests.reject', $rejected))
            ->assertSessionHas('status', 'Permintaan ini sudah diproses sebelumnya.');
        $this->post(route('admin.intern-password-reset-requests.reject', $approved))
            ->assertSessionHas('status', 'Permintaan ini sudah diproses sebelumnya.');

        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertEquals($rejectedUpdatedAt, $rejected->fresh()->updated_at);
        $this->assertEquals($approvedUpdatedAt, $approved->fresh()->updated_at);
    }

    private function plaintextTokenFromResponse($response): string
    {
        $response->assertOk();
        $url = $response->getOriginalContent()->getData()['resetUrl'];
        $this->assertSame('/intern/reset-password', parse_url($url, PHP_URL_PATH));

        return (string) parse_url($url, PHP_URL_FRAGMENT);
    }

    private function createIntern(string $email): UserIntern
    {
        $institution = Institution::factory()->create(['name' => 'Institusi Reset Test']);
        $operator = User::factory()->operator($institution)->create();
        $request = InternshipRequest::create([
            'institution_id' => $institution->id,
            'created_by' => $operator->id,
            'title' => 'Pengajuan Reset Test',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Intern Admin Reset',
            'email' => $email,
            'student_number' => 'NIM-RESET-01',
        ]);
        $registrationToken = RegistrationToken::create([
            'applicant_id' => $applicant->id,
            'token_hash' => hash('sha256', random_bytes(32)),
            'status' => 'used',
            'expires_at' => now()->addDays(30),
            'used_at' => now(),
        ]);

        return UserIntern::create([
            'applicant_id' => $applicant->id,
            'registration_token_id' => $registrationToken->id,
            'name' => $applicant->name,
            'email' => $email,
            'password' => 'intern-password',
        ]);
    }
}
