<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users_intern', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('internship_applicants', function (Blueprint $table): void {
            $table->string('participant_status', 20)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index('participant_status', 'internship_applicants_participant_status_index');
        });

        Schema::create('internship_participant_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('applicant_id')
                ->constrained('internship_applicants')
                ->restrictOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['applicant_id', 'changed_at'], 'internship_participant_status_history_lookup_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE internship_applicants
                ADD CONSTRAINT internship_applicants_participant_status_check
                CHECK (
                    CASE
                        WHEN participant_status IS NULL THEN completed_at IS NULL
                        WHEN participant_status = 'active' THEN completed_at IS NULL
                        WHEN participant_status = 'completed' THEN completed_at IS NOT NULL
                        ELSE FALSE
                    END
                )
            SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE internship_participant_status_histories
                ADD CONSTRAINT internship_participant_status_histories_status_check
                CHECK (
                    (old_status IS NULL OR old_status IN ('active', 'completed'))
                    AND new_status IN ('active', 'completed')
                )
            SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER internship_applicants_participant_status_insert_check
                BEFORE INSERT ON internship_applicants
                WHEN NOT CASE
                    WHEN NEW.participant_status IS NULL THEN NEW.completed_at IS NULL
                    WHEN NEW.participant_status = 'active' THEN NEW.completed_at IS NULL
                    WHEN NEW.participant_status = 'completed' THEN NEW.completed_at IS NOT NULL
                    ELSE FALSE
                END
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid participant status or completed_at combination');
                END
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER internship_applicants_participant_status_update_check
                BEFORE UPDATE OF participant_status, completed_at ON internship_applicants
                WHEN NOT CASE
                    WHEN NEW.participant_status IS NULL THEN NEW.completed_at IS NULL
                    WHEN NEW.participant_status = 'active' THEN NEW.completed_at IS NULL
                    WHEN NEW.participant_status = 'completed' THEN NEW.completed_at IS NOT NULL
                    ELSE FALSE
                END
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid participant status or completed_at combination');
                END
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER internship_participant_status_history_insert_check
                BEFORE INSERT ON internship_participant_status_histories
                WHEN NOT (
                    (NEW.old_status IS NULL OR NEW.old_status IN ('active', 'completed'))
                    AND NEW.new_status IN ('active', 'completed')
                )
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid participant status history value');
                END
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER internship_participant_status_history_update_check
                BEFORE UPDATE OF old_status, new_status ON internship_participant_status_histories
                WHEN NOT (
                    (NEW.old_status IS NULL OR NEW.old_status IN ('active', 'completed'))
                    AND NEW.new_status IN ('active', 'completed')
                )
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid participant status history value');
                END
            SQL);
        }
    }

    public function down(): void
    {
        $hasLifecycleData = DB::table('internship_applicants')
            ->whereNotNull('participant_status')
            ->orWhereNotNull('completed_at')
            ->exists();

        if (
            DB::table('users_intern')->whereNotNull('deleted_at')->exists()
            || $hasLifecycleData
            || DB::table('internship_participant_status_histories')->exists()
        ) {
            throw new RuntimeException('Cannot roll back participant lifecycle data or archived intern accounts.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE internship_applicants DROP CONSTRAINT IF EXISTS internship_applicants_participant_status_check');
            DB::statement('ALTER TABLE internship_participant_status_histories DROP CONSTRAINT IF EXISTS internship_participant_status_histories_status_check');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS internship_applicants_participant_status_insert_check');
            DB::unprepared('DROP TRIGGER IF EXISTS internship_applicants_participant_status_update_check');
            DB::unprepared('DROP TRIGGER IF EXISTS internship_participant_status_history_insert_check');
            DB::unprepared('DROP TRIGGER IF EXISTS internship_participant_status_history_update_check');
        }

        Schema::dropIfExists('internship_participant_status_histories');

        Schema::table('internship_applicants', function (Blueprint $table): void {
            $table->dropIndex('internship_applicants_participant_status_index');
            $table->dropColumn(['participant_status', 'completed_at']);
        });

        Schema::table('users_intern', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
