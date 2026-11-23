<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_registration_tokens', function (Blueprint $table): void {
            $table->text('token_encrypted')->nullable();
            $table->foreignId('quota_updated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('quota_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('shared_registration_tokens')->whereNotNull('token_encrypted')->exists()
            || DB::table('shared_registration_tokens')->whereNotNull('quota_updated_by')->exists()
            || DB::table('shared_registration_tokens')->whereNotNull('quota_updated_at')->exists()) {
            throw new RuntimeException('Cannot remove encrypted tokens or quota audit data during rollback.');
        }

        Schema::table('shared_registration_tokens', function (Blueprint $table): void {
            $table->dropForeign(['quota_updated_by']);
            $table->dropColumn(['token_encrypted', 'quota_updated_by', 'quota_updated_at']);
        });
    }
};
