<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('internship_requests', function (Blueprint $table) {
            $table->unsignedInteger('participant_count')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE internship_requests
            ADD CONSTRAINT internship_requests_participant_count_check
            CHECK (participant_count IS NULL OR participant_count > 0)
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('internship_requests')->whereNotNull('participant_count')->exists()) {
            throw new RuntimeException('Cannot drop participant_count while request quota data exists.');
        }

        DB::statement('ALTER TABLE internship_requests DROP CONSTRAINT IF EXISTS internship_requests_participant_count_check');

        Schema::table('internship_requests', function (Blueprint $table) {
            $table->dropColumn('participant_count');
        });
    }
};
