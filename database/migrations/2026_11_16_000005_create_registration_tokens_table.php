<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tanpa transaksi terbungkus — kompatibel dengan transaction-mode pooler Supabase (SQLSTATE 25P02). */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('registration_tokens', function (Blueprint $table) {
            $table->id();

            // 1 peserta = 1 token; ikut hilang bersama peserta
            $table->foreignId('applicant_id')
                ->unique()
                ->constrained('internship_applicants')
                ->cascadeOnDelete();

            // Token asli TIDAK disimpan — hanya hash SHA-256 (64 karakter hex)
            $table->string('token_hash', 64)->unique();

            // active | used | revoked — "expired" ditentukan dari expires_at, bukan status tersimpan
            $table->string('status', 20)->default('active');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        // Konsistensi pemakaian token:
        // - used                : used_at wajib terisi
        // - active / revoked    : used_at wajib kosong
        DB::statement("
            ALTER TABLE registration_tokens
            ADD CONSTRAINT registration_tokens_used_check
            CHECK (
                (status = 'used' AND used_at IS NOT NULL)
                OR (status IN ('active', 'revoked') AND used_at IS NULL)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_tokens');
    }
};
