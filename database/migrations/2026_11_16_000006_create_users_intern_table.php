<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('users_intern', function (Blueprint $table) {
            $table->id();

            // 1 peserta = 1 akun; ikut hilang bersama peserta
            $table->foreignId('applicant_id')
                ->unique()
                ->constrained('internship_applicants')
                ->cascadeOnDelete();

            // 1 token = 1 akun (jejak audit token yang dipakai saat registrasi)
            $table->foreignId('registration_token_id')
                ->unique()
                ->constrained('registration_tokens')
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password', 255); // disimpan hashed (bcrypt/argon2) via Laravel
            $table->string('phone', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_intern');
    }
};
