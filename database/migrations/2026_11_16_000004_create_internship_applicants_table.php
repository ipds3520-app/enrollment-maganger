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
        Schema::create('internship_applicants', function (Blueprint $table) {
            $table->id();
            // Peserta hilang bersama pengajuannya
            $table->foreignId('internship_request_id')
                ->constrained('internship_requests')
                ->cascadeOnDelete();

            $table->string('name', 100);
            // Tidak unique secara global — email sama boleh muncul lintas pengajuan
            $table->string('email', 150);
            $table->string('phone', 20)->nullable();

            // NIS/NIM — unik hanya di dalam satu pengajuan
            $table->string('student_number', 30)->nullable();
            $table->string('school_major', 100)->nullable();

            $table->timestamps();

            // Composite unique: nomor induk tidak boleh duplikat dalam satu pengajuan
            $table->unique(
                ['internship_request_id', 'student_number'],
                'internship_applicants_request_student_unique'
            );

            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_applicants');
    }
};
