<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternPasswordResetRequest;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\SharedRegistrationClaim;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use App\Models\UserIntern;
use Illuminate\Support\Facades\Auth;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternParticipantManagementTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    public function test_admin_sees_account_holders_with_profile_and_lifecycle_labels_but_not_incomplete_or_archived_accounts(): void
    {
        $active = $this->participant('Peserta Aktif', 'SMA Aktif', 'active');
        $completed = $this->participant('Peserta Selesai', 'SMA Selesai', 'completed');
        $unset = $this->participant('Peserta Belum Ditetapkan', 'SMA Belum', null);
        $archived = $this->participant('Peserta Arsip', 'SMA Arsip', 'active');
        $archived['intern']->delete();

        $application = $this->internshipRequest('SMA Tanpa Akun');
        $application->applicants()->create([
            'name' => 'Calon Tanpa Akun',
            'email' => 'calon@example.test',
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.intern-participants.index'))
            ->assertOk()
            ->assertSee('Kelola Peserta Magang')
            ->assertSee('Peserta Aktif')
            ->assertSee('SMA Aktif')
            ->assertSee('Aktif')
            ->assertSee('Peserta Selesai')
            ->assertSee('Selesai')
            ->assertSee('Peserta Belum Ditetapkan')
            ->assertSee('Belum Ditetapkan')
            ->assertDontSee('Peserta Arsip')
            ->assertDontSee('Calon Tanpa Akun');

        $participants = $response->viewData('participants');
        $this->assertCount(3, $participants->items());
        $this->assertNull($unset['applicant']->fresh()->participant_status);
        $this->assertSame('completed', $completed['applicant']->fresh()->participant_status);
        $this->assertSame('active', $active['applicant']->fresh()->participant_status);
    }

    public function test_admin_searches_by_name_student_number_email_and_institution(): void
    {
        $target = $this->participant('Nadia Search', 'Institusi Sasaran');
        $target['applicant']->student_number = 'NIM-SEARCH-204';
        $target['applicant']->save();
        $this->participant('Peserta Pembanding', 'Institusi Pembanding');
        $admin = User::factory()->admin()->create();

        foreach (['Nadia Search', 'NIM-SEARCH-204', 'intern-1@example.test', 'Institusi Sasaran'] as $keyword) {
            $this->actingAs($admin)
                ->get(route('admin.intern-participants.index', ['q' => $keyword]))
                ->assertOk()
                ->assertSee('Nadia Search')
                ->assertDontSee('Peserta Pembanding');
        }
    }

    public function test_admin_filters_each_lifecycle_status_and_combines_search_with_filter(): void
    {
        $active = $this->participant('Filter Alpha Aktif', 'Institusi A', 'active');
        $completed = $this->participant('Filter Alpha Selesai', 'Institusi B', 'completed');
        $unset = $this->participant('Filter Alpha Kosong', 'Institusi C', null);
        $this->participant('Filter Beta Aktif', 'Institusi D', 'active');
        $admin = User::factory()->admin()->create();

        foreach ([
            ['active', 'Filter Alpha Aktif'],
            ['completed', 'Filter Alpha Selesai'],
            ['unset', 'Filter Alpha Kosong'],
        ] as [$filter, $expectedName]) {
            $response = $this->actingAs($admin)
                ->get(route('admin.intern-participants.index', ['q' => 'Filter Alpha', 'status' => $filter]))
                ->assertOk()
                ->assertSee($expectedName)
                ->assertDontSee('Filter Beta Aktif');

            $this->assertCount(1, $response->viewData('participants')->items());
        }

        $this->assertSame('active', $active['applicant']->fresh()->participant_status);
        $this->assertSame('completed', $completed['applicant']->fresh()->participant_status);
        $this->assertNull($unset['applicant']->fresh()->participant_status);
    }

    public function test_pagination_preserves_search_and_status_parameters(): void
    {
        for ($index = 1; $index <= 16; $index++) {
            $this->participant(sprintf('Pencarian Halaman %02d', $index), 'Institusi Halaman', 'active');
        }

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.intern-participants.index', ['q' => 'Pencarian Halaman', 'status' => 'active', 'page' => 2]))
            ->assertOk();

        $this->assertSame(
            route('admin.intern-participants.index', ['q' => 'Pencarian Halaman', 'status' => 'active', 'page' => 1]),
            $response->viewData('participants')->url(1),
        );
        $this->assertSame(16, $response->viewData('participants')->total());
        $this->assertCount(1, $response->viewData('participants')->items());
    }

    public function test_admin_can_see_participant_detail_and_edit_form_with_read_only_identity_fields(): void
    {
        $participant = $this->participant('Detail Peserta', 'SMA Detail', 'completed');
        $participant['applicant']->forceFill(['completed_at' => now()->subDay()])->save();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.intern-participants.show', $participant['intern']))
            ->assertOk()
            ->assertSee('Detail Peserta')
            ->assertSee('SMA Detail')
            ->assertSee('Selesai')
            ->assertSee('Tanggal penyelesaian');

        $this->get(route('admin.intern-participants.edit', $participant['intern']))
            ->assertOk()
            ->assertSee('Email (tidak dapat diubah)')
            ->assertSee('Asal institusi (tidak dapat diubah)')
            ->assertSee('Status lifecycle (tidak dapat diubah pada tahap ini)');
    }

    public function test_admin_edits_allowed_fields_and_synchronizes_name_and_phone_without_changing_protected_data(): void
    {
        $participant = $this->participant('Nama Awal', 'SMA Edit', 'completed');
        $participant['applicant']->forceFill([
            'completed_at' => now()->subDays(3),
            'nik' => '1234567890123456',
        ])->save();
        $intern = $participant['intern'];
        $applicant = $participant['applicant'];
        $admin = User::factory()->admin()->create();

        $token = SharedRegistrationToken::query()->create([
            'internship_request_id' => $participant['request']->getKey(),
            'token_hash' => hash('sha256', 'keep-shared-token'),
            'token_encrypted' => encrypt('keep-shared-token'),
            'quota' => 3,
            'used_count' => 1,
            'status' => 'active',
        ]);
        $claim = SharedRegistrationClaim::query()->create([
            'shared_registration_token_id' => $token->getKey(),
            'user_intern_id' => $intern->getKey(),
            'internship_applicant_id' => $applicant->getKey(),
            'status' => 'completed',
            'claimed_at' => now()->subDays(10),
            'completed_at' => now()->subDays(9),
        ]);
        $resetHistory = InternPasswordResetRequest::query()->create([
            'user_intern_id' => $intern->getKey(),
            'status' => 'rejected',
            'processed_by' => $admin->getKey(),
            'processed_at' => now()->subDays(4),
        ]);

        $snapshot = [
            'intern_id' => $intern->getKey(),
            'applicant_id' => $applicant->getKey(),
            'request_id' => $participant['request']->getKey(),
            'email' => $intern->email,
            'password' => $intern->password,
            'nik' => $applicant->nik,
            'school_name' => $applicant->school_name,
            'status' => $applicant->participant_status,
            'completed_at' => $applicant->completed_at->toDateTimeString(),
            'token' => $token->only(['id', 'token_hash', 'token_encrypted', 'quota', 'used_count', 'status']),
            'claim' => [
                'id' => $claim->getKey(),
                'status' => $claim->status,
                'completed_at' => $claim->completed_at->toDateTimeString(),
            ],
        ];

        $this->actingAs($admin)
            ->put(route('admin.intern-participants.update', $intern), [
                'name' => 'Nama Baru',
                'student_number' => 'NIM-UPDATED',
                'school_major' => 'Sistem Informasi',
                'phone' => '081299990000',
                'address' => 'Alamat baru',
                'birth_date' => '2004-05-06',
                'gender' => 'Perempuan',
            ])
            ->assertRedirect(route('admin.intern-participants.show', $intern));

        $intern->refresh();
        $applicant->refresh();
        $this->assertSame('Nama Baru', $intern->name);
        $this->assertSame('Nama Baru', $applicant->name);
        $this->assertSame('081299990000', $intern->phone);
        $this->assertSame('081299990000', $applicant->phone);
        $this->assertSame('NIM-UPDATED', $applicant->student_number);
        $this->assertSame('Sistem Informasi', $applicant->school_major);
        $this->assertSame('Alamat baru', $applicant->address);
        $this->assertSame('2004-05-06', $applicant->birth_date->format('Y-m-d'));
        $this->assertSame('Perempuan', $applicant->gender);
        $this->assertSame($snapshot['intern_id'], $intern->getKey());
        $this->assertSame($snapshot['applicant_id'], $intern->applicant_id);
        $this->assertSame($snapshot['request_id'], $applicant->internship_request_id);
        $this->assertSame($snapshot['email'], $intern->email);
        $this->assertSame($snapshot['password'], $intern->password);
        $this->assertSame($snapshot['nik'], $applicant->nik);
        $this->assertSame($snapshot['school_name'], $applicant->school_name);
        $this->assertSame($snapshot['status'], $applicant->participant_status);
        $this->assertSame($snapshot['completed_at'], $applicant->completed_at->toDateTimeString());
        $this->assertSame($snapshot['token'], $token->fresh()->only(array_keys($snapshot['token'])));
        $freshClaim = $claim->fresh();
        $this->assertSame($snapshot['claim']['id'], $freshClaim->getKey());
        $this->assertSame($snapshot['claim']['status'], $freshClaim->status);
        $this->assertSame($snapshot['claim']['completed_at'], $freshClaim->completed_at->toDateTimeString());
        $this->assertDatabaseHas('intern_password_reset_requests', ['id' => $resetHistory->getKey()]);
    }

    public function test_email_password_and_lifecycle_fields_cannot_be_changed_by_manipulating_the_request(): void
    {
        $participant = $this->participant('Protected Participant', 'SMA Protected', 'active');
        $intern = $participant['intern'];
        $applicant = $participant['applicant'];
        $password = $intern->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.intern-participants.update', $intern), [
                'name' => 'Nama Manipulasi',
                'student_number' => $applicant->student_number,
                'school_major' => $applicant->school_major,
                'phone' => $intern->phone,
                'address' => $applicant->address,
                'birth_date' => null,
                'gender' => null,
                'email' => 'attacker@example.test',
                'nik' => '9999999999999999',
                'school_name' => 'Institusi Palsu',
                'password' => 'password-baru',
                'applicant_id' => 999999,
                'internship_request_id' => 999999,
                'institution_id' => 999999,
                'participant_status' => 'completed',
                'completed_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors(['email', 'nik', 'school_name', 'password', 'applicant_id', 'internship_request_id', 'institution_id', 'participant_status', 'completed_at']);

        $this->assertSame('Protected Participant', $intern->fresh()->name);
        $this->assertSame($password, $intern->fresh()->password);
        $this->assertSame('Protected Participant', $applicant->fresh()->name);
        $this->assertSame('profile-1@example.test', $applicant->fresh()->email);
        $this->assertSame('active', $applicant->fresh()->participant_status);
        $this->assertNull($applicant->fresh()->completed_at);
    }

    public function test_nis_nim_must_be_unique_within_the_same_internship_request_but_current_value_is_allowed(): void
    {
        $first = $this->participant('Peserta Pertama', 'SMA Sama', 'active');
        $second = $this->participant('Peserta Kedua', 'SMA Sama', 'active', $first['request']);
        $admin = User::factory()->admin()->create();

        $sameNumber = $this->validProfile($first['intern'], $first['applicant']);
        $this->actingAs($admin)
            ->put(route('admin.intern-participants.update', $first['intern']), $sameNumber)
            ->assertRedirect(route('admin.intern-participants.show', $first['intern']));

        $duplicateNumber = $this->validProfile($second['intern'], $second['applicant']);
        $duplicateNumber['student_number'] = $first['applicant']->student_number;
        $this->put(route('admin.intern-participants.update', $second['intern']), $duplicateNumber)
            ->assertSessionHasErrors('student_number');

        $this->assertSame('STU-1', $first['applicant']->fresh()->student_number);
        $this->assertSame('STU-2', $second['applicant']->fresh()->student_number);
    }

    public function test_only_admin_can_access_participant_management_routes(): void
    {
        $participant = $this->participant('Akses Terbatas', 'SMA Akses');
        $operator = User::factory()->operator($participant['institution'])->create();

        $this->actingAs($operator)
            ->get(route('admin.intern-participants.index'))
            ->assertForbidden();

        $intern = UserIntern::query()->findOrFail($participant['intern']->getKey());
        $this->actingAs($intern)
            ->get(route('admin.intern-participants.show', $intern))
            ->assertForbidden();

        Auth::guard('admin')->logout();
        Auth::guard('operator')->logout();
        Auth::guard('intern')->logout();
        $this->get(route('admin.intern-participants.index'))
            ->assertRedirect();
    }

    /** @return array{UserIntern, InternshipApplicant, InternshipRequest, Institution} */
    private function participant(string $name, string $institutionName, ?string $status = null, ?InternshipRequest $request = null): array
    {
        $institution = $request?->institution ?? Institution::factory()->create(['name' => $institutionName]);
        $request ??= $this->internshipRequest($institutionName, $institution);
        $index = UserIntern::withTrashed()->count() + 1;
        $applicant = $request->applicants()->create([
            'name' => $name,
            'email' => 'profile-'.$index.'@example.test',
            'phone' => '0812000000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'student_number' => 'STU-'.$index,
            'school_major' => 'Rekayasa Perangkat Lunak',
            'school_name' => $institution->name,
        ]);
        $applicant->forceFill([
            'participant_status' => $status,
            'completed_at' => $status === 'completed' ? now()->subDay() : null,
        ])->save();
        $intern = UserIntern::query()->create([
            'applicant_id' => $applicant->getKey(),
            'registration_token_id' => null,
            'name' => $name,
            'email' => 'intern-'.$index.'@example.test',
            'password' => 'participant-password',
            'phone' => $applicant->phone,
        ]);

        return ['intern' => $intern, 'applicant' => $applicant, 'request' => $request, 'institution' => $institution];
    }

    private function internshipRequest(string $institutionName, ?Institution $institution = null): InternshipRequest
    {
        $institution ??= Institution::factory()->create(['name' => $institutionName]);
        $operator = User::factory()->operator($institution)->create();

        return $institution->internshipRequests()->create([
            'created_by' => $operator->getKey(),
            'title' => 'Program Magang '.$institutionName,
            'description' => 'Permohonan pengujian.',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
        ]);
    }

    /** @return array<string, mixed> */
    private function validProfile(UserIntern $intern, InternshipApplicant $applicant): array
    {
        return [
            'name' => $intern->name,
            'student_number' => $applicant->student_number,
            'school_major' => $applicant->school_major,
            'phone' => $intern->phone,
            'address' => $applicant->address,
            'birth_date' => null,
            'gender' => null,
        ];
    }
}
