<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class InternshipRequestReviewTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    /** @return array{User, Institution} */
    private function operator(string $institutionName): array
    {
        $institution = Institution::factory()->create(['name' => $institutionName]);
        $operator = User::factory()->operator($institution)->create();

        return [$operator, $institution];
    }

    private function pendingRequest(User $operator, Institution $institution, string $title = 'Pengajuan Uji'): InternshipRequest
    {
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->id,
            'title' => $title,
            'description' => 'Deskripsi pengajuan.',
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);

        $request->applicants()->create([
            'name' => 'Peserta Uji',
            'email' => 'peserta@example.test',
            'phone' => '081234567890',
            'student_number' => 'SISWA-001',
            'school_major' => 'RPL',
        ]);

        return $request;
    }

    private function pdfFile(string $name = 'surat-tu.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 1, 'application/pdf');
    }

    public function test_admin_melihat_pengajuan_dari_seluruh_institusi(): void
    {
        [$operatorA, $institutionA] = $this->operator('SMK A');
        [$operatorB, $institutionB] = $this->operator('SMK B');
        $this->pendingRequest($operatorA, $institutionA, 'Pengajuan A');
        $this->pendingRequest($operatorB, $institutionB, 'Pengajuan B');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.internship-requests.index'))
            ->assertOk()
            ->assertSee('SMK A')
            ->assertSee('SMK B')
            ->assertSee('Pengajuan A')
            ->assertSee('Pengajuan B')
            ->assertSee($operatorA->name)
            ->assertSee($operatorB->name);
    }

    public function test_admin_dapat_mencari_dan_memfilter_pengajuan(): void
    {
        [$operatorA, $institutionA] = $this->operator('SMK Cendekia');
        [$operatorB, $institutionB] = $this->operator('SMK Mandiri');
        $this->pendingRequest($operatorA, $institutionA, 'Magang Teknologi');
        $this->pendingRequest($operatorB, $institutionB, 'Magang Akuntansi');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.internship-requests.index', ['q' => 'cendekia', 'status' => 'pending']))
            ->assertOk()
            ->assertSee('Magang Teknologi')
            ->assertDontSee('Magang Akuntansi');
    }

    public function test_admin_dapat_membuka_detail_pengajuan(): void
    {
        [$operator, $institution] = $this->operator('SMK Detail');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.internship-requests.show', $request))
            ->assertOk()
            ->assertSee('SMK Detail')
            ->assertSee($operator->email)
            ->assertSee('Peserta Uji');
    }

    public function test_admin_dapat_menyetujui_pengajuan_dengan_pdf(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Approved');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.internship-requests.decision', $request),
            ['decision' => 'approved', 'reply_letter' => $this->pdfFile()],
        );

        $request->refresh();
        $response->assertRedirect(route('admin.internship-requests.show', $request));
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->processed_by);
        $this->assertNotNull($request->decided_at);
        $this->assertNotNull($request->reply_letter_path);
        $this->assertStringStartsWith('reply-letters/', $request->reply_letter_path);
        $this->assertStringEndsWith('.pdf', $request->reply_letter_path);
        Storage::disk('local')->assertExists($request->reply_letter_path);
        $this->assertDatabaseCount('registration_tokens', 0);
        $this->assertDatabaseCount('users_intern', 0);
    }

    public function test_admin_dapat_menolak_pengajuan_dengan_pdf(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Rejected');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.internship-requests.decision', $request),
            ['decision' => 'rejected', 'reply_letter' => $this->pdfFile()],
        )->assertRedirect(route('admin.internship-requests.show', $request));

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame($admin->id, $request->processed_by);
        $this->assertNotNull($request->decided_at);
        Storage::disk('local')->assertExists($request->reply_letter_path);
    }

    public function test_keputusan_tanpa_file_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK Tanpa File');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), ['decision' => 'approved'])
            ->assertSessionHasErrors('reply_letter');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_field_keputusan_sensitif_dari_input_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK Manipulasi');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter' => $this->pdfFile(),
                'status' => 'rejected',
                'processed_by' => $operator->id,
                'decided_at' => '2020-01-01 00:00:00',
                'reply_letter_path' => 'reply-letters/palsu.pdf',
            ])
            ->assertSessionHasErrors(['status', 'processed_by', 'decided_at', 'reply_letter_path']);

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->processed_by);
        $this->assertNull($request->decided_at);
        $this->assertNull($request->reply_letter_path);
    }

    public function test_file_non_pdf_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK File Salah');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter' => UploadedFile::fake()->create('surat.txt', 1, 'text/plain'),
            ])
            ->assertSessionHasErrors('reply_letter');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_file_lebih_dari_10_mb_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK File Besar');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter' => UploadedFile::fake()->create('surat.pdf', 10241, 'application/pdf'),
            ])
            ->assertSessionHasErrors('reply_letter');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_kegagalan_update_database_menghapus_hanya_file_baru(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Rollback');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();
        Event::listen('eloquent.updating: '.InternshipRequest::class, function (InternshipRequest $model): void {
            if ($model->status === 'approved') {
                throw new RuntimeException('Simulasi kegagalan penyimpanan keputusan.');
            }
        });

        $this->withoutExceptionHandling();
        $failedAsExpected = false;

        try {
            $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter' => $this->pdfFile(),
            ]);
        } catch (RuntimeException $exception) {
            $failedAsExpected = $exception->getMessage() === 'Simulasi kegagalan penyimpanan keputusan.';
        }

        $this->assertTrue($failedAsExpected);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertNull($request->fresh()->reply_letter_path);
        $this->assertSame([], Storage::disk('local')->allFiles('reply-letters'));
    }

    public function test_pengajuan_yang_sudah_diproses_tidak_dapat_diproses_ulang(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Sekali');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter' => $this->pdfFile(),
        ])->assertRedirect();

        $originalPath = $request->fresh()->reply_letter_path;
        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'rejected',
                'reply_letter' => $this->pdfFile('surat-kedua.pdf'),
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($originalPath, $request->fresh()->reply_letter_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('reply-letters'));
    }

    public function test_admin_dapat_mengunduh_surat_dari_semua_institusi(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        foreach (['SMK Surat A', 'SMK Surat B'] as $index => $name) {
            [$operator, $institution] = $this->operator($name);
            $request = $this->pendingRequest($operator, $institution, 'Pengajuan '.$index);
            $path = 'reply-letters/surat-admin-'.$index.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 surat uji');
            $request->forceFill([
                'status' => 'approved',
                'processed_by' => $admin->id,
                'decided_at' => now(),
                'reply_letter_path' => $path,
            ])->save();

            $this->actingAs($admin)
                ->get(route('admin.internship-requests.reply-letter', $request))
                ->assertDownload('surat-balasan-'.$request->id.'.pdf');
        }
    }

    public function test_operator_dapat_mengunduh_surat_milik_institusinya(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Pemilik');
        $request = $this->pendingRequest($operator, $institution);
        $path = 'reply-letters/surat-operator.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 surat uji');
        $request->forceFill([
            'status' => 'rejected',
            'processed_by' => User::factory()->admin()->create()->id,
            'decided_at' => now(),
            'reply_letter_path' => $path,
        ])->save();

        $this->actingAs($operator)
            ->get(route('operator.internship-requests.reply-letter', $request))
            ->assertDownload('surat-balasan-'.$request->id.'.pdf');
    }

    public function test_operator_mendapat_404_untuk_surat_institusi_lain(): void
    {
        Storage::fake('local');
        [$operatorA] = $this->operator('SMK Satu');
        [$operatorB, $institutionB] = $this->operator('SMK Dua');
        $request = $this->pendingRequest($operatorB, $institutionB);
        $path = 'reply-letters/surat-milik-lain.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 surat uji');
        $request->forceFill([
            'status' => 'approved',
            'processed_by' => User::factory()->admin()->create()->id,
            'decided_at' => now(),
            'reply_letter_path' => $path,
        ])->save();

        $this->actingAs($operatorA)
            ->get(route('operator.internship-requests.reply-letter', $request))
            ->assertNotFound();
    }

    public function test_download_hanya_tersedia_untuk_pengajuan_final_dengan_file(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Belum Final');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.internship-requests.reply-letter', $request))
            ->assertNotFound();

        $request->forceFill([
            'status' => 'approved',
            'processed_by' => $admin->id,
            'decided_at' => now(),
            'reply_letter_path' => 'reply-letters/tidak-ada.pdf',
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.internship-requests.reply-letter', $request))
            ->assertNotFound();
    }

    public function test_guest_tidak_dapat_mengakses_download_atau_keputusan(): void
    {
        [$operator, $institution] = $this->operator('SMK Guest');
        $request = $this->pendingRequest($operator, $institution);

        $this->get(route('admin.internship-requests.reply-letter', $request))->assertRedirect(route('login'));
        $this->get(route('operator.internship-requests.reply-letter', $request))->assertRedirect(route('login'));
        $this->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter' => $this->pdfFile(),
        ])->assertRedirect(route('login'));
    }

    public function test_operator_tidak_dapat_melakukan_keputusan_admin(): void
    {
        [$operator, $institution] = $this->operator('SMK Operator');
        $request = $this->pendingRequest($operator, $institution);

        $this->actingAs($operator)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter' => $this->pdfFile(),
        ])->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }
}
