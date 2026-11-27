<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternPasswordResetRequest;
use App\Models\InternshipApplicant;
use App\Models\RegistrationToken;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternParticipantBulkActionsTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_admin_bulk_archive_is_atomic_preserves_relations_and_keeps_current_filters(): void
    {
        $first = $this->participant('completed');
        $second = $this->participant('completed');
        $admin = User::factory()->admin()->create();
        $this->createAttendanceTables();

        $attendanceRequestIds = [];
        $attendanceIds = [];
        $resetIds = [];
        $historyIds = [];

        foreach ([$first, $second] as $participant) {
            $attendanceRequestIds[] = DB::table('attendance_requests')->insertGetId([
                'internship_applicant_id' => $participant['applicant']->getKey(),
            ]);
            $attendanceIds[] = DB::table('attendances')->insertGetId([
                'internship_applicant_id' => $participant['applicant']->getKey(),
            ]);
            $resetIds[] = InternPasswordResetRequest::query()->create([
                'user_intern_id' => $participant['intern']->getKey(),
                'status' => 'pending',
            ])->getKey();

            if ($participant['applicant']->participant_status === 'completed') {
                $historyIds[] = $participant['applicant']->statusHistories()->create([
                    'old_status' => 'active',
                    'new_status' => 'completed',
                    'changed_by' => $admin->getKey(),
                    'changed_at' => $participant['applicant']->completed_at,
                ])->getKey();
            }
        }

        $snapshots = [];
        foreach ([$first, $second] as $participant) {
            $intern = $participant['intern']->fresh();
            $snapshots[$intern->getKey()] = [
                'auth_version' => (int) $intern->auth_version,
                'remember_token' => $intern->getRememberToken(),
                'participant_status' => $participant['applicant']->participant_status,
                'completed_at' => $participant['applicant']->completed_at?->toDateTimeString(),
                'token' => $participant['token']->only(['id', 'token_hash', 'token_encrypted', 'quota', 'used_count', 'status']),
                'claim' => [
                    'id' => $participant['claim']->getKey(),
                    'status' => $participant['claim']->status,
                    'completed_at' => $participant['claim']->completed_at?->toDateTimeString(),
                ],
            ];
        }

        $query = ['q' => 'Peserta', 'status' => 'completed', 'page' => 2];
        $ids = [$second['intern']->getKey(), $first['intern']->getKey()];

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.bulk-archive', $query), ['user_intern_ids' => $ids])
            ->assertRedirect(route('admin.intern-participants.index', $query))
            ->assertSessionHas('status', '2 peserta berhasil diarsipkan. Data profil dan riwayat tetap tersimpan.');

        foreach ([$first, $second] as $participant) {
            $archived = UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey());
            $this->assertNotNull($archived->deleted_at);
            $this->assertSame($snapshots[$archived->getKey()]['auth_version'] + 1, (int) $archived->auth_version);
            $this->assertNotSame($snapshots[$archived->getKey()]['remember_token'], $archived->getRememberToken());
            $this->assertSame($snapshots[$archived->getKey()]['participant_status'], $participant['applicant']->fresh()->participant_status);
            $this->assertSame($snapshots[$archived->getKey()]['completed_at'], $participant['applicant']->fresh()->completed_at?->toDateTimeString());
            $this->assertSame($snapshots[$archived->getKey()]['token'], $participant['token']->fresh()->only(array_keys($snapshots[$archived->getKey()]['token'])));
            $freshClaim = $participant['claim']->fresh();
            $this->assertSame($snapshots[$archived->getKey()]['claim']['id'], $freshClaim->getKey());
            $this->assertSame($snapshots[$archived->getKey()]['claim']['status'], $freshClaim->status);
            $this->assertSame($snapshots[$archived->getKey()]['claim']['completed_at'], $freshClaim->completed_at?->toDateTimeString());
            $this->assertDatabaseHas('internship_applicants', ['id' => $participant['applicant']->getKey()]);
        }

        foreach ($attendanceRequestIds as $id) {
            $this->assertDatabaseHas('attendance_requests', ['id' => $id]);
        }
        foreach ($attendanceIds as $id) {
            $this->assertDatabaseHas('attendances', ['id' => $id]);
        }
        foreach ($resetIds as $id) {
            $this->assertDatabaseHas('intern_password_reset_requests', ['id' => $id]);
        }
        foreach ($historyIds as $id) {
            $this->assertDatabaseHas('internship_participant_status_histories', ['id' => $id]);
        }

        $this->assertSame(2, SharedRegistrationToken::query()->count());
        $this->assertSame(2, SharedRegistrationClaim::query()->count());
        $this->get(route('admin.intern-participants.index', $query))->assertOk()->assertDontSee('Peserta 1');
    }

    public function test_bulk_archive_rolls_back_the_whole_batch_when_one_account_update_fails(): void
    {
        $first = $this->participant('completed');
        $second = $this->participant('completed');
        $admin = User::factory()->admin()->create();
        $before = collect([$first, $second])->mapWithKeys(fn (array $participant): array => [
            $participant['intern']->getKey() => [
                'auth_version' => (int) $participant['intern']->fresh()->auth_version,
                'remember_token' => $participant['intern']->fresh()->getRememberToken(),
            ],
        ])->all();

        Event::listen('eloquent.updating: '.UserIntern::class, function (UserIntern $intern) use ($second): void {
            if ($intern->is($second['intern'])) {
                throw new RuntimeException('Simulated bulk archive failure.');
            }
        });

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.bulk-archive'), [
                'user_intern_ids' => [$first['intern']->getKey(), $second['intern']->getKey()],
            ])
            ->assertRedirect(route('admin.intern-participants.index', ['page' => 1]))
            ->assertSessionHasErrors('user_intern_ids');

        foreach ([$first, $second] as $participant) {
            $active = UserIntern::query()->findOrFail($participant['intern']->getKey());
            $this->assertNull($active->deleted_at);
            $this->assertSame($before[$active->getKey()]['auth_version'], (int) $active->auth_version);
            $this->assertSame($before[$active->getKey()]['remember_token'], $active->getRememberToken());
        }
    }

    public function test_bulk_archive_rejects_archived_duplicate_missing_and_over_limit_ids_without_partial_changes(): void
    {
        $active = $this->participant('active');
        $archived = $this->participant('completed');
        $archived['intern']->delete();
        $admin = User::factory()->admin()->create();
        $activeVersion = (int) $active['intern']->fresh()->auth_version;

        $this->actingAs($admin)
            ->from(route('admin.intern-participants.index'))
            ->post(route('admin.intern-participants.bulk-archive'), [
                'user_intern_ids' => [$active['intern']->getKey(), $archived['intern']->getKey()],
            ])
            ->assertSessionHasErrors('user_intern_ids');
        $this->assertDatabaseHas('users_intern', ['id' => $active['intern']->getKey(), 'deleted_at' => null, 'auth_version' => $activeVersion]);

        $this->post(route('admin.intern-participants.bulk-archive'), [
            'user_intern_ids' => [$active['intern']->getKey(), $active['intern']->getKey()],
        ])->assertSessionHasErrors('user_intern_ids.1');
        $this->assertDatabaseHas('users_intern', ['id' => $active['intern']->getKey(), 'deleted_at' => null]);

        $this->post(route('admin.intern-participants.bulk-archive'), [
            'user_intern_ids' => [$active['intern']->getKey(), 999999],
        ])->assertSessionHasErrors('user_intern_ids.1');
        $this->assertDatabaseHas('users_intern', ['id' => $active['intern']->getKey(), 'deleted_at' => null]);

        $this->post(route('admin.intern-participants.bulk-archive'), [
            'user_intern_ids' => range(10001, 10101),
        ])->assertSessionHasErrors('user_intern_ids');
        $this->assertDatabaseHas('users_intern', ['id' => $active['intern']->getKey(), 'deleted_at' => null]);
    }

    public function test_admin_can_bulk_update_lifecycle_and_records_only_changed_statuses(): void
    {
        $active = $this->participant('active');
        $completed = $this->participant('completed');
        $unset = $this->participant(null);
        $admin = User::factory()->admin()->create();
        $completedHistoryCount = $completed['applicant']->statusHistories()->count();
        $internSnapshots = collect([$active, $completed, $unset])->mapWithKeys(fn (array $participant): array => [
            $participant['intern']->getKey() => [
                'auth_version' => (int) $participant['intern']->auth_version,
                'remember_token' => $participant['intern']->getRememberToken(),
                'token_hash' => $participant['token']->token_hash,
                'token_encrypted' => $participant['token']->token_encrypted,
                'used_count' => $participant['token']->used_count,
            ],
        ])->all();

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.bulk-lifecycle.update'), [
                'user_intern_ids' => [$active['intern']->getKey(), $completed['intern']->getKey(), $unset['intern']->getKey()],
                'participant_status' => 'completed',
            ])
            ->assertRedirect(route('admin.intern-participants.index', ['page' => 1]))
            ->assertSessionHas('status', '2 status peserta berhasil diperbarui.');

        foreach ([$active, $completed, $unset] as $participant) {
            $freshApplicant = $participant['applicant']->fresh();
            $this->assertSame('completed', $freshApplicant->participant_status);
            $this->assertNotNull($freshApplicant->completed_at);
            $freshIntern = $participant['intern']->fresh();
            $before = $internSnapshots[$freshIntern->getKey()];
            $this->assertSame($before['auth_version'], (int) $freshIntern->auth_version);
            $this->assertSame($before['remember_token'], $freshIntern->getRememberToken());
            $this->assertSame($before['token_hash'], $participant['token']->fresh()->token_hash);
            $this->assertSame($before['token_encrypted'], $participant['token']->fresh()->token_encrypted);
            $this->assertSame($before['used_count'], $participant['token']->fresh()->used_count);
        }

        $this->assertSame(1, $active['applicant']->statusHistories()->count());
        $this->assertSame($completedHistoryCount, $completed['applicant']->statusHistories()->count());
        $this->assertSame(1, $unset['applicant']->statusHistories()->count());
        $this->assertDatabaseHas('internship_participant_status_histories', [
            'applicant_id' => $active['applicant']->getKey(),
            'old_status' => 'active',
            'new_status' => 'completed',
            'changed_by' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('internship_participant_status_histories', [
            'applicant_id' => $unset['applicant']->getKey(),
            'old_status' => null,
            'new_status' => 'completed',
            'changed_by' => $admin->getKey(),
        ]);

        $this->post(route('admin.intern-participants.bulk-lifecycle.update'), [
            'user_intern_ids' => [$active['intern']->getKey()],
            'participant_status' => 'active',
        ])->assertSessionHas('status', '1 status peserta berhasil diperbarui.');
        $this->assertSame('active', $active['applicant']->fresh()->participant_status);
        $this->assertNull($active['applicant']->fresh()->completed_at);
        $this->assertSame('completed', $completed['applicant']->fresh()->participant_status);

        $refreshedList = $this->get(route('admin.intern-participants.index'))->assertOk();
        $this->assertStringContainsString('data-status-panel-toggle hidden', $refreshedList->getContent());
        $this->assertStringContainsString('data-status-panel hidden', $refreshedList->getContent());
        preg_match_all('/<input\b[^>]*\bdata-select-item\b[^>]*>/', $refreshedList->getContent(), $checkboxes);
        $this->assertNotEmpty($checkboxes[0]);
        foreach ($checkboxes[0] as $checkbox) {
            $this->assertStringNotContainsString(' checked', $checkbox);
        }
    }

    public function test_bulk_lifecycle_no_op_does_not_create_history(): void
    {
        $completed = $this->participant('completed');
        $admin = User::factory()->admin()->create();
        $completedAt = $completed['applicant']->completed_at->toDateTimeString();
        $historyCount = $completed['applicant']->statusHistories()->count();

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.bulk-lifecycle.update'), [
                'user_intern_ids' => [$completed['intern']->getKey()],
                'participant_status' => 'completed',
            ])
            ->assertSessionHas('status', 'Status peserta terpilih sudah sesuai. Tidak ada riwayat baru.');

        $this->assertSame($historyCount, $completed['applicant']->statusHistories()->count());
        $this->assertSame($completedAt, $completed['applicant']->fresh()->completed_at->toDateTimeString());
    }

    public function test_bulk_lifecycle_rejects_duplicate_missing_over_limit_ids_and_invalid_status(): void
    {
        $participant = $this->participant('active');
        $admin = User::factory()->admin()->create();
        $route = route('admin.intern-participants.bulk-lifecycle.update');
        $payload = [
            'user_intern_ids' => [$participant['intern']->getKey()],
            'participant_status' => 'completed',
        ];

        $this->actingAs($admin)
            ->from(route('admin.intern-participants.index'))
            ->post($route, [
                ...$payload,
                'user_intern_ids' => [$participant['intern']->getKey(), $participant['intern']->getKey()],
            ])
            ->assertSessionHasErrors('user_intern_ids.1');
        $this->assertSame('active', $participant['applicant']->fresh()->participant_status);
        $this->assertNull($participant['applicant']->fresh()->completed_at);
        $this->assertSame(0, $participant['applicant']->statusHistories()->count());

        $this->post($route, [
            ...$payload,
            'user_intern_ids' => [$participant['intern']->getKey(), 999999],
        ])->assertSessionHasErrors('user_intern_ids.1');
        $this->assertSame('active', $participant['applicant']->fresh()->participant_status);
        $this->assertNull($participant['applicant']->fresh()->completed_at);
        $this->assertSame(0, $participant['applicant']->statusHistories()->count());

        $this->post($route, [
            ...$payload,
            'user_intern_ids' => array_merge([$participant['intern']->getKey()], range(10000, 10099)),
        ])->assertSessionHasErrors('user_intern_ids');
        $this->assertSame('active', $participant['applicant']->fresh()->participant_status);
        $this->assertNull($participant['applicant']->fresh()->completed_at);
        $this->assertSame(0, $participant['applicant']->statusHistories()->count());

        $this->post($route, [
            ...$payload,
            'participant_status' => 'archived',
        ])->assertSessionHasErrors('participant_status');
        $this->assertSame('active', $participant['applicant']->fresh()->participant_status);
        $this->assertNull($participant['applicant']->fresh()->completed_at);
        $this->assertSame(0, $participant['applicant']->statusHistories()->count());
    }

    public function test_bulk_lifecycle_rolls_back_all_status_changes_when_a_later_update_fails(): void
    {
        $first = $this->participant('active');
        $second = $this->participant(null);
        $admin = User::factory()->admin()->create();
        $firstHistoryCount = $first['applicant']->statusHistories()->count();
        $secondHistoryCount = $second['applicant']->statusHistories()->count();
        $failureInjected = false;

        Event::listen('eloquent.updating: '.InternshipApplicant::class, function (InternshipApplicant $applicant) use ($second, &$failureInjected): void {
            if ($applicant->is($second['applicant'])) {
                $failureInjected = true;
                throw new RuntimeException('Simulated lifecycle update failure.');
            }
        });

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.bulk-lifecycle.update'), [
                'user_intern_ids' => [$first['intern']->getKey(), $second['intern']->getKey()],
                'participant_status' => 'completed',
            ])
            ->assertSessionHasErrors('user_intern_ids');

        $this->assertTrue($failureInjected);
        $this->assertSame('active', $first['applicant']->fresh()->participant_status);
        $this->assertNull($first['applicant']->fresh()->completed_at);
        $this->assertNull($second['applicant']->fresh()->participant_status);
        $this->assertNull($second['applicant']->fresh()->completed_at);
        $this->assertSame($firstHistoryCount, $first['applicant']->statusHistories()->count());
        $this->assertSame($secondHistoryCount, $second['applicant']->statusHistories()->count());
    }

    public function test_bulk_archive_rejects_active_or_unset_participants_even_when_request_is_forged(): void
    {
        $active = $this->participant('active');
        $unset = $this->participant(null);
        $completed = $this->participant('completed');
        $admin = User::factory()->admin()->create();
        $before = collect([$active, $unset, $completed])->mapWithKeys(fn (array $participant): array => [
            $participant['intern']->getKey() => [
                'auth_version' => (int) $participant['intern']->fresh()->auth_version,
                'remember_token' => $participant['intern']->fresh()->getRememberToken(),
            ],
        ])->all();

        foreach ([[$active, $completed], [$unset, $completed]] as $selection) {
            $this->actingAs($admin)
                ->post(route('admin.intern-participants.bulk-archive'), [
                    'user_intern_ids' => array_map(fn (array $participant): int => $participant['intern']->getKey(), $selection),
                ])
                ->assertSessionHasErrors('user_intern_ids');
        }

        foreach ([$active, $unset, $completed] as $participant) {
            $fresh = UserIntern::query()->findOrFail($participant['intern']->getKey());
            $this->assertNull($fresh->deleted_at);
            $this->assertSame($before[$fresh->getKey()]['auth_version'], (int) $fresh->auth_version);
            $this->assertSame($before[$fresh->getKey()]['remember_token'], $fresh->getRememberToken());
        }
    }

    public function test_admin_bulk_restore_preserves_account_token_claim_lifecycle_and_filters(): void
    {
        $first = $this->participant('active');
        $second = $this->participant('completed');
        $admin = User::factory()->admin()->create();
        $history = $second['applicant']->statusHistories()->create([
            'old_status' => 'active',
            'new_status' => 'completed',
            'changed_by' => $admin->getKey(),
            'changed_at' => $second['applicant']->completed_at,
        ]);
        $legacyToken = RegistrationToken::query()->create([
            'applicant_id' => $first['applicant']->getKey(),
            'token_hash' => hash('sha256', 'bulk-restore-legacy-token'),
            'status' => 'used',
            'expires_at' => now()->addDay(),
            'used_at' => now(),
        ]);
        $first['intern']->forceFill(['registration_token_id' => $legacyToken->getKey()])->save();

        $this->actingAs($admin)->delete(route('admin.intern-participants.archive', $first['intern']))->assertRedirect();
        $this->delete(route('admin.intern-participants.archive', $second['intern']))->assertRedirect();

        $snapshots = [];
        foreach ([$first, $second] as $participant) {
            $intern = UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey());
            $snapshots[$intern->getKey()] = [
                'auth_version' => (int) $intern->auth_version,
                'email' => $intern->email,
                'password' => $intern->password,
                'applicant_id' => $intern->applicant_id,
                'registration_token_id' => $intern->registration_token_id,
                'participant_status' => $participant['applicant']->fresh()->participant_status,
                'completed_at' => $participant['applicant']->fresh()->completed_at?->toDateTimeString(),
                'token_count' => RegistrationToken::query()->count(),
                'shared_token_count' => SharedRegistrationToken::query()->count(),
                'claim_count' => SharedRegistrationClaim::query()->count(),
            ];
        }

        $query = ['q' => 'Peserta', 'page' => 3];
        $this->post(route('admin.intern-participants.archived.bulk-restore', $query), [
            'user_intern_ids' => [$second['intern']->getKey(), $first['intern']->getKey()],
        ])->assertRedirect(route('admin.intern-participants.archived.index', $query))
            ->assertSessionHas('status', '2 peserta berhasil dipulihkan.');

        foreach ([$first, $second] as $participant) {
            $intern = UserIntern::query()->findOrFail($participant['intern']->getKey());
            $this->assertNull($intern->deleted_at);
            $this->assertSame($snapshots[$intern->getKey()]['auth_version'], (int) $intern->auth_version);
            $this->assertSame($snapshots[$intern->getKey()]['email'], $intern->email);
            $this->assertSame($snapshots[$intern->getKey()]['password'], $intern->password);
            $this->assertSame($snapshots[$intern->getKey()]['applicant_id'], $intern->applicant_id);
            $this->assertSame($snapshots[$intern->getKey()]['registration_token_id'], $intern->registration_token_id);
            $this->assertSame($snapshots[$intern->getKey()]['participant_status'], $participant['applicant']->fresh()->participant_status);
            $this->assertSame($snapshots[$intern->getKey()]['completed_at'], $participant['applicant']->fresh()->completed_at?->toDateTimeString());
            $this->assertSame($snapshots[$intern->getKey()]['token_count'], RegistrationToken::query()->count());
            $this->assertSame($snapshots[$intern->getKey()]['shared_token_count'], SharedRegistrationToken::query()->count());
            $this->assertSame($snapshots[$intern->getKey()]['claim_count'], SharedRegistrationClaim::query()->count());
        }

        $this->assertDatabaseHas('registration_tokens', ['id' => $legacyToken->getKey(), 'status' => 'used']);
        $this->assertDatabaseHas('internship_participant_status_histories', ['id' => $history->getKey()]);
    }

    public function test_bulk_restore_rejects_active_accounts_and_over_limit_without_restoring_anyone(): void
    {
        $archived = $this->participant('active');
        $active = $this->participant('completed');
        $archived['intern']->delete();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.intern-participants.archived.index'))
            ->post(route('admin.intern-participants.archived.bulk-restore'), [
                'user_intern_ids' => [$archived['intern']->getKey(), $active['intern']->getKey()],
            ])
            ->assertSessionHasErrors('user_intern_ids');
        $this->assertNotNull(UserIntern::onlyTrashed()->findOrFail($archived['intern']->getKey())->deleted_at);

        $this->post(route('admin.intern-participants.archived.bulk-restore'), [
            'user_intern_ids' => [$archived['intern']->getKey(), 999999],
        ])->assertSessionHasErrors('user_intern_ids.1');
        $this->assertNotNull(UserIntern::onlyTrashed()->findOrFail($archived['intern']->getKey())->deleted_at);

        $this->post(route('admin.intern-participants.archived.bulk-restore'), [
            'user_intern_ids' => [$archived['intern']->getKey(), $archived['intern']->getKey()],
        ])->assertSessionHasErrors('user_intern_ids.1');
        $this->assertNotNull(UserIntern::onlyTrashed()->findOrFail($archived['intern']->getKey())->deleted_at);

        $this->post(route('admin.intern-participants.archived.bulk-restore'), [
            'user_intern_ids' => range(20001, 20101),
        ])->assertSessionHasErrors('user_intern_ids');
        $this->assertNotNull(UserIntern::onlyTrashed()->findOrFail($archived['intern']->getKey())->deleted_at);
    }

    public function test_bulk_restore_rolls_back_the_whole_batch_when_one_account_restore_fails(): void
    {
        $first = $this->participant('active');
        $second = $this->participant('completed');
        $legacyTokens = [];

        foreach ([$first, $second] as $participant) {
            $token = RegistrationToken::query()->create([
                'applicant_id' => $participant['applicant']->getKey(),
                'token_hash' => hash('sha256', random_bytes(32)),
                'status' => 'used',
                'expires_at' => now()->addDay(),
                'used_at' => now(),
            ]);
            $participant['intern']->forceFill(['registration_token_id' => $token->getKey()])->save();
            $participant['intern']->delete();
            $legacyTokens[$participant['intern']->getKey()] = $token->getKey();
        }

        $before = collect([$first, $second])->mapWithKeys(fn (array $participant): array => [
            $participant['intern']->getKey() => [
                'auth_version' => (int) UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey())->auth_version,
                'deleted_at' => UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey())->deleted_at->toDateTimeString(),
                'applicant_id' => $participant['applicant']->getKey(),
                'registration_token_id' => $legacyTokens[$participant['intern']->getKey()],
            ],
        ])->all();

        $failureInjected = false;
        Event::listen('eloquent.updating: '.UserIntern::class, function (UserIntern $intern) use ($second, &$failureInjected): void {
            if ($intern->is($second['intern'])) {
                $failureInjected = true;
                throw new RuntimeException('Simulated bulk restore failure.');
            }
        });

        $admin = User::factory()->admin()->create();
        $query = ['q' => 'Peserta', 'page' => 1];

        $this->actingAs($admin)
            ->post(route('admin.intern-participants.archived.bulk-restore', $query), [
                'user_intern_ids' => [$first['intern']->getKey(), $second['intern']->getKey()],
            ])
            ->assertRedirect(route('admin.intern-participants.archived.index', $query))
            ->assertSessionHasErrors('user_intern_ids');

        $this->assertTrue($failureInjected, 'Failure injection must be triggered while restoring the second participant.');

        foreach ([$first, $second] as $participant) {
            $archived = UserIntern::onlyTrashed()->findOrFail($participant['intern']->getKey());
            $snapshot = $before[$archived->getKey()];

            $this->assertSame($snapshot['auth_version'], (int) $archived->auth_version);
            $this->assertSame($snapshot['deleted_at'], $archived->deleted_at->toDateTimeString());
            $this->assertSame($snapshot['applicant_id'], $archived->applicant_id);
            $this->assertSame($snapshot['registration_token_id'], $archived->registration_token_id);
            $this->assertDatabaseHas('registration_tokens', ['id' => $snapshot['registration_token_id']]);
        }

        $this->get(route('admin.intern-participants.archived.index', $query))
            ->assertOk()
            ->assertSee($first['intern']->name)
            ->assertSee($second['intern']->name);
    }

    public function test_operator_dan_intern_tidak_dapat_menggunakan_bulk_archive_atau_restore(): void
    {
        $participant = $this->participant('active');
        $participant['intern']->delete();
        $operator = User::factory()->operator($participant['institution'])->create();

        $this->actingAs($operator)
            ->post(route('admin.intern-participants.bulk-archive'), ['user_intern_ids' => [$participant['intern']->getKey()]])
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.intern-participants.bulk-lifecycle.update'), [
            'user_intern_ids' => [$participant['intern']->getKey()],
            'participant_status' => 'completed',
        ])->assertRedirect(route('admin.login'));
        $this->post(route('admin.intern-participants.archived.bulk-restore'), ['user_intern_ids' => [$participant['intern']->getKey()]])
            ->assertRedirect(route('admin.login'));

        Auth::guard('admin')->logout();
        Auth::guard('operator')->logout();
        Auth::guard('intern')->logout();

        $this->actingAs($participant['intern'])
            ->post(route('admin.intern-participants.bulk-archive'), ['user_intern_ids' => [$participant['intern']->getKey()]])
            ->assertRedirect(route('admin.login'));
        $this->post(route('admin.intern-participants.bulk-lifecycle.update'), [
            'user_intern_ids' => [$participant['intern']->getKey()],
            'participant_status' => 'completed',
        ])->assertRedirect(route('admin.login'));
        $this->post(route('admin.intern-participants.archived.bulk-restore'), ['user_intern_ids' => [$participant['intern']->getKey()]])
            ->assertRedirect(route('admin.login'));
    }

    public function test_bulk_select_only_contains_rows_from_current_paginated_filtered_page(): void
    {
        $ids = [];
        for ($index = 0; $index < 16; $index++) {
            $ids[] = $this->participant('completed')['intern']->getKey();
        }

        $firstPage = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.intern-participants.index', ['q' => 'Peserta', 'status' => 'completed', 'page' => 1]))
            ->assertOk();
        $this->assertSame(10, substr_count($firstPage->getContent(), 'name="user_intern_ids[]"'));
        $this->assertStringContainsString('id="bulk-select-all"', $firstPage->getContent());
        $this->assertStringContainsString('id="bulk-selection-count"', $firstPage->getContent());
        $this->assertStringContainsString('data-status-panel-toggle hidden', $firstPage->getContent());
        $this->assertStringContainsString('data-status-panel hidden', $firstPage->getContent());
        $this->assertStringContainsString('data-archive-button hidden disabled', $firstPage->getContent());
        $this->assertStringContainsString('data-bulk-submit="lifecycle" disabled', $firstPage->getContent());
        $this->assertStringContainsString('<option value="active">Aktif</option>', $firstPage->getContent());
        $this->assertStringContainsString('<option value="completed">Selesai</option>', $firstPage->getContent());
        $this->assertStringNotContainsString('<th scope="col">Nomor</th>', $firstPage->getContent());
        $this->assertStringContainsString('data-participant-status="completed"', $firstPage->getContent());
        $this->assertStringContainsString('page=1', html_entity_decode($firstPage->getContent()));

        $secondPage = $this->get(route('admin.intern-participants.index', ['q' => 'Peserta', 'status' => 'completed', 'page' => 2]))
            ->assertOk();
        $this->assertSame(6, substr_count($secondPage->getContent(), 'name="user_intern_ids[]"'));
        $this->assertStringContainsString('data-select-item', $secondPage->getContent());
        $this->assertStringContainsString('page=2', html_entity_decode($secondPage->getContent()));

        $this->post(route('admin.intern-participants.bulk-archive'), ['user_intern_ids' => $ids])->assertRedirect();
        $archivedPage = $this->get(route('admin.intern-participants.archived.index', ['q' => 'Peserta', 'page' => 1]))->assertOk();
        $this->assertSame(10, substr_count($archivedPage->getContent(), 'name="user_intern_ids[]"'));
        $this->assertStringContainsString('id="bulk-select-all"', $archivedPage->getContent());
        $this->assertStringContainsString('bulk-restore', html_entity_decode($archivedPage->getContent()));
        $this->assertStringContainsString('data-bulk-submit disabled', $archivedPage->getContent());
    }

    private function participant(?string $status): array
    {
        $institution = Institution::factory()->create(['name' => 'Institusi Bulk']);
        $operator = User::factory()->operator($institution)->create();
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->getKey(),
            'title' => 'Pengajuan Bulk',
            'description' => 'Uji proses batch.',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
        ]);
        $sequence = UserIntern::withTrashed()->count() + 1;
        $applicant = $request->applicants()->create([
            'name' => 'Peserta '.$sequence,
            'email' => 'bulk-'.$sequence.'@example.test',
            'phone' => '081234567890',
            'student_number' => 'NIM-'.$sequence,
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
            'name' => $applicant->name,
            'email' => 'bulk-intern-'.$sequence.'@example.test',
            'password' => 'original-password',
            'phone' => '081234567890',
        ]);
        $token = SharedRegistrationToken::query()->create([
            'internship_request_id' => $request->getKey(),
            'token_hash' => hash('sha256', random_bytes(32)),
            'token_encrypted' => encrypt(Str::random(32)),
            'quota' => 5,
            'used_count' => 1,
            'status' => 'active',
        ]);
        $claim = SharedRegistrationClaim::query()->create([
            'shared_registration_token_id' => $token->getKey(),
            'user_intern_id' => $intern->getKey(),
            'internship_applicant_id' => $applicant->getKey(),
            'status' => 'completed',
            'claimed_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);

        return compact('intern', 'applicant', 'request', 'institution', 'token', 'claim');
    }

    private function createAttendanceTables(): void
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
