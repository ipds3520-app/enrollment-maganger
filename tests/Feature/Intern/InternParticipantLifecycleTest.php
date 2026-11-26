<?php

namespace Tests\Feature\Intern;

use App\Models\Institution;
use App\Models\InternPasswordResetRequest;
use App\Models\InternshipApplicant;
use App\Models\InternshipParticipantStatusHistory;
use App\Models\InternshipRequest;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternParticipantLifecycleTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_sqlite_migration_adds_lifecycle_archive_and_history_schema(): void
    {
        $this->assertTrue(Schema::hasColumn('users_intern', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('internship_applicants', 'participant_status'));
        $this->assertTrue(Schema::hasColumn('internship_applicants', 'completed_at'));
        $this->assertTrue(Schema::hasTable('internship_participant_status_histories'));
        $this->assertTrue(Schema::hasColumn('internship_participant_status_histories', 'changed_by'));
    }

    public function test_database_accepts_only_valid_participant_status_and_completed_at_combinations(): void
    {
        [, $applicant] = $this->createParticipant();

        $completedAt = now()->subDay()->startOfSecond()->toDateTimeString();
        $validCombinations = [
            [null, null],
            ['active', null],
            ['completed', $completedAt],
        ];

        foreach ($validCombinations as [$status, $finishedAt]) {
            $this->assertSame(1, $applicant->newQuery()->whereKey($applicant->getKey())->update([
                'participant_status' => $status,
                'completed_at' => $finishedAt,
            ]));
            $this->assertDatabaseHas('internship_applicants', [
                'id' => $applicant->getKey(),
                'participant_status' => $status,
                'completed_at' => $finishedAt,
            ]);
        }

        $invalidCombinations = [
            [null, $completedAt],
            ['active', $completedAt],
            ['completed', null],
            ['paused', null],
            ['unknown', $completedAt],
        ];

        foreach ($invalidCombinations as [$status, $finishedAt]) {
            try {
                $applicant->newQuery()->whereKey($applicant->getKey())->update([
                    'participant_status' => $status,
                    'completed_at' => $finishedAt,
                ]);
                $this->fail(sprintf(
                    'The combination participant_status=%s and completed_at=%s should be rejected.',
                    var_export($status, true),
                    var_export($finishedAt, true),
                ));
            } catch (QueryException) {
                $this->assertDatabaseHas('internship_applicants', [
                    'id' => $applicant->getKey(),
                    'participant_status' => 'completed',
                    'completed_at' => $completedAt,
                ]);
            }
        }
    }

    public function test_status_history_relations_and_constraints_work(): void
    {
        [$intern, $applicant] = $this->createParticipant();
        $admin = User::factory()->admin()->create();

        $history = $applicant->statusHistories()->create([
            'old_status' => null,
            'new_status' => 'active',
            'changed_by' => $admin->getKey(),
            'changed_at' => now(),
        ]);

        $this->assertSame($applicant->getKey(), $history->applicant->getKey());
        $this->assertSame($admin->getKey(), $history->changedBy->getKey());
        $this->assertSame($history->getKey(), $intern->applicant->statusHistories()->sole()->getKey());

        try {
            InternshipParticipantStatusHistory::query()->create([
                'applicant_id' => $applicant->getKey(),
                'old_status' => 'paused',
                'new_status' => 'invalid',
                'changed_at' => now(),
            ]);
            $this->fail('Invalid status history values should be rejected.');
        } catch (QueryException) {
            $this->assertDatabaseCount('internship_participant_status_histories', 1);
        }
    }

    public function test_soft_deleting_intern_preserves_profile_claim_tokens_and_reset_history(): void
    {
        [$intern, $applicant, $request, $token, $claim] = $this->createParticipant('completed');
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $applicant->forceFill([
            'participant_status' => 'active',
            'completed_at' => null,
        ])->save();
        $applicant->statusHistories()->create([
            'old_status' => null,
            'new_status' => 'active',
            'changed_by' => $admin->getKey(),
            'changed_at' => now()->subDay(),
        ]);
        $completedAt = now()->startOfSecond();
        $applicant->forceFill([
            'participant_status' => 'completed',
            'completed_at' => $completedAt,
        ])->save();
        $applicant->statusHistories()->create([
            'old_status' => 'active',
            'new_status' => 'completed',
            'changed_by' => $admin->getKey(),
            'changed_at' => $completedAt,
        ]);

        $resetToken = 'archived-account-reset-token';
        $resetHistory = InternPasswordResetRequest::query()->create([
            'user_intern_id' => $intern->getKey(),
            'status' => 'approved',
            'processed_by' => $admin->getKey(),
            'processed_at' => now(),
            'token_hash' => hash('sha256', $resetToken),
            'token_expires_at' => now()->addMinutes(30),
        ]);
        $tokenSnapshot = $token->only(['id', 'token_hash', 'quota', 'used_count', 'status']);
        $claimSnapshot = [
            'id' => $claim->getKey(),
            'status' => $claim->status,
            'completed_at' => $claim->completed_at->toDateTimeString(),
        ];

        $intern->delete();

        $this->assertNull(UserIntern::query()->find($intern->getKey()));
        $this->assertTrue(UserIntern::onlyTrashed()->findOrFail($intern->getKey())->trashed());
        $this->assertDatabaseHas('internship_applicants', [
            'id' => $applicant->getKey(),
            'internship_request_id' => $request->getKey(),
        ]);
        $this->assertSame($tokenSnapshot, $token->fresh()->only(array_keys($tokenSnapshot)));
        $freshClaim = $claim->fresh();
        $this->assertSame($claimSnapshot['id'], $freshClaim->getKey());
        $this->assertSame($claimSnapshot['status'], $freshClaim->status);
        $this->assertSame($claimSnapshot['completed_at'], $freshClaim->completed_at->toDateTimeString());
        $this->assertDatabaseHas('intern_password_reset_requests', ['id' => $resetHistory->getKey()]);
        $this->assertSame(2, $applicant->statusHistories()->count());
        $this->assertSame('completed', $applicant->fresh()->participant_status);
        $this->assertSame($completedAt->toDateTimeString(), $applicant->fresh()->completed_at->toDateTimeString());

        Auth::guard('intern')->logout();
        $this->post(route('intern.login.store'), [
            'email' => $intern->email,
            'password' => 'participant-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest('intern');

        $this->post(route('intern.password.email'), ['email' => $intern->email])
            ->assertSessionHas('status');
        $this->assertDatabaseCount('intern_password_reset_requests', 1);

        $this->post(route('intern.password.update'), [
            'token' => $resetToken,
            'password' => 'new-password-for-archived',
            'password_confirmation' => 'new-password-for-archived',
        ])->assertSessionHasErrors('token');
        $this->assertSame('approved', $resetHistory->fresh()->status);
        $this->assertNull($resetHistory->fresh()->token_used_at);
        $this->assertTrue(Hash::check('participant-password', $intern->fresh()->password));
    }

    /** @return array{UserIntern, InternshipApplicant, InternshipRequest, SharedRegistrationToken, SharedRegistrationClaim} */
    private function createParticipant(string $claimStatus = 'pending_profile'): array
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $admin = User::factory()->admin()->create();
        $request = InternshipRequest::query()->create([
            'institution_id' => $institution->getKey(),
            'created_by' => $operator->getKey(),
            'title' => 'Periode Uji Lifecycle',
            'start_date' => '2027-02-01',
            'end_date' => '2027-04-01',
            'participant_count' => 2,
            'status' => 'approved',
            'processed_by' => $admin->getKey(),
            'decided_at' => now(),
        ]);
        $token = SharedRegistrationToken::forceCreate([
            'internship_request_id' => $request->getKey(),
            'token_hash' => hash('sha256', 'lifecycle-token-'.$request->getKey()),
            'quota' => 2,
            'used_count' => $claimStatus === 'completed' ? 1 : 0,
            'status' => 'active',
        ]);
        $applicant = $request->applicants()->create([
            'name' => 'Peserta Lifecycle',
            'email' => 'lifecycle@example.test',
            'student_number' => 'LIFECYCLE-01',
        ]);
        $intern = UserIntern::query()->create([
            'applicant_id' => $applicant->getKey(),
            'registration_token_id' => null,
            'name' => $applicant->name,
            'email' => $applicant->email,
            'password' => Hash::make('participant-password'),
        ]);
        $claim = SharedRegistrationClaim::query()->create([
            'shared_registration_token_id' => $token->getKey(),
            'user_intern_id' => $intern->getKey(),
            'internship_applicant_id' => $applicant->getKey(),
            'status' => $claimStatus,
            'claimed_at' => now(),
            'completed_at' => $claimStatus === 'completed' ? now() : null,
        ]);

        return [$intern, $applicant, $request, $token, $claim];
    }
}
