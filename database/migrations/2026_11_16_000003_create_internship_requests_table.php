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
        Schema::create('internship_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')
                ->constrained('institutions')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');

            // pending | approved | rejected — tanpa draft
            $table->string('status', 20)->default('pending');

            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reply_letter_path', 255)->nullable();
            $table->string('letter_number', 50)->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('start_date');
        });

        // Status hanya boleh: pending | approved | rejected
        DB::statement("
            ALTER TABLE internship_requests
            ADD CONSTRAINT internship_requests_status_check
            CHECK (status IN ('pending', 'approved', 'rejected'))
        ");

        // Tanggal magang harus valid
        DB::statement("
            ALTER TABLE internship_requests
            ADD CONSTRAINT internship_requests_dates_check
            CHECK (end_date > start_date)
        ");

        // Konsistensi keputusan:
        // - pending     : belum ada admin, waktu keputusan, dan surat balasan
        // - approved/rejected : wajib ada admin pemroses dan waktu keputusan
        DB::statement("
            ALTER TABLE internship_requests
            ADD CONSTRAINT internship_requests_decision_check
            CHECK (
                (status = 'pending'
                    AND processed_by IS NULL
                    AND decided_at IS NULL
                    AND reply_letter_path IS NULL)
                OR
                (status IN ('approved', 'rejected')
                    AND processed_by IS NOT NULL
                    AND decided_at IS NOT NULL)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_requests');
    }
};
