<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use UsesSqliteTestDatabase;

    private const GENERIC_STATUS = 'Jika alamat email tersebut terdaftar, kami telah mengirimkan tautan reset password. Silakan periksa kotak masuk Anda.';

    private const INVALID_TOKEN_ERROR = 'Tautan reset password tidak valid atau sudah kedaluwarsa.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    private function tokenFor(User $user): string
    {
        return Password::broker()->createToken($user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function resetPayload(User $user, string $token, array $overrides = []): array
    {
        return array_merge([
            'token' => $token,
            'email' => $user->email,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ], $overrides);
    }

    public function test_halaman_lupa_password_dapat_ditampilkan(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Lupa Password')
            ->assertSee('Email')
            ->assertSee('Kirim Tautan Reset');
    }

    public function test_tautan_reset_dikirim_untuk_email_terdaftar(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create();

        $response = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_email_tidak_terdaftar_tidak_mengirim_notifikasi_dan_pesan_tetap_sama(): void
    {
        Notification::fake();

        $response = $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'tidak-terdaftar@example.test']);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertNothingSent();
    }

    public function test_email_dengan_huruf_besar_tetap_dikenali(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create(['email' => 'admin@example.test']);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'ADMIN@EXAMPLE.TEST'])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_permintaan_kedua_dalam_enam_puluh_detik_tidak_mengirim_notifikasi_baru(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_halaman_reset_dapat_ditampilkan_dengan_token_valid(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->tokenFor($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Reset Password')
            ->assertSee('Password Baru')
            ->assertSee($user->email);
    }

    public function test_password_dapat_direset_dengan_token_valid(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password-lama']);
        $token = $this->tokenFor($user);

        $response = $this->post('/reset-password', $this->resetPayload($user, $token));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Password berhasil diubah. Silakan masuk dengan password baru.');

        $user->refresh();

        $this->assertTrue(Hash::check('password-baru-123', $user->password));
        $this->assertFalse(Hash::check('password-lama', $user->password));
    }

    public function test_token_tidak_valid_ditolak(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password-lama']);

        $response = $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($user, 'token-palsu'));

        $response->assertSessionHasErrors(['email' => self::INVALID_TOKEN_ERROR]);

        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        $user = User::factory()->admin()->create(['password' => 'password-lama']);
        $token = $this->tokenFor($user);

        $this->travel(61)->minutes();

        $response = $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($user, $token));

        $response->assertSessionHasErrors(['email' => self::INVALID_TOKEN_ERROR]);

        $this->assertTrue(Hash::check('password-lama', $user->fresh()->password));
    }

    public function test_token_tidak_dapat_digunakan_ulang(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->tokenFor($user);

        $this->post('/reset-password', $this->resetPayload($user, $token))
            ->assertRedirect(route('login'));

        $response = $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($user, $token));

        $response->assertSessionHasErrors(['email' => self::INVALID_TOKEN_ERROR]);
    }

    public function test_email_harus_cocok_dengan_token(): void
    {
        $userA = User::factory()->admin()->create(['password' => 'password-lama']);
        $userB = User::factory()->admin()->create();
        $token = $this->tokenFor($userA);

        $response = $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($userA, $token, [
                'email' => $userB->email,
            ]));

        $response->assertSessionHasErrors(['email' => self::INVALID_TOKEN_ERROR]);

        $this->assertTrue(Hash::check('password-lama', $userA->fresh()->password));
    }

    public function test_operator_tidak_dapat_memulai_reset_password_email_bawaan(): void
    {
        Notification::fake();
        $operator = User::factory()->operator()->create();

        $this->post('/forgot-password', ['email' => $operator->email])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_token_reset_email_lama_operator_tidak_dapat_mengubah_password(): void
    {
        $operator = User::factory()->operator()->create(['password' => 'password-lama']);
        $token = $this->tokenFor($operator);

        $this->get(route('password.reset', ['token' => $token, 'email' => $operator->email]))
            ->assertNotFound();

        $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($operator, $token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password-lama', $operator->fresh()->password));
    }

    public function test_konfirmasi_password_baru_harus_cocok(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->tokenFor($user);

        $response = $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($user, $token, [
                'password_confirmation' => 'berbeda-123',
            ]));

        $response->assertSessionHasErrors([
            'password' => 'Konfirmasi password tidak cocok.',
        ]);
    }

    public function test_password_baru_minimal_delapan_karakter(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->tokenFor($user);

        $response = $this->from('/reset-password')
            ->post('/reset-password', $this->resetPayload($user, $token, [
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ]));

        $response->assertSessionHasErrors([
            'password' => 'Password baru minimal 8 karakter.',
        ]);
    }

    public function test_percobaan_reset_ketujuh_terkena_rate_limit(): void
    {
        $user = User::factory()->admin()->create();

        $payload = $this->resetPayload($user, 'token-palsu');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/reset-password', $payload);
        }

        $this->post('/reset-password', $payload)->assertStatus(429);
    }

    public function test_percobaan_lupa_password_ketujuh_terkena_rate_limit(): void
    {
        Notification::fake();

        $payload = ['email' => 'tidak-terdaftar@example.test'];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/forgot-password', $payload);
        }

        $this->post('/forgot-password', $payload)->assertStatus(429);
    }

    public function test_pengguna_yang_sudah_login_dapat_membuka_halaman_lupa_password_tanpa_mengubah_guard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/forgot-password')
            ->assertOk();
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_pengguna_yang_sudah_login_dapat_membuka_halaman_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->tokenFor($admin);

        $this->actingAs($admin)
            ->get(route('password.reset', ['token' => $token, 'email' => $admin->email]))
            ->assertOk();
    }

    public function test_isi_email_reset_menggunakan_bahasa_indonesia(): void
    {
        $user = User::factory()->admin()->create();

        $mail = (new ResetPassword('token-uji'))->toMail($user);

        $this->assertSame('Reset Password Akun Anda', $mail->subject);
        $this->assertSame('Reset Password', $mail->actionText);
        $this->assertStringContainsString('token-uji', $mail->actionUrl);
        $this->assertStringContainsString('reset-password', $mail->actionUrl);
    }

    public function test_admin_juga_dapat_meminta_tautan_reset_password(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $admin->email])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo($admin, ResetPassword::class);
    }
}
