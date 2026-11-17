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
        Schema::table('users', function (Blueprint $table) {
            // admin: institution_id NULL | operator: institution_id wajib terisi
            $table->foreignId('institution_id')
                ->nullable()
                ->constrained('institutions')
                ->restrictOnDelete();

            $table->string('role', 20)->default('operator'); // admin | operator

            $table->index('role');
        });

        // Aturan role vs instansi (PostgreSQL CHECK):
        // (role = admin  AND institution_id IS NULL)
        // OR (role = operator AND institution_id IS NOT NULL)
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_institution_check
            CHECK (
                (role = 'admin' AND institution_id IS NULL)
                OR (role = 'operator' AND institution_id IS NOT NULL)
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_institution_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
