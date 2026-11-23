<?php

namespace Tests\Feature\Operator;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternshipRequestTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
        Storage::fake('local');
    }

    /** @return array{User, Institution} */
    private function operator(string $institutionName = 'SMK Contoh'): array
    {
        $institution = Institution::factory()->create(['name' => $institutionName]);
        $operator = User::factory()->operator($institution)->create();

        return [$operator, $institution];
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Permohonan Magang Kelas XII',
            'description' => 'Permohonan praktik kerja lapangan.',
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
            'participant_count' => 1,
            'proposal_url' => 'https://drive.google.com/file/d/drive-file-id/view?usp=sharing',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function applicant(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Calon Peserta',
            'email' => 'calon@example.test',
            'phone' => '081234567890',
            'student_number' => 'SISWA-001',
            'school_major' => 'Rekayasa Perangkat Lunak',
        ], $overrides);
    }

    private function existingRequest(User $operator, Institution $institution, string $title): InternshipRequest
    {
        return $institution->internshipRequests()->create([
            'created_by' => $operator->id,
            'title' => $title,
            'description' => null,
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);
    }

    public function test_operator_dapat_membuat_pengajuan_tanpa_membuat_record_peserta(): void
    {
        [$operator, $institution] = $this->operator();

        $response = $this->actingAs($operator)->post(
            route('operator.internship-requests.store'),
            $this->payload(),
        );

        $request = InternshipRequest::with('applicants')->firstOrFail();

        $response->assertRedirect(route('operator.internship-requests.show', $request));
        $this->assertSame($institution->id, $request->institution_id);
        $this->assertSame($operator->id, $request->created_by);
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->processed_by);
        $this->assertNull($request->decided_at);
        $this->assertSame(1, $request->participant_count);
        $this->assertCount(0, $request->applicants);
        $this->assertSame('https://drive.google.com/file/d/drive-file-id/view?usp=sharing', $request->proposal_path);
        $this->assertSame([], Storage::disk('local')->allFiles('proposals'));
    }

    public function test_operator_dapat_mengajukan_beberapa_slot_peserta_dengan_satu_jumlah(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)->post(
            route('operator.internship-requests.store'),
            $this->payload(['participant_count' => 4]),
        )->assertRedirect();

        $this->assertDatabaseCount('internship_requests', 1);
        $this->assertDatabaseHas('internship_requests', ['participant_count' => 4]);
        $this->assertDatabaseCount('internship_applicants', 0);
    }

    public function test_field_kepemilikan_dan_keputusan_dari_input_ditolak(): void
    {
        [$operator, $otherInstitution] = $this->operator();
        $admin = User::factory()->admin()->create();

        $payload = $this->payload([
            'institution_id' => $otherInstitution->id + 100,
            'created_by' => $admin->id,
            'status' => 'approved',
            'processed_by' => $admin->id,
            'decided_at' => '2026-01-01 10:00:00',
            'reply_letter_path' => 'surat.pdf',
            'proposal_path' => 'proposals/1/fake.pdf',
            'letter_number' => '123/ADM',
            'admin_notes' => 'Disetujui',
        ]);

        $response = $this->actingAs($operator)->from('/operator/internship-requests/create')->post(
            route('operator.internship-requests.store'),
            $payload,
        );

        $response->assertSessionHasErrors([
            'institution_id',
            'created_by',
            'status',
            'processed_by',
            'decided_at',
            'reply_letter_path',
            'proposal_path',
            'letter_number',
            'admin_notes',
        ]);
        $this->assertDatabaseCount('internship_requests', 0);
        $this->assertDatabaseCount('internship_applicants', 0);
    }

    public function test_link_proposal_wajib_dan_hanya_menerima_https_google_drive(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload(['proposal_url' => null]))
            ->assertSessionHasErrors('proposal_url');

        $this->actingAs($operator)->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload([
                'proposal_url' => 'https://example.com/proposal.pdf',
            ]))->assertSessionHasErrors('proposal_url');

        $this->actingAs($operator)->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload([
                'proposal_url' => 'http://drive.google.com/file/d/file-id/view',
            ]))->assertSessionHasErrors('proposal_url');

        $this->assertDatabaseCount('internship_requests', 0);
    }

    public function test_upload_file_proposal_tidak_diterima_lagi(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload([
                'proposal' => 'proposal.pdf',
            ]))->assertSessionHasErrors('proposal');

        $this->assertDatabaseCount('internship_requests', 0);
    }

    public function test_pengajuan_lama_tanpa_proposal_tetap_dapat_dibuka(): void
    {
        [$operator, $institution] = $this->operator();
        $request = $this->existingRequest($operator, $institution, 'Pengajuan Lama');

        $this->actingAs($operator)
            ->get(route('operator.internship-requests.show', $request))
            ->assertOk()
            ->assertSee('Proposal belum tersedia untuk pengajuan ini.')
            ->assertDontSee(route('operator.internship-requests.proposal', $request));
    }

    public function test_operator_dapat_membuka_link_proposal_miliknya(): void
    {
        [$operator, $institution] = $this->operator();
        $request = $this->existingRequest($operator, $institution, 'Pengajuan dengan proposal Drive');
        $request->proposal_path = 'https://drive.google.com/file/d/proposal-id/view';
        $request->save();

        $this->actingAs($operator)
            ->get(route('operator.internship-requests.show', $request))
            ->assertOk()
            ->assertSee('Lihat Proposal')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_jumlah_peserta_harus_positif(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)
            ->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload(['participant_count' => 0]))
            ->assertSessionHasErrors('participant_count');

        $this->assertDatabaseCount('internship_requests', 0);
    }

    public function test_tanggal_selesai_harus_setelah_tanggal_mulai(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)
            ->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload([
                'start_date' => '2027-03-10',
                'end_date' => '2027-03-10',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('internship_requests', 0);
    }

    public function test_jumlah_peserta_non_integer_ditolak(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)
            ->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload(['participant_count' => '1.5']))
            ->assertSessionHasErrors('participant_count');

        $this->assertDatabaseCount('internship_requests', 0);
    }

    public function test_pengajuan_baru_menyimpan_jumlah_tanpa_membuat_calon_placeholder(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)->post(
            route('operator.internship-requests.store'),
            $this->payload(['participant_count' => 3]),
        )->assertRedirect();

        $this->assertDatabaseCount('internship_applicants', 0);
        $this->assertDatabaseHas('internship_requests', ['participant_count' => 3]);
    }

    public function test_operator_hanya_melihat_pengajuan_institusinya(): void
    {
        [$operator, $institution] = $this->operator('Nama Institusi Sama');
        [$otherOperator, $otherInstitution] = $this->operator('Nama Institusi Sama');
        $this->existingRequest($operator, $institution, 'Pengajuan Milik Saya');
        $this->existingRequest($otherOperator, $otherInstitution, 'Pengajuan Institusi Lain');

        $this->actingAs($operator)
            ->get(route('operator.internship-requests.index'))
            ->assertOk()
            ->assertSee('Pengajuan Milik Saya')
            ->assertDontSee('Pengajuan Institusi Lain');
    }

    public function test_operator_mendapat_404_untuk_detail_institusi_lain(): void
    {
        [$operator] = $this->operator('Nama Sama');
        [$otherOperator, $otherInstitution] = $this->operator('Nama Sama');
        $request = $this->existingRequest($otherOperator, $otherInstitution, 'Pengajuan Rahasia');

        $this->actingAs($operator)
            ->get(route('operator.internship-requests.show', $request))
            ->assertNotFound();
    }

    public function test_guest_tidak_dapat_mengakses_fitur_pengajuan(): void
    {
        $this->get(route('operator.internship-requests.index'))->assertRedirect(route('operator.login'));
        $this->get(route('operator.internship-requests.create'))->assertRedirect(route('operator.login'));
        $this->post(route('operator.internship-requests.store'), $this->payload())->assertRedirect(route('operator.login'));
        $this->get('/operator/internship-requests/1')->assertRedirect(route('operator.login'));
    }

    public function test_admin_tidak_dapat_mengakses_fitur_pengajuan_operator(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('operator.internship-requests.index'))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('operator.internship-requests.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_tidak_tersedia_route_ubah_atau_hapus_pengajuan(): void
    {
        [$operator, $institution] = $this->operator();
        $request = $this->existingRequest($operator, $institution, 'Pengajuan Tetap');

        $this->actingAs($operator)->get('/operator/internship-requests/'.$request->id.'/edit')->assertNotFound();
        $this->actingAs($operator)->put('/operator/internship-requests/'.$request->id, $this->payload())->assertStatus(405);
        $this->actingAs($operator)->delete('/operator/internship-requests/'.$request->id)->assertStatus(405);

        $this->assertDatabaseHas('internship_requests', ['id' => $request->id, 'title' => 'Pengajuan Tetap']);
    }

    public function test_daftar_pengajuan_menghitung_calon_tanpa_query_n_plus_satu(): void
    {
        [$operator, $institution] = $this->operator();

        for ($index = 1; $index <= 3; $index++) {
            $request = $this->existingRequest($operator, $institution, 'Pengajuan '.$index);
            $request->applicants()->create($this->applicant([
                'name' => 'Calon '.$index,
                'email' => "calon{$index}@example.test",
                'student_number' => "SISWA-00{$index}",
            ]));
        }

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $response = $this->actingAs($operator)
            ->get(route('operator.internship-requests.index'))
            ->assertOk()
            ->assertSee('Pengajuan 1')
            ->assertSee('Pengajuan 2')
            ->assertSee('Pengajuan 3');

        $applicantQueries = collect($connection->getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'internship_applicants'));
        $connection->disableQueryLog();

        $this->assertCount(1, $applicantQueries, 'Calon peserta seharusnya dihitung dalam satu query agregat.');
        $response->assertSee('Menunggu diproses');
    }
}
