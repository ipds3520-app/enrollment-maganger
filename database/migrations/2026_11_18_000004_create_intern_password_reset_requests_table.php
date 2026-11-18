<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intern_password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_intern_id')->constrained('users_intern')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_used_at')->nullable();
            $table->timestamp('token_revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_intern_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX intern_password_reset_requests_one_pending_per_intern_unique ON intern_password_reset_requests (user_intern_id) WHERE status = 'pending'");
        DB::statement(<<<'SQL'
            ALTER TABLE intern_password_reset_requests
            ADD CONSTRAINT intern_password_reset_requests_status_check
            CHECK (status IN ('pending', 'approved', 'rejected'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE intern_password_reset_requests
            ADD CONSTRAINT intern_password_reset_requests_processing_check
            CHECK (
                (status = 'pending' AND processed_by IS NULL AND processed_at IS NULL AND token_hash IS NULL AND token_expires_at IS NULL AND token_used_at IS NULL AND token_revoked_at IS NULL)
                OR
                (status = 'rejected' AND processed_by IS NOT NULL AND processed_at IS NOT NULL AND token_hash IS NULL AND token_expires_at IS NULL AND token_used_at IS NULL AND token_revoked_at IS NULL)
                OR
                (status = 'approved' AND processed_by IS NOT NULL AND processed_at IS NOT NULL AND token_hash IS NOT NULL AND token_expires_at IS NOT NULL AND (token_used_at IS NULL OR token_revoked_at IS NULL))
            )
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Intern password reset request history is retained; this migration cannot be rolled back automatically.');
    }
};
