<?php

namespace Tests\Feature\Auth;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'institution_name' => 'SMK Negeri 1 Contoh',
            'institution_type' => 'smk',
            'institution_address' => 'Jl. Pendidikan No. 1',
            'institution_phone' => '021-1234567',
            'institution_email' => 'info@smk1.test',
            'name' => 'Operator Contoh',
            'email' => 'operator@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    public function test_halaman_registrasi_dapat_ditampilkan(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Daftar Akun Operator')
            ->assertSee('Data Institusi')
            ->assertSee('Data Akun Operator')
            ->assertSee('Nama Institusi')
            ->assertSee('Nama Operator');
    }

    public function test_registrasi_operator_berhasil_dan_langsung_login(): void
    {
        $response = $this->post('/register', $this->payload());

        $response->assertRedirect(route('operator.dashboard'));

        $user = User::where('email', 'operator@example.test')->firstOrFail();

        $this->assertSame('operator', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->institution_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_institusi_baru_dibuat_dari_data_form(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('operator.dashboard'));

        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $institution = Institution::findOrFail($user->institution_id);

        $this->assertSame('SMK Negeri 1 Contoh', $institution->name);
        $this->assertSame('smk', $institution->type);
        $this->assertSame('Jl. Pendidikan No. 1', $institution->address);
        $this->assertSame('021-1234567', $institution->phone);
        $this->assertSame('info@smk1.test', $institution->email);
    }

    public function test_setiap_pendaftaran_membuat_institusi_terpisah_walau_nama_sama(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('operator.dashboard'));
        $this->post(route('operator.logout'));

        $this->post('/register', $this->payload([
            'name' => 'Operator Kedua',
            'email' => 'operator2@example.test',
            'institution_address' => 'Jl. Berbeda No. 2',
        ]))->assertRedirect(route('operator.dashboard'));

        $first = User::where('email', 'operator@example.test')->firstOrFail();
        $second = User::where('email', 'operator2@example.test')->firstOrFail();

        $this->assertDatabaseCount('institutions', 2);
        $this->assertNotSame($first->institution_id, $second->institution_id);
    }

    public function test_data_institusi_operator_lain_tidak_berubah(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('operator.dashboard'));
        $this->post(route('operator.logout'));

        $first = User::where('email', 'operator@example.test')->firstOrFail();
        $institution = Institution::findOrFail($first->institution_id);

        $this->post('/register', $this->payload([
            'name' => 'Operator Kedua',
            'email' => 'operator2@example.test',
            'institution_address' => 'Alamat Lain',
            'institution_phone' => '021-9999999',
            'institution_email' => 'lain@smk1.test',
        ]))->assertRedirect(route('operator.dashboard'));

        $institution->refresh();

        $this->assertSame('Jl. Pendidikan No. 1', $institution->address);
        $this->assertSame('021-1234567', $institution->phone);
        $this->assertSame('info@smk1.test', $institution->email);
    }

    public function test_email_duplikat_ditolak(): void
    {
        User::factory()->admin()->create(['email' => 'operator@example.test']);

        $response = $this->from('/register')->post('/register', $this->payload());

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['email' => 'Email sudah digunakan oleh akun lain.']);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_email_duplikat_dengan_huruf_besar_ditolak(): void
    {
        User::factory()->admin()->create(['email' => 'operator@example.test']);

        $response = $this->from('/register')->post('/register', $this->payload([
            'email' => 'OPERATOR@EXAMPLE.TEST',
        ]));

        $response->assertSessionHasErrors(['email' => 'Email sudah digunakan oleh akun lain.']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_role_tidak_dapat_dimanipulasi_lewat_input(): void
    {
        $response = $this->post('/register', $this->payload([
            'role' => 'admin',
        ]));

        $response->assertRedirect(route('operator.dashboard'));

        $user = User::where('email', 'operator@example.test')->firstOrFail();

        $this->assertSame('operator', $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isOperator());
    }

    public function test_institution_id_dari_input_diabaikan(): void
    {
        $institutionLain = Institution::factory()->create();

        $this->post('/register', $this->payload([
            'institution_id' => $institutionLain->id,
        ]))->assertRedirect(route('operator.dashboard'));

        $user = User::where('email', 'operator@example.test')->firstOrFail();

        $this->assertNotSame($institutionLain->id, $user->institution_id);
        $this->assertSame(0, $institutionLain->users()->count());
        $this->assertDatabaseCount('institutions', 2);
    }

    public function test_password_disimpan_dalam_bentuk_hash(): void
    {
        $this->post('/register', $this->payload());

        $user = User::where('email', 'operator@example.test')->firstOrFail();

        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_email_verifikasi_tidak_dikirim_setelah_registrasi(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload());

        $user = User::where('email', 'operator@example.test')->firstOrFail();

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_registrasi_langsung_membuka_dashboard_operator(): void
    {
        $this->followingRedirects()
            ->post('/register', $this->payload())
            ->assertOk()
            ->assertSee('Dashboard Operator');
    }

    public function test_password_kurang_dari_delapan_karakter_ditolak(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ]));

        $response->assertSessionHasErrors(['password' => 'Password minimal 8 karakter.']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('institutions', 0);
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            'password_confirmation' => 'rahasia456',
        ]));

        $response->assertSessionHasErrors(['password' => 'Konfirmasi password tidak cocok.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_field_wajib_tidak_boleh_kosong(): void
    {
        $response = $this->from('/register')->post('/register', []);

        $response->assertSessionHasErrors([
            'institution_name',
            'institution_type',
            'name',
            'email',
            'password',
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_jenis_institusi_tidak_valid_ditolak(): void
    {
        $response = $this->from('/register')->post('/register', $this->payload([
            'institution_type' => 'preschool',
        ]));

        $response->assertSessionHasErrors(['institution_type' => 'Jenis institusi tidak valid.']);
        $this->assertDatabaseCount('institutions', 0);
    }

    public function test_admin_dapat_membuka_registrasi_operator_tanpa_mengganggu_session_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/register')
            ->assertOk();
    }

    public function test_registrasi_operator_tidak_menghapus_session_admin_yang_sudah_aktif(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'admin-password']);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'admin-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->post('/register', $this->payload())
            ->assertRedirect(route('operator.dashboard'));

        $operator = User::query()->where('email', 'operator@example.test')->firstOrFail();
        $institution = Institution::query()->findOrFail($operator->institution_id);

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('operator', $operator->role);
        $this->assertSame('SMK Negeri 1 Contoh', $institution->name);
        $this->assertSame('smk', $institution->type);
        $this->assertSame('Jl. Pendidikan No. 1', $institution->address);
        $this->assertSame('021-1234567', $institution->phone);
        $this->assertSame('info@smk1.test', $institution->email);
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($operator, 'operator');
    }

    public function test_percobaan_registrasi_ketujuh_terkena_rate_limit(): void
    {
        $payload = $this->payload(['email' => 'bukan-email']);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/register', $payload);
        }

        $this->post('/register', $payload)->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registrasi_tidak_membuat_admin(): void
    {
        $this->post('/register', $this->payload());

        $this->assertSame(0, User::where('role', 'admin')->count());
    }

    public function test_institusi_dapat_dibuat_tanpa_field_opsional(): void
    {
        $this->post('/register', $this->payload([
            'institution_address' => '',
            'institution_phone' => '',
            'institution_email' => '',
        ]))->assertRedirect(route('operator.dashboard'));

        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $institution = Institution::findOrFail($user->institution_id);

        $this->assertNull($institution->address);
        $this->assertNull($institution->phone);
        $this->assertNull($institution->email);
    }

    public function test_email_disimpan_dalam_huruf_kecil(): void
    {
        $this->post('/register', $this->payload([
            'email' => 'Operator.Baru@Example.TEST',
        ]));

        $user = User::where('email', 'operator.baru@example.test')->firstOrFail();

        $this->assertSame('operator.baru@example.test', $user->email);
    }
}
