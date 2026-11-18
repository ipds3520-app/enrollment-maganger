<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema uji SQLite (KHUSUS TESTING).
 *
 * Meniru struktur tabel inti hasil migration produksi, tanpa sintaks khusus
 * PostgreSQL yang tidak didukung SQLite. File ini hanya dijalankan oleh
 * tests/Support/UsesSqliteTestDatabase.php pada koneksi sqlite :memory:.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 30)->default('other');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->timestamps();

            $table->index('type');
        });

        // Nama institusi boleh sama: setiap pendaftaran operator membuat
        // record institusi baru (index unik nama sudah dihapus).

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->restrictOnDelete();
            $table->string('role', 20)->default('operator');
            $table->timestamps();

            $table->index('role');
        });

        // SQLite-compatible equivalent of the production PostgreSQL table,
        // including its status, date, and decision consistency constraints.
        DB::statement(<<<'SQL'
            CREATE TABLE internship_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                institution_id INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                processed_by INTEGER NULL,
                decided_at DATETIME NULL,
                reply_letter_path VARCHAR(255) NULL,
                letter_number VARCHAR(50) NULL,
                admin_notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
                FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE RESTRICT,
                CHECK (status IN ('pending', 'approved', 'rejected')),
                CHECK (end_date > start_date),
                CHECK (
                    (status = 'pending' AND processed_by IS NULL AND decided_at IS NULL AND reply_letter_path IS NULL)
                    OR
                    (status IN ('approved', 'rejected') AND processed_by IS NOT NULL AND decided_at IS NOT NULL)
                )
            )
        SQL);

        DB::statement('CREATE INDEX internship_requests_status_index ON internship_requests (status)');
        DB::statement('CREATE INDEX internship_requests_start_date_index ON internship_requests (start_date)');

        Schema::create('internship_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_request_id')
                ->constrained('internship_requests')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 20)->nullable();
            $table->string('student_number', 30)->nullable();
            $table->string('school_major', 100)->nullable();
            $table->timestamps();
            $table->unique(
                ['internship_request_id', 'student_number'],
                'internship_applicants_request_student_unique'
            );
            $table->index('email');
        });

        Schema::create('registration_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained('internship_applicants')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('users_intern', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained('internship_applicants')->cascadeOnDelete();
            $table->foreignId('registration_token_id')->unique()->constrained('registration_tokens')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('password', 255);
            $table->string('phone', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_intern');
        Schema::dropIfExists('registration_tokens');
        Schema::dropIfExists('internship_applicants');
        Schema::dropIfExists('internship_requests');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('institutions');
    }
};
