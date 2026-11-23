<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\RegistrationToken;
use App\Models\User;
use Illuminate\Support\Carbon;
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
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    private function driveUrl(): string
    {
        return 'https://drive.google.com/file/d/drive-file-id/view?usp=sharing';
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
        $request->proposal_path = $this->driveUrl();
        $request->save();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.internship-requests.show', $request))
            ->assertOk()
            ->assertSee('SMK Detail')
            ->assertSee($operator->email)
            ->assertSee('Peserta Uji')
            ->assertSee('Lihat Proposal')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_operator_dapat_membuka_link_surat_balasan(): void
    {
        [$operator, $institution] = $this->operator('SMK Surat Drive');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();
        $request->status = 'approved';
        $request->processed_by = $admin->id;
        $request->decided_at = now();
        $request->reply_letter_path = $this->driveUrl();
        $request->save();

        $this->actingAs($operator)
            ->get(route('operator.internship-requests.show', $request))
            ->assertOk()
            ->assertSee('Lihat Surat Balasan')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_admin_dapat_menyetujui_pengajuan_dengan_link_surat_google_drive(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 09:30:00'));
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Approved');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.internship-requests.decision', $request),
            ['decision' => 'approved', 'reply_letter_url' => $this->driveUrl()],
        );

        $request->refresh();
        $response->assertOk()->assertViewIs('admin.registration-tokens.issued');
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->processed_by);
        $this->assertNotNull($request->decided_at);
        $this->assertNotNull($request->reply_letter_path);
        $this->assertSame($this->driveUrl(), $request->reply_letter_path);
        $this->assertSame([], Storage::disk('local')->allFiles('reply-letters'));
        $this->assertDatabaseCount('registration_tokens', 1);
        $this->assertDatabaseCount('users_intern', 0);

        $issuedTokens = $response->viewData('issuedTokens');
        $plainToken = $issuedTokens[0]['token'];
        $registrationToken = RegistrationToken::query()->firstOrFail();
        $this->assertSame(hash('sha256', $plainToken), $registrationToken->token_hash);
        $this->assertStringNotContainsString($plainToken, $registrationToken->token_hash);
        $this->assertSame('active', $registrationToken->status);
        $this->assertNull($registrationToken->used_at);
        $this->assertTrue($registrationToken->expires_at->equalTo(now()->addDays(30)));
        $this->assertStringContainsString($plainToken, $response->getContent());
        $this->assertFalse(session()->has('issuedTokens'));

        $this->get(route('admin.internship-requests.show', $request))
            ->assertOk()
            ->assertDontSee($plainToken);
    }

    public function test_admin_dapat_menolak_pengajuan_dengan_link_surat_google_drive(): void
    {
        Storage::fake('local');
        [$operator, $institution] = $this->operator('SMK Rejected');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.internship-requests.decision', $request),
            ['decision' => 'rejected', 'reply_letter_url' => $this->driveUrl()],
        )->assertRedirect(route('admin.internship-requests.show', $request));

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame($admin->id, $request->processed_by);
        $this->assertNotNull($request->decided_at);
        $this->assertSame($this->driveUrl(), $request->reply_letter_path);
        $this->assertDatabaseCount('registration_tokens', 0);
    }

    public function test_approval_menerbitkan_satu_token_untuk_setiap_peserta(): void
    {
        [$operator, $institution] = $this->operator('SMK Multi Token');
        $request = $this->pendingRequest($operator, $institution);
        $request->applicants()->create([
            'name' => 'Peserta Kedua',
            'email' => 'peserta.kedua@example.test',
            'student_number' => 'SISWA-002',
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter_url' => $this->driveUrl(),
        ]);

        $response->assertOk()->assertViewIs('admin.registration-tokens.issued');
        $this->assertDatabaseCount('registration_tokens', 2);
        $this->assertCount(2, $response->viewData('issuedTokens'));

        foreach ($response->viewData('issuedTokens') as $issued) {
            $this->assertSame(hash('sha256', $issued['token']), RegistrationToken::query()
                ->where('applicant_id', $issued['applicant']->id)->value('token_hash'));
        }
    }

    public function test_pengajuan_tanpa_peserta_tidak_dapat_disetujui(): void
    {
        [$operator, $institution] = $this->operator('SMK Tanpa Peserta');
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->id,
            'title' => 'Tanpa peserta',
            'description' => null,
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter_url' => $this->driveUrl(),
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertDatabaseCount('registration_tokens', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('reply-letters'));
    }

    public function test_kegagalan_penerbitan_token_membatalkan_keputusan_dan_membersihkan_surat(): void
    {
        [$operator, $institution] = $this->operator('SMK Token Rollback');
        $request = $this->pendingRequest($operator, $institution);
        $request->applicants()->create([
            'name' => 'Peserta Kedua',
            'email' => 'peserta.kedua@example.test',
            'student_number' => 'SISWA-002',
        ]);
        $admin = User::factory()->admin()->create();
        $created = 0;
        Event::listen('eloquent.creating: '.RegistrationToken::class, function () use (&$created): void {
            $created++;
            if ($created === 2) {
                throw new RuntimeException('Simulasi kegagalan penerbitan token.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter_url' => $this->driveUrl(),
            ]);
            $this->fail('Kegagalan penerbitan token seharusnya membatalkan proses.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulasi kegagalan penerbitan token.', $exception->getMessage());
        }

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertDatabaseCount('registration_tokens', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('reply-letters'));
    }

    public function test_approval_ulang_tidak_menerbitkan_token_ganda(): void
    {
        [$operator, $institution] = $this->operator('SMK Approval Ulang');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter_url' => $this->driveUrl(),
        ])->assertOk();
        $originalHash = RegistrationToken::query()->value('token_hash');

        $this->actingAs($admin)->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter_url' => $this->driveUrl(),
            ])
            ->assertSessionHasErrors('decision');

        $this->assertDatabaseCount('registration_tokens', 1);
        $this->assertSame($originalHash, RegistrationToken::query()->value('token_hash'));
    }

    public function test_keputusan_tanpa_link_surat_balasan_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK Tanpa File');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), ['decision' => 'approved'])
            ->assertSessionHasErrors('reply_letter_url');

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
                'reply_letter_url' => $this->driveUrl(),
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

    public function test_link_selain_google_drive_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK File Salah');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter_url' => 'http://drive.google.com/file/d/file-id/view',
            ])
            ->assertSessionHasErrors('reply_letter_url');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_link_dari_domain_lain_ditolak(): void
    {
        [$operator, $institution] = $this->operator('SMK File Besar');
        $request = $this->pendingRequest($operator, $institution);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'approved',
                'reply_letter_url' => 'https://example.com/surat.pdf',
            ])
            ->assertSessionHasErrors('reply_letter_url');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_kegagalan_update_database_tidak_mengubah_pengajuan(): void
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
                'reply_letter_url' => $this->driveUrl(),
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
            'reply_letter_url' => $this->driveUrl(),
        ])->assertOk();

        $originalPath = $request->fresh()->reply_letter_path;
        $this->actingAs($admin)
            ->from(route('admin.internship-requests.show', $request))
            ->post(route('admin.internship-requests.decision', $request), [
                'decision' => 'rejected',
                'reply_letter_url' => $this->driveUrl(),
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($originalPath, $request->fresh()->reply_letter_path);
        $this->assertSame([], Storage::disk('local')->allFiles('reply-letters'));
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

        $this->get(route('admin.internship-requests.reply-letter', $request))->assertRedirect(route('admin.login'));
        $this->get(route('operator.internship-requests.reply-letter', $request))->assertRedirect(route('operator.login'));
        $this->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter_url' => $this->driveUrl(),
        ])->assertRedirect(route('admin.login'));
    }

    public function test_operator_tidak_dapat_melakukan_keputusan_admin(): void
    {
        [$operator, $institution] = $this->operator('SMK Operator');
        $request = $this->pendingRequest($operator, $institution);

        $this->actingAs($operator)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter_url' => $this->driveUrl(),
        ])->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }
}
