<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Membuat akun admin tanpa registrasi publik.
 *
 * Role dan institution_id ditetapkan secara eksplisit di server —
 * tidak pernah berasal dari input pengguna. Password diminta secara
 * rahasia (tidak ditampilkan) dan disimpan dalam bentuk hash.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Membuat akun admin baru (tanpa registrasi publik)';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nama admin'));
        $email = trim((string) $this->ask('Email admin'));
        $password = (string) $this->secret('Password admin (minimal 8 karakter)');
        $passwordConfirmation = (string) $this->secret('Konfirmasi password admin');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            [
                'name.required' => 'Nama admin wajib diisi.',
                'email.required' => 'Email admin wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'email.unique' => 'Email sudah digunakan oleh akun lain.',
                'password.required' => 'Password admin wajib diisi.',
                'password.min' => 'Password admin minimal 8 karakter.',
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $admin = DB::transaction(fn (): User => User::forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'institution_id' => null,
            ]));
        } catch (QueryException $exception) {
            // Penjagaan tambahan bila email terpakai tepat setelah validasi
            // (race condition). Password tidak pernah ditampilkan.
            $this->error('Gagal membuat akun admin: email mungkin sudah digunakan.');

            return self::FAILURE;
        }

        $this->info('Akun admin berhasil dibuat.');
        $this->line('Nama  : '.$admin->name);
        $this->line('Email : '.$admin->email);

        return self::SUCCESS;
    }
}
