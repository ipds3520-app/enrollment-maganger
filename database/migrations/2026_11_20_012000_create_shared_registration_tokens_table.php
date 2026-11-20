<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_registration_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_request_id')->unique()
                ->constrained('internship_requests')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->unsignedInteger('quota');
            $table->unsignedInteger('used_count')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index('status');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE shared_registration_tokens
            ADD CONSTRAINT shared_registration_tokens_quota_check
            CHECK (quota > 0 AND used_count >= 0 AND used_count <= quota)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE shared_registration_tokens
            ADD CONSTRAINT shared_registration_tokens_status_check
            CHECK (status IN ('active', 'exhausted', 'revoked'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE shared_registration_tokens
            ADD CONSTRAINT shared_registration_tokens_status_quota_check
            CHECK (
                (status = 'active' AND used_count < quota)
                OR (status = 'exhausted' AND used_count = quota)
                OR status = 'revoked'
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::table('shared_registration_tokens')->exists()) {
            throw new RuntimeException('Cannot drop shared registration tokens while token records exist.');
        }

        Schema::dropIfExists('shared_registration_tokens');
    }
};
