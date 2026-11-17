<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Tanpa transaksi terbungkus — kompatibel dengan transaction-mode pooler Supabase (SQLSTATE 25P02). */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hasil review Tahap 2 (Temuan A): batasi nilai status token
        // hanya active | used | revoked — setara dengan whitelist
        // status pada tabel internship_requests.
        DB::statement("
            ALTER TABLE registration_tokens
            ADD CONSTRAINT registration_tokens_status_check
            CHECK (status IN ('active', 'used', 'revoked'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE registration_tokens DROP CONSTRAINT IF EXISTS registration_tokens_status_check');
    }
};
