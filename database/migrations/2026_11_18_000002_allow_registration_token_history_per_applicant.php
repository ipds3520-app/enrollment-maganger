<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** PostgreSQL supports transactional DDL for these non-concurrent operations. */
    public $withinTransaction = true;

    public function up(): void
    {
        $this->assertTransactionalPostgres();
        $this->lockTokenTable();
        $this->assertApplicantForeignKeyExists();

        $legacyConstraintExists = $this->legacyApplicantUniqueExists();
        $this->assertNoStandaloneLegacyIndex($legacyConstraintExists);

        // Reuse a valid index on a retry; a conflicting index definition is an error.
        $activeIndexExists = $this->activeApplicantIndexExists();

        if (! $activeIndexExists) {
            DB::statement("CREATE UNIQUE INDEX registration_tokens_one_active_per_applicant_unique ON registration_tokens (applicant_id) WHERE status = 'active'");

            if (! $this->activeApplicantIndexExists()) {
                throw new RuntimeException('The active-token partial unique index was not created.');
            }
        }

        // The new protection is now present and verified. The surrounding PostgreSQL
        // transaction restores the old constraint if this operation fails.
        if ($legacyConstraintExists) {
            Schema::table('registration_tokens', function (Blueprint $table): void {
                $table->dropUnique('registration_tokens_applicant_id_unique');
            });
        }
    }

    public function down(): void
    {
        $this->assertTransactionalPostgres();
        $this->lockTokenTable();
        $this->assertApplicantForeignKeyExists();

        if (DB::table('registration_tokens')
            ->select('applicant_id')
            ->groupBy('applicant_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Rollback cancelled: registration token history has multiple rows for an applicant. No schema or data was changed.');
        }

        $legacyConstraintExists = $this->legacyApplicantUniqueExists();
        $this->assertNoStandaloneLegacyIndex($legacyConstraintExists);
        $activeIndexExists = $this->activeApplicantIndexExists();

        // Restore the stronger legacy uniqueness before removing the partial index.
        if (! $legacyConstraintExists) {
            Schema::table('registration_tokens', function (Blueprint $table): void {
                $table->unique('applicant_id', 'registration_tokens_applicant_id_unique');
            });

            if (! $this->legacyApplicantUniqueExists()) {
                throw new RuntimeException('The legacy applicant unique constraint could not be restored.');
            }
        }

        if ($activeIndexExists) {
            DB::statement('DROP INDEX registration_tokens_one_active_per_applicant_unique');
        }
    }

    private function assertTransactionalPostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('This migration requires PostgreSQL.');
        }

        if (DB::connection()->transactionLevel() < 1) {
            throw new RuntimeException("This migration must run in Laravel's PostgreSQL schema transaction.");
        }
    }

    private function lockTokenTable(): void
    {
        DB::statement('LOCK TABLE registration_tokens IN ACCESS EXCLUSIVE MODE');
    }

    private function assertApplicantForeignKeyExists(): void
    {
        $foreignKey = DB::selectOne(
            "SELECT 1 FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = current_schema()
               AND t.relname = 'registration_tokens'
               AND c.conname = 'registration_tokens_applicant_id_foreign'
               AND c.contype = 'f'"
        );

        if ($foreignKey === null) {
            throw new RuntimeException('The registration_tokens applicant foreign key is missing; migration stopped without changing it.');
        }
    }

    private function legacyApplicantUniqueExists(): bool
    {
        $constraint = DB::selectOne(
            "SELECT c.contype, pg_get_constraintdef(c.oid) AS definition
             FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = current_schema()
               AND t.relname = 'registration_tokens'
               AND c.conname = 'registration_tokens_applicant_id_unique'"
        );

        if ($constraint === null) {
            return false;
        }

        $definition = strtolower(preg_replace('/[\s"()]/', '', $constraint->definition));

        if ($constraint->contype !== 'u' || $definition !== 'uniqueapplicant_id') {
            throw new RuntimeException('The legacy applicant constraint exists but does not match the expected unique constraint.');
        }

        return true;
    }

    private function assertNoStandaloneLegacyIndex(bool $legacyConstraintExists): void
    {
        if ($legacyConstraintExists) {
            return;
        }

        $index = DB::selectOne(
            'SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
            ['registration_tokens', 'registration_tokens_applicant_id_unique'],
        );

        if ($index !== null) {
            throw new RuntimeException('A standalone index uses the legacy constraint name; migration stopped without changing it.');
        }
    }

    private function activeApplicantIndexExists(): bool
    {
        $index = DB::selectOne(
            "SELECT table_namespace.nspname AS schema_name,
                    current_schema() AS active_schema,
                    table_relation.relname AS table_name,
                    index_relation.relname AS index_name,
                    index_access_method.amname AS index_method,
                    CASE WHEN index_data.indisunique THEN 1 ELSE 0 END AS is_unique,
                    CASE WHEN index_data.indisvalid THEN 1 ELSE 0 END AS is_valid,
                    CASE WHEN index_data.indisready THEN 1 ELSE 0 END AS is_ready,
                    CASE WHEN index_data.indislive THEN 1 ELSE 0 END AS is_live,
                    index_data.indnatts AS total_columns,
                    index_data.indnkeyatts AS key_columns,
                    (
                        SELECT string_agg(attribute.attname, ',' ORDER BY index_column.ordinality)
                        FROM unnest(index_data.indkey) WITH ORDINALITY AS index_column(attnum, ordinality)
                        JOIN pg_attribute attribute
                          ON attribute.attrelid = table_relation.oid
                         AND attribute.attnum = index_column.attnum
                        WHERE index_column.ordinality <= index_data.indnkeyatts
                    ) AS columns,
                    pg_get_expr(index_data.indpred, index_data.indrelid) AS predicate
             FROM pg_index index_data
             JOIN pg_class index_relation ON index_relation.oid = index_data.indexrelid
             JOIN pg_namespace index_namespace ON index_namespace.oid = index_relation.relnamespace
             JOIN pg_am index_access_method ON index_access_method.oid = index_relation.relam
             JOIN pg_class table_relation ON table_relation.oid = index_data.indrelid
             JOIN pg_namespace table_namespace ON table_namespace.oid = table_relation.relnamespace
             WHERE table_namespace.nspname = current_schema()
               AND index_namespace.nspname = current_schema()
               AND table_relation.relname = ?
               AND index_relation.relname = ?",
            ['registration_tokens', 'registration_tokens_one_active_per_applicant_unique'],
        );

        if ($index === null) {
            return false;
        }

        if (! $this->isExpectedActiveApplicantIndex($index)) {
            throw new RuntimeException('The active-token index name is already used by an unexpected index definition.');
        }

        return true;
    }

    /**
     * @param object{
     *     schema_name: string,
     *     active_schema: string,
     *     table_name: string,
     *     index_name: string,
     *     index_method: string,
     *     is_unique: int|string,
     *     is_valid: int|string,
     *     is_ready: int|string,
     *     is_live: int|string,
     *     total_columns: int|string,
     *     key_columns: int|string,
     *     columns: ?string,
     *     predicate: ?string
     * } $index
     */
    private function isExpectedActiveApplicantIndex(object $index): bool
    {
        $predicate = strtolower(preg_replace('/[\s"\'()]/', '', $index->predicate ?? ''));
        $predicate = preg_replace('/::(?:charactervarying\d*|varchar\d*|text)/', '', $predicate);

        return $index->schema_name === $index->active_schema
            && $index->table_name === 'registration_tokens'
            && $index->index_name === 'registration_tokens_one_active_per_applicant_unique'
            && $index->index_method === 'btree'
            && (int) $index->is_unique === 1
            && (int) $index->is_valid === 1
            && (int) $index->is_ready === 1
            && (int) $index->is_live === 1
            && (int) $index->total_columns === 1
            && (int) $index->key_columns === 1
            && $index->columns === 'applicant_id'
            && $predicate === 'status=active';
    }
};
