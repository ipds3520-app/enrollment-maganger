    <?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

#[Signature('supabase:test {--connection= : Nama koneksi database yang diuji (default: koneksi default)}')]
#[Description('Tes koneksi aplikasi ke Supabase (PostgreSQL)')]
class TestSupabaseConnection extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connectionName = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$connectionName}");

        $this->info('=== Tes Koneksi Supabase ===');
        $this->line("Koneksi    : {$connectionName} ({$config['driver']})");
        $this->line('Host       : '.($config['host'] ?? '-'.''));
        $this->line('Port       : '.($config['port'] ?? '-'));
        $this->line('Database   : '.($config['database'] ?? '-'));
        $this->line('Username   : '.($config['username'] ?? '-'));
        $this->newLine();

        try {
            $start = microtime(true);
            $pdo = DB::connection($connectionName)->getPdo();
            $elapsed = round((microtime(true) - $start) * 1000);

            $this->info("✅ Koneksi BERHASIL ({$elapsed} ms)");
            $this->line('Versi DB   : '.$pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
            $this->line('Nama DB    : '.$pdo->query('SELECT current_database()')->fetchColumn());

            $tables = DB::connection($connectionName)
                ->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
            $this->line('Jumlah tabel (schema public): '.count($tables));

            if (count($tables) > 0) {
                $this->newLine();
                $this->line('Daftar tabel:');
                foreach ($tables as $table) {
                    $this->line("  - {$table->tablename}");
                }
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('❌ Koneksi GAGAL');
            $this->line('Pesan: '.$e->getMessage());

            $message = $e->getMessage();

            if (str_contains($message, 'could not find driver')) {
                $this->newLine();
                $this->warn('Saran: aktifkan ekstensi pdo_pgsql pada php.ini (hilangkan ";" pada baris extension=pdo_pgsql), lalu restart terminal/Laragon.');
            } elseif (str_contains($message, 'password authentication failed')) {
                $this->newLine();
                $this->warn('Saran: DB_PASSWORD di file .env tidak cocok. Periksa password database Supabase di dashboard Supabase (Project Settings > Database), lalu perbarui .env.');
            } elseif (str_contains($message, 'could not connect to server') || str_contains($message, 'Connection refused') || str_contains($message, 'timed out')) {
                $this->newLine();
                $this->warn('Saran: server tidak terjangkau. Pastikan proyek Supabase aktif (tidak paused) dan koneksi internet stabil.');
            }

            return self::FAILURE;
        }
    }
}
