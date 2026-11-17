<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    public function test_command_membuat_admin_tanpa_verifikasi_dan_tanpa_institusi(): void
    {
        Notification::fake();

        $this->runCommand('Admin Uji', 'admin.uji@example.test', 'rahasia123', 'rahasia123')
            ->expectsOutputToContain('Akun admin berhasil dibuat.')
            ->assertSuccessful();

        $admin = User::where('email', 'admin.uji@example.test')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertNull($admin->institution_id);
        $this->assertNull($admin->email_verified_at);
        Notification::assertNotSentTo($admin, VerifyEmail::class);
    }

    public function test_password_admin_tersimpan_dalam_bentuk_hash(): void
    {
        $this->runCommand('Admin Hash', 'admin.hash@example.test', 'rahasia123', 'rahasia123')
            ->assertSuccessful();

        $admin = User::where('email', 'admin.hash@example.test')->firstOrFail();

        $this->assertNotSame('rahasia123', $admin->password);
        $this->assertTrue(Hash::check('rahasia123', $admin->password));
    }

    public function test_email_duplikat_ditolak(): void
    {
        User::factory()->admin()->create(['email' => 'sudah.ada@example.test']);

        $this->runCommand('Admin Baru', 'sudah.ada@example.test', 'rahasia123', 'rahasia123')
            ->expectsOutputToContain('Email sudah digunakan oleh akun lain.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', [
            'email' => 'sudah.ada@example.test',
            'name' => 'Admin Baru',
        ]);
    }

    public function test_konfirmasi_password_tidak_cocok_ditolak(): void
    {
        $this->runCommand('Admin Salah', 'admin.salah@example.test', 'rahasia123', 'rahasia456')
            ->expectsOutputToContain('Konfirmasi password tidak cocok.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin.salah@example.test']);
    }

    public function test_password_kurang_dari_delapan_karakter_ditolak(): void
    {
        $this->runCommand('Admin Pendek', 'admin.pendek@example.test', 'pendek', 'pendek')
            ->expectsOutputToContain('Password admin minimal 8 karakter.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin.pendek@example.test']);
    }

    private function runCommand(string $name, string $email, string $password, string $confirmation): PendingCommand
    {
        return $this->artisan('admin:create')
            ->expectsQuestion('Nama admin', $name)
            ->expectsQuestion('Email admin', $email)
            ->expectsQuestion('Password admin (minimal 8 karakter)', $password)
            ->expectsQuestion('Konfirmasi password admin', $confirmation);
    }
}
