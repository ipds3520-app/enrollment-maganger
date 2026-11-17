<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed data contoh untuk pengembangan lokal.
     *
     * Akun admin TIDAK di-seed di sini (tanpa password hardcoded untuk
     * production). Buat admin melalui: php artisan admin:create
     */
    public function run(): void
    {
        $institution = Institution::factory()->create([
            'name' => 'SMK Negeri 1 Contoh',
            'type' => 'smk',
        ]);

        User::factory()
            ->operator($institution)
            ->create([
                'name' => 'Operator Contoh',
                'email' => 'operator@example.com',
            ]);
    }
}
