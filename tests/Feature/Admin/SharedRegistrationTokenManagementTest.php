<?php

namespace Tests\Feature\Admin;

use App\Models\Institution;
use App\Models\InternshipRequest;
use App\Models\SharedRegistrationToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class SharedRegistrationTokenManagementTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSqliteTestDatabase();
    }

    /** @return array{InternshipRequest, User, User, SharedRegistrationToken, string} */
    private function approvedToken(int $quota = 3): array
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->getKey(),
            'title' => 'Program Magang Pengujian',
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
            'participant_count' => $quota,
        ]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.internship-requests.decision', $request), [
            'decision' => 'approved',
            'reply_letter_url' => 'https://drive.google.com/file/d/drive-file-id/view?usp=sharing',
        ]);
        $response->assertOk()->assertViewIs('admin.shared-registration-tokens.issued');

        return [
            $request->fresh(),
            $operator,
            $admin,
            SharedRegistrationToken::query()->where('internship_request_id', $request->getKey())->sole(),
            $response->viewData('plaintextToken'),
        ];
    }

    public function test_token_baru_disimpan_terenkripsi_dan_tidak_muncul_dalam_serialisasi(): void
    {
        [, , , $token, $plaintext] = $this->approvedToken();

        $this->assertSame(hash('sha256', $plaintext), $token->token_hash);
        $this->assertSame($plaintext, $token->token_encrypted);
        $this->assertNotSame($plaintext, DB::table('shared_registration_tokens')->where('id', $token->id)->value('token_encrypted'));
        $this->assertArrayNotHasKey('token_hash', $token->toArray());
        $this->assertArrayNotHasKey('token_encrypted', $token->toArray());
    }

    public function test_operator_hanya_melihat_token_dan_kuota_milik_institusinya(): void
    {
        [$request, $operator, , $token, $plaintext] = $this->approvedToken();
        $detail = $this->actingAs($operator, 'operator')->get(route('operator.internship-requests.show', $request));

        $detail->assertOk()
            ->assertSee($plaintext)
            ->assertSee('Salin token')
            ->assertSee('Sisa kuota')
            ->assertDontSee($token->token_hash)
            ->assertDontSee(DB::table('shared_registration_tokens')->where('id', $token->id)->value('token_encrypted'));
        $this->assertStringContainsString('no-store', $detail->headers->get('Cache-Control', ''));
        $this->assertSame('no-referrer', $detail->headers->get('Referrer-Policy'));

        $otherInstitution = Institution::factory()->create();
        $otherOperator = User::factory()->operator($otherInstitution)->create();
        $this->actingAs($otherOperator, 'operator')
            ->get(route('operator.internship-requests.show', $request))
            ->assertNotFound();
    }

    public function test_dashboard_operator_memuat_panduan_pendaftaran_sesuai_alur_aplikasi(): void
    {
        [, $operator] = $this->approvedToken();

        $this->actingAs($operator, 'operator')->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Panduan pendaftaran peserta')
            ->assertSee('tunggu statusnya menjadi disetujui')
            ->assertSee(route('intern.register'))
            ->assertSee('Kuota baru berkurang setelah peserta melengkapi dan menyimpan profil.');
    }

    public function test_token_lama_tetap_hash_only_tidak_berubah_dan_dapat_dipakai(): void
    {
        $institution = Institution::factory()->create();
        $operator = User::factory()->operator($institution)->create();
        $request = $institution->internshipRequests()->create([
            'created_by' => $operator->getKey(),
            'title' => 'Pengajuan Token Lama',
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-10',
            'participant_count' => 2,
            'status' => 'approved',
        ]);
        $plaintext = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plaintext);
        $token = SharedRegistrationToken::create([
            'internship_request_id' => $request->getKey(),
            'token_hash' => $hash,
            'quota' => 2,
            'used_count' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($operator, 'operator')
            ->get(route('operator.internship-requests.show', $request))
            ->assertOk()
            ->assertSee('Token ini diterbitkan sebelum pembaruan sistem. Token tidak dapat ditampilkan kembali.')
            ->assertDontSee($plaintext);

        $this->post(route('intern.register.store'), [
            'token' => $plaintext,
            'name' => 'Intern Token Lama',
            'email' => 'intern-lama@example.test',
            'password' => 'A-strong-password-123',
            'password_confirmation' => 'A-strong-password-123',
        ])->assertRedirect(route('intern.profile.show'));

        $token->refresh();
        $this->assertSame($hash, $token->token_hash);
        $this->assertNull($token->token_encrypted);
        $this->assertSame(0, $token->used_count);
        $this->assertSame('active', $token->status);
        $this->assertSame($token->id, SharedRegistrationToken::query()->sole()->id);
    }

    public function test_admin_dapat_memantau_semua_token_tanpa_melihat_secret(): void
    {
        [, , $admin, $token, $plaintext] = $this->approvedToken();
        [, , , $secondToken] = $this->approvedToken();
        $ciphertext = DB::table('shared_registration_tokens')->where('id', $token->id)->value('token_encrypted');

        $this->actingAs($admin)->get(route('admin.shared-registration-tokens.index'))
            ->assertOk()
            ->assertSee('Program Magang Pengujian')
            ->assertSee('Referensi')
            ->assertSee('#'.$token->id)
            ->assertSee('#'.$secondToken->id)
            ->assertDontSee($plaintext)
            ->assertDontSee($ciphertext)
            ->assertDontSee($token->token_hash);
    }

    public function test_admin_mengubah_kuota_tanpa_menerbitkan_ulang_token(): void
    {
        [$request, , $admin, $token] = $this->approvedToken(3);
        $hash = $token->token_hash;
        $ciphertext = DB::table('shared_registration_tokens')->where('id', $token->id)->value('token_encrypted');

        $this->actingAs($admin)->put(route('admin.internship-requests.shared-registration-token.quota', $request), ['quota' => 5])
            ->assertRedirect(route('admin.internship-requests.show', $request))
            ->assertSessionHas('status', 'Kuota peserta diperbarui dari 3 menjadi 5 peserta.');

        $updated = $token->fresh();
        $this->assertSame($token->id, $updated->id);
        $this->assertSame(5, $updated->quota);
        $this->assertSame($hash, $updated->token_hash);
        $this->assertSame($ciphertext, DB::table('shared_registration_tokens')->where('id', $token->id)->value('token_encrypted'));
        $this->assertSame($admin->id, $updated->quota_updated_by);
        $this->assertNotNull($updated->quota_updated_at);
        $this->assertSame('active', $updated->status);
    }

    public function test_kuota_tidak_dapat_diturunkan_di_bawah_jumlah_profil_selesai(): void
    {
        [$request, , $admin, $token] = $this->approvedToken(3);
        $token->used_count = 2;
        $token->save();

        $this->actingAs($admin)->from(route('admin.internship-requests.show', $request))
            ->put(route('admin.internship-requests.shared-registration-token.quota', $request), ['quota' => 1])
            ->assertSessionHasErrors('quota');

        $this->assertSame(3, $token->fresh()->quota);
        $this->assertNull($token->fresh()->quota_updated_at);
    }

    public function test_token_exhausted_aktif_kembali_jika_kuota_dinaikkan_dan_token_revoked_tetap_dicabut(): void
    {
        [$request, , $admin, $token] = $this->approvedToken(1);
        $token->used_count = 1;
        $token->status = 'exhausted';
        $token->save();

        $this->actingAs($admin)->put(route('admin.internship-requests.shared-registration-token.quota', $request), ['quota' => 2])
            ->assertRedirect(route('admin.internship-requests.show', $request));
        $this->assertSame('active', $token->fresh()->status);

        $token->status = 'revoked';
        $token->save();
        $this->actingAs($admin)->put(route('admin.internship-requests.shared-registration-token.quota', $request), ['quota' => 3])
            ->assertRedirect(route('admin.internship-requests.show', $request));
        $this->assertSame('revoked', $token->fresh()->status);
    }

    public function test_operator_dan_guest_tidak_dapat_mengakses_pemantauan_atau_mengubah_kuota(): void
    {
        [$request, $operator] = $this->approvedToken();

        Auth::guard('admin')->logout();
        Auth::guard('web')->logout();
        Auth::guard('operator')->logout();

        $this->put(route('admin.internship-requests.shared-registration-token.quota', $request), ['quota' => 6])
            ->assertRedirect(route('admin.login'));

        $this->actingAs($operator, 'operator')->get(route('admin.shared-registration-tokens.index'))->assertForbidden();
        $this->put(route('admin.internship-requests.shared-registration-token.quota', $request), ['quota' => 6])
            ->assertForbidden();
    }
}
