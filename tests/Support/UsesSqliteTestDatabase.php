<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyiapkan skema database khusus pengujian pada SQLite in-memory.
 *
 * Migration produksi memakai sintaks khusus PostgreSQL (ALTER TABLE ... ADD
 * CONSTRAINT, btrim) yang tidak kompatibel dengan SQLite. Karena migration
 * produksi tidak boleh diubah sembarangan, pengujian memakai migration
 * terpisah di tests/database/migrations yang meniru struktur tabel inti.
 *
 * Pengaman: pengujian dibatalkan bila koneksi bukan sqlite :memory:,
 * sehingga database Supabase tidak akan pernah tersentuh.
 */
trait UsesSqliteTestDatabase
{
    protected function setUpSqliteTestDatabase(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Pengujian dibatalkan: koneksi harus sqlite :memory:, tetapi driver={$driver}, database={$database}. "
                .'Periksa phpunit.xml — pengujian tidak boleh menyentuh Supabase.'
            );
        }

        $this->artisan('migrate:fresh', [
            '--path' => 'tests/database/migrations',
            '--force' => true,
        ])->run();

        $participantLifecycleMigration = require base_path('database/migrations/2026_11_26_000001_add_intern_participant_lifecycle_and_soft_deletes.php');
        $participantLifecycleMigration->up();

        $internAuthVersionMigration = require base_path('database/migrations/2026_11_26_000002_add_auth_version_to_users_intern_table.php');
        $internAuthVersionMigration->up();
    }
}
