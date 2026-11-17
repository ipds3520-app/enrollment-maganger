<?php

namespace Tests\Feature\Operator;

use App\Models\Institution;
use App\Models\InternshipApplicant;
use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternshipRequestTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
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
            'applicants' => [$this->applicant()],
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

    public function test_operator_dapat_membuat_pengajuan_dengan_satu_calon(): void
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
        $this->assertCount(1, $request->applicants);
        $this->assertSame('calon@example.test', $request->applicants->first()->email);
    }

    public function test_operator_dapat_membuat_pengajuan_dengan_beberapa_calon(): void
    {
        [$operator] = $this->operator();
        $applicants = [
            $this->applicant(),
            $this->applicant([
                'name' => 'Calon Kedua',
                'email' => 'calon.kedua@example.test',
                'student_number' => 'SISWA-002',
            ]),
        ];

        $this->actingAs($operator)->post(
            route('operator.internship-requests.store'),
            $this->payload(['applicants' => $applicants]),
        )->assertRedirect();

        $this->assertDatabaseCount('internship_requests', 1);
        $this->assertDatabaseCount('internship_applicants', 2);
        $this->assertDatabaseHas('internship_applicants', ['name' => 'Calon Kedua']);
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
            'letter_number',
            'admin_notes',
        ]);
        $this->assertDatabaseCount('internship_requests', 0);
        $this->assertDatabaseCount('internship_applicants', 0);
    }

    public function test_pengajuan_tanpa_calon_ditolak(): void
    {
        [$operator] = $this->operator();

        $this->actingAs($operator)
            ->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload(['applicants' => []]))
            ->assertSessionHasErrors('applicants');

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

    public function test_nomor_induk_duplikat_dalam_satu_pengajuan_ditolak(): void
    {
        [$operator] = $this->operator();
        $applicants = [
            $this->applicant(),
            $this->applicant([
                'name' => 'Calon Kedua',
                'email' => 'calon.kedua@example.test',
            ]),
        ];

        $this->actingAs($operator)
            ->from('/operator/internship-requests/create')
            ->post(route('operator.internship-requests.store'), $this->payload(['applicants' => $applicants]))
            ->assertSessionHasErrors('applicants');

        $this->assertDatabaseCount('internship_requests', 0);
    }

    public function test_kegagalan_simpan_calon_membatalkan_seluruh_transaksi(): void
    {
        [$operator] = $this->operator();
        Event::listen('eloquent.creating: '.InternshipApplicant::class, function (InternshipApplicant $applicant): void {
            if ($applicant->name === 'Paksa Gagal') {
                throw new RuntimeException('Simulasi kegagalan insert calon.');
            }
        });

        $this->withoutExceptionHandling();
        $failedAsExpected = false;

        try {
            $this->actingAs($operator)->post(route('operator.internship-requests.store'), $this->payload([
                'applicants' => [
                    $this->applicant(),
                    $this->applicant([
                        'name' => 'Paksa Gagal',
                        'email' => 'gagal@example.test',
                        'student_number' => 'SISWA-002',
                    ]),
                ],
            ]));
        } catch (RuntimeException $exception) {
            $failedAsExpected = $exception->getMessage() === 'Simulasi kegagalan insert calon.';
        }

        $this->assertTrue($failedAsExpected, 'Insert calon seharusnya gagal di dalam transaksi.');
        $this->assertDatabaseCount('internship_requests', 0);
        $this->assertDatabaseCount('internship_applicants', 0);
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
        $this->get(route('operator.internship-requests.index'))->assertRedirect(route('login'));
        $this->get(route('operator.internship-requests.create'))->assertRedirect(route('login'));
        $this->post(route('operator.internship-requests.store'), $this->payload())->assertRedirect(route('login'));
        $this->get('/operator/internship-requests/1')->assertRedirect(route('login'));
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
