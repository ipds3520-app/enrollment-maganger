<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_registration_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_registration_token_id')
                ->constrained('shared_registration_tokens')->cascadeOnDelete();
            $table->foreignId('user_intern_id')->unique()
                ->constrained('users_intern')->cascadeOnDelete();
            $table->foreignId('internship_applicant_id')->unique()
                ->constrained('internship_applicants')->cascadeOnDelete();
            $table->string('status', 20)->default('pending_profile');
            $table->timestamp('claimed_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['shared_registration_token_id', 'user_intern_id'], 'shared_claim_token_user_unique');
            $table->index(['shared_registration_token_id', 'status'], 'shared_claim_token_status_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE shared_registration_claims
            ADD CONSTRAINT shared_registration_claims_status_check
            CHECK (
                (status = 'pending_profile' AND completed_at IS NULL)
                OR (status = 'completed' AND completed_at IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        $hasSharedUsers = DB::table('users_intern')->whereNull('registration_token_id')->exists();
        $hasParticipantCounts = DB::table('internship_requests')->whereNotNull('participant_count')->exists();
        $hasApplicantProfiles = DB::table('internship_applicants')
            ->where(function ($query): void {
                $query->whereNotNull('nik')
                    ->orWhereNotNull('school_name')
                    ->orWhereNotNull('address')
                    ->orWhereNotNull('birth_date')
                    ->orWhereNotNull('gender');
            })
            ->exists();

        if (
            DB::table('shared_registration_claims')->exists()
            || DB::table('shared_registration_tokens')->exists()
            || $hasSharedUsers
            || $hasParticipantCounts
            || $hasApplicantProfiles
        ) {
            throw new RuntimeException('Cannot roll back Stage 6 migrations while shared registration or participant data exists.');
        }

        Schema::dropIfExists('shared_registration_claims');
    }
};
