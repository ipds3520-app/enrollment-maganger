<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Tanpa transaksi terbungkus — kompatibel dengan transaction-mode pooler Supabase (SQLSTATE 25P02). */
    public $withinTransaction = false;

    /**
     * Hapus index unik nama institusi.
     *
     * Setiap pendaftaran operator membuat record institusi baru, sehingga nama
     * institusi boleh sama (mis. dua SMK dengan nama serupa). Data institusi
     * milik operator lain tidak pernah dicari, digabung, atau ditimpa.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS institutions_name_normalized_unique');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pengaman: index unik tidak dapat dibuat bila data sudah duplikat.
        // Data TIDAK dihapus/digabung otomatis — harus diselesaikan manual.
        $duplicates = DB::select('
            SELECT lower(btrim(name)) AS normalized_name, count(*) AS total
            FROM institutions
            GROUP BY lower(btrim(name))
            HAVING count(*) > 1
            ORDER BY total DESC
        ');

        if (! empty($duplicates)) {
            $details = collect($duplicates)
                ->map(fn ($row) => sprintf("'%s' (%d baris)", $row->normalized_name, $row->total))
                ->implode(', ');

            throw new RuntimeException(
                'Migration dibatalkan: ditemukan nama institusi duplikat setelah normalisasi: '
                .$details
                .'. Rapikan data terlebih dahulu, lalu jalankan ulang migration.'
            );
        }

        DB::statement('
            CREATE UNIQUE INDEX institutions_name_normalized_unique
            ON institutions (lower(btrim(name)))
        ');
    }
};
