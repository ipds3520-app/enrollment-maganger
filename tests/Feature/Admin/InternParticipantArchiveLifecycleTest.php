<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternPasswordResetRequest;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Auth\Recaller;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternParticipantArchiveLifecycleTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_admin_archives_account_without_deleting_profile_attendance_tokens_claims_or_reset_history(): void
    {
        $participant = $this->participant('active');
        $admin = User::factory()->admin()->create();
        $this->createAttendanceFixtures();
        $attendanceRequestId = DB::table('attendance_requests')->insertGetId([
            'internship_applicant_id' => $participant['applicant']->getKey(),
        ]);
        $attendanceId = DB::table('attendances')->insertGetId([
            'internship_applicant_id' => $participant['applicant']->getKey(),
        ]);

        $resetHistory = InternPasswordResetRequest::query()->create([
            'user_intern_id' => $participant['intern']->getKey(),
            'status' => 'pending',
        ]);
        $tokenSnapshot = $participant['token']->only(['id', 'token_hash', 'token_encrypted', 'quota', 'used_count', 'status']);
        $claimSnapshot = [
            'id' => $participant['claim']->getKey(),
            'status' => $participant['claim']->status,
            'completed_at' => $participant['claim']->completed_at->toDateTimeString(),
        ];

        $this->actingAs($admin)
            ->delete(route('admin.intern-participants.archive', $participant['intern']))
            ->assertRedirect(route('admin.intern-participants.index'));

        $this->assertNotNull(UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey())->deleted_at);
        $this->assertDatabaseHas('internship_applicants', ['id' => $participant['applicant']->getKey()]);
        $this->assertDatabaseHas('attendance_requests', ['id' => $attendanceRequestId]);
        $this->assertDatabaseHas('attendances', ['id' => $attendanceId]);
        $this->assertDatabaseHas('intern_password_reset_requests', ['id' => $resetHistory->getKey()]);
        $this->assertSame($tokenSnapshot, $participant['token']->fresh()->only(array_keys($tokenSnapshot)));
        $freshClaim = $participant['claim']->fresh();
        $this->assertSame($claimSnapshot['id'], $freshClaim->getKey());
        $this->assertSame($claimSnapshot['status'], $freshClaim->status);
        $this->assertSame($claimSnapshot['completed_at'], $freshClaim->completed_at->toDateTimeString());

        $this->get(route('admin.intern-participants.index'))
            ->assertOk()
            ->assertDontSee('Peserta Uji');
        $this->get(route('admin.intern-participants.archived.index'))
            ->assertOk()
            ->assertSee('Peserta Uji')
            ->assertSee('SMA Uji');
        $this->get(route('admin.intern-participants.show', $participant['intern']))->assertNotFound();
        $this->get(route('admin.intern-participants.archived.show', $participant['intern']))
            ->assertOk()
            ->assertSee('Detail Peserta Terarsip');
    }

    public function test_admin_can_restore_archived_participant_without_changing_account_or_lifecycle_data(): void
    {
        $participant = $this->participant('completed');
        $completedAt = now()->subDay()->startOfSecond();
        $participant['applicant']->forceFill(['completed_at' => $completedAt])->save();
        $admin = User::factory()->admin()->create();
        $history = $participant['applicant']->statusHistories()->create([
            'old_status' => 'active',
            'new_status' => 'completed',
            'changed_by' => $admin->getKey(),
            'changed_at' => $completedAt,
        ]);
        $participant['intern']->delete();

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.restore', $participant['intern']))
            ->assertRedirect(route('admin.intern-participants.index'));

        $restored = UserIntern::query()->findOrFail($participant['intern']->getKey());
        $applicant = $participant['applicant']->fresh();
        $this->assertNull($restored->deleted_at);
        $this->assertSame($participant['applicant']->getKey(), $restored->applicant_id);
        $this->assertSame($participant['intern']->email, $restored->email);
        $this->assertSame($participant['intern']->password, $restored->password);
        $this->assertSame('completed', $applicant->participant_status);
        $this->assertTrue($applicant->completed_at->equalTo($completedAt));
        $this->assertDatabaseHas('internship_participant_status_histories', ['id' => $history->getKey()]);
        $this->assertDatabaseHas('shared_registration_tokens', ['id' => $participant['token']->getKey()]);
        $this->assertDatabaseHas('shared_registration_claims', ['id' => $participant['claim']->getKey()]);
        $this->get(route('admin.intern-participants.index'))->assertOk()->assertSee('Peserta Uji');
    }

    public function test_restore_preserves_a_legacy_registration_token_relation(): void
    {
        $participant = $this->participant('active');
        $legacyToken = RegistrationToken::query()->create([
            'applicant_id' => $participant['applicant']->getKey(),
            'token_hash' => hash('sha256', 'legacy-archive-token'),
            'status' => 'used',
            'expires_at' => now()->addDay(),
            'used_at' => now(),
        ]);
        $participant['intern']->forceFill(['registration_token_id' => $legacyToken->getKey()])->save();
        $participant['intern']->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.intern-participants.restore', $participant['intern']))
            ->assertRedirect(route('admin.intern-participants.index'));

        $this->assertSame($legacyToken->getKey(), UserIntern::query()->findOrFail($participant['intern']->getKey())->registration_token_id);
        $this->assertDatabaseHas('registration_tokens', ['id' => $legacyToken->getKey(), 'status' => 'used']);
    }

    public function test_archive_invalidates_old_intern_session_across_restore_without_affecting_other_guards(): void
    {
        $participant = $this->participant('active');
        $admin = User::factory()->admin()->create(['password' => 'admin-password']);
        $operator = User::factory()->operator($participant['institution'])->create(['password' => 'operator-password']);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'admin-password'])->assertRedirect();
        $this->post(route('operator.login.store'), ['email' => $operator->email, 'password' => 'operator-password'])->assertRedirect();
        $this->post(route('intern.login.store'), ['email' => $participant['intern']->email, 'password' => 'original-password'])->assertRedirect(route('intern.dashboard'));

        $oldVersion = (int) $participant['intern']->fresh()->auth_version;
        $this->delete(route('admin.intern-participants.archive', $participant['intern']))->assertRedirect();
        $this->assertSame($oldVersion + 1, (int) UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey())->auth_version);

        $this->app['auth']->forgetGuards();
        $this->get(route('intern.dashboard'))->assertRedirect(route('intern.login'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');

        $this->post(route('admin.intern-participants.restore', $participant['intern']))->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->get(route('intern.dashboard'))->assertRedirect(route('intern.login'));
        $this->assertGuest('intern');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');

        $this->post(route('intern.login.store'), ['email' => $participant['intern']->email, 'password' => 'original-password'])
            ->assertRedirect(route('intern.dashboard'));
        $this->get(route('intern.dashboard'))->assertOk();
    }

    public function test_remember_me_cookie_from_before_archive_cannot_authenticate_after_restore(): void
    {
        $participant = $this->participant('active');
        $admin = User::factory()->admin()->create(['password' => 'admin-password']);
        $internGuard = Auth::guard('intern');
        $recallerName = $internGuard->getRecallerName();

        $login = $this->post(route('intern.login.store'), [
            'email' => $participant['intern']->email,
            'password' => 'original-password',
            'remember' => true,
        ])->assertRedirect(route('intern.dashboard'));
        $rememberCookie = $login->getCookie($recallerName, false);
        $this->assertNotNull($rememberCookie);
        $recaller = new Recaller($login->getCookie($recallerName)->getValue());
        $internId = $participant['intern']->getKey();
        $this->assertSame(
            $internId,
            app('auth')->createUserProvider('interns')->retrieveByToken($internId, $recaller->token())?->getAuthIdentifier()
        );
        $oldRememberToken = $participant['intern']->fresh()->getRememberToken();

        $admin = User::factory()->admin()->create(['password' => 'admin-password']);
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'admin-password'])->assertRedirect();
        $this->delete(route('admin.intern-participants.archive', $participant['intern']))->assertRedirect();
        $this->assertNotSame($oldRememberToken, UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey())->getRememberToken());
        $this->assertNull(app('auth')->createUserProvider('interns')->retrieveByToken($internId, $recaller->token()));
        $this->post(route('admin.intern-participants.restore', $participant['intern']))->assertRedirect();

        $this->app['session']->forget($internGuard->getName());
        $this->app['auth']->forgetGuards();
        $this->withCookie($recallerName, $rememberCookie->getValue())
            ->get(route('intern.dashboard'))
            ->assertRedirect(route('intern.login'));
        $this->assertGuest('intern');
    }

    public function test_repeated_archive_and_restore_requests_are_safe_and_do_not_change_data(): void
    {
        $participant = $this->participant('active');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('admin.intern-participants.archive', $participant['intern']))->assertRedirect();
        $archived = UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey());
        $deletedAt = $archived->deleted_at->toDateTimeString();
        $version = (int) $archived->auth_version;

        $this->delete(route('admin.intern-participants.archive', $participant['intern']))->assertNotFound();
        $archived->refresh();
        $this->assertSame($deletedAt, $archived->deleted_at->toDateTimeString());
        $this->assertSame($version, (int) $archived->auth_version);

        $this->post(route('admin.intern-participants.restore', $participant['intern']))->assertRedirect();
        $restored = UserIntern::query()->findOrFail($participant['intern']->getKey());
        $this->post(route('admin.intern-participants.restore', $participant['intern']))->assertNotFound();
        $this->assertNull($restored->fresh()->deleted_at);
        $this->assertSame($version, (int) $restored->fresh()->auth_version);
    }

    public function test_auth_version_migration_refuses_rollback_after_revocation_state_is_used(): void
    {
        $participant = $this->participant('active');
        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.intern-participants.archive', $participant['intern']))
            ->assertRedirect();

        $migration = require database_path('migrations/2026_11_26_000002_add_auth_version_to_users_intern_table.php');

        try {
            $migration->down();
            $this->fail('Rollback seharusnya mempertahankan versi autentikasi yang telah dinaikkan.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot remove Intern authentication versions', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('users_intern', 'auth_version'));
        $this->assertSame(2, (int) UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey())->auth_version);
    }

    public function test_admin_status_transitions_set_completion_time_and_append_history(): void
    {
        $participant = $this->participant('active');
        $admin = User::factory()->admin()->create();
        $tokenSnapshot = $participant['token']->only(['id', 'token_hash', 'token_encrypted', 'quota', 'used_count', 'status']);
        $claimCompletedAt = $participant['claim']->completed_at->toDateTimeString();

        $this->actingAs($admin)
            ->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
                'participant_status' => 'completed',
            ])
            ->assertRedirect(route('admin.intern-participants.show', $participant['intern']));

        $applicant = $participant['applicant']->fresh();
        $this->assertSame('completed', $applicant->participant_status);
        $this->assertNotNull($applicant->completed_at);
        $firstHistory = $applicant->statusHistories()->sole();
        $this->assertSame('active', $firstHistory->old_status);
        $this->assertSame('completed', $firstHistory->new_status);
        $this->assertSame($admin->getKey(), $firstHistory->changed_by);
        $this->assertTrue($firstHistory->changed_at->equalTo($applicant->completed_at));

        $this->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
            'participant_status' => 'active',
        ])->assertRedirect(route('admin.intern-participants.show', $participant['intern']));

        $applicant->refresh();
        $this->assertSame('active', $applicant->participant_status);
        $this->assertNull($applicant->completed_at);
        $this->assertSame(2, $applicant->statusHistories()->count());
        $latestHistory = $applicant->statusHistories()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame('completed', $latestHistory->old_status);
        $this->assertSame('active', $latestHistory->new_status);
        $this->assertSame($tokenSnapshot, $participant['token']->fresh()->only(array_keys($tokenSnapshot)));
        $this->assertSame($claimCompletedAt, $participant['claim']->fresh()->completed_at->toDateTimeString());

        $this->get(route('admin.intern-participants.show', $participant['intern']))
            ->assertOk()
            ->assertSee('Riwayat Status')
            ->assertSee($admin->name);
    }

    public function test_admin_can_classify_null_status_explicitly_and_same_status_does_not_add_history(): void
    {
        $participant = $this->participant(null);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
                'participant_status' => 'completed',
            ])
            ->assertRedirect();

        $applicant = $participant['applicant']->fresh();
        $this->assertSame('completed', $applicant->participant_status);
        $this->assertNotNull($applicant->completed_at);
        $this->assertSame(1, $applicant->statusHistories()->count());
        $this->assertNull($applicant->statusHistories()->sole()->old_status);

        $this->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
            'participant_status' => 'completed',
        ])->assertRedirect();

        $this->assertSame(1, $applicant->statusHistories()->count());
    }

    public function test_archived_participant_cannot_have_lifecycle_changed_and_non_admins_cannot_use_archive_features(): void
    {
        $participant = $this->participant('active');
        $operator = User::factory()->operator($participant['institution'])->create();
        $participant['intern']->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
                'participant_status' => 'completed',
            ])->assertNotFound();

        Auth::guard('admin')->logout();
        Auth::guard('operator')->logout();
        Auth::guard('intern')->logout();
        $this->actingAs($operator)
            ->get(route('admin.intern-participants.archived.index'))
            ->assertForbidden();
        $this->delete(route('admin.intern-participants.archive', $participant['intern']))->assertForbidden();
        $this->post(route('admin.intern-participants.restore', $participant['intern']))->assertForbidden();
        $this->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
            'participant_status' => 'completed',
        ])->assertForbidden();

        Auth::guard('admin')->logout();
        Auth::guard('operator')->logout();
        Auth::guard('intern')->logout();
        $this->actingAs($participant['intern'])
            ->get(route('admin.intern-participants.archived.index'))
            ->assertForbidden();
        $this->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
            'participant_status' => 'completed',
        ])->assertForbidden();

        Auth::guard('admin')->logout();
        Auth::guard('operator')->logout();
        Auth::guard('intern')->logout();
        $this->post(route('admin.intern-participants.restore', $participant['intern']))->assertRedirect();
        $this->delete(route('admin.intern-participants.archive', $participant['intern']))->assertRedirect();
        $this->patch(route('admin.intern-participants.lifecycle.update', $participant['intern']), [
            'participant_status' => 'completed',
        ])->assertRedirect();
    }

    /** @return array{intern: UserIntern, applicant: InternshipApplicant, request: InternshipRequest, institution: Institution, token: SharedRegistrationToken, claim: SharedRegistrationClaim} */
    private function participant(?string $status): array
    {
        $institution = Institution::factory()->create(['name' => 'SMA Uji']);
        $operator = User::factory()->operator($institution)->create();
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->getKey(),
            'title' => 'Periode Magang Uji',
            'description' => 'Pengujian lifecycle.',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Peserta Uji',
            'email' => 'profile@example.test',
            'phone' => '081234567890',
            'student_number' => 'NIM-001',
            'school_major' => 'Informatika',
            'school_name' => $institution->name,
        ]);
        $applicant->forceFill([
            'participant_status' => $status,
            'completed_at' => $status === 'completed' ? now()->subDay() : null,
        ])->save();
        $intern = UserIntern::query()->create([
            'applicant_id' => $applicant->getKey(),
            'registration_token_id' => null,
            'name' => 'Peserta Uji',
            'email' => 'intern@example.test',
            'password' => 'original-password',
            'phone' => '081234567890',
        ]);
        $token = SharedRegistrationToken::query()->create([
            'internship_request_id' => $request->getKey(),
            'token_hash' => hash('sha256', 'archive-lifecycle-token'),
            'token_encrypted' => encrypt('archive-lifecycle-token'),
            'quota' => 2,
            'used_count' => 1,
            'status' => 'active',
        ]);
        $claim = SharedRegistrationClaim::query()->create([
            'shared_registration_token_id' => $token->getKey(),
            'user_intern_id' => $intern->getKey(),
            'internship_applicant_id' => $applicant->getKey(),
            'status' => 'completed',
            'claimed_at' => now()->subDays(5),
            'completed_at' => now()->subDays(4),
        ]);

        return compact('intern', 'applicant', 'request', 'institution', 'token', 'claim');
    }

    private function createAttendanceFixtures(): void
    {
        Schema::create('attendance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_applicant_id')->constrained('internship_applicants')->restrictOnDelete();
        });
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internship_applicant_id')->constrained('internship_applicants')->restrictOnDelete();
        });
    }
}
