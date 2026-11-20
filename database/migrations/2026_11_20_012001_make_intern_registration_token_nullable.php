<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ALTER COLUMN only changes nullability; existing unique and FK constraints remain intact.
        DB::statement('ALTER TABLE users_intern ALTER COLUMN registration_token_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Refuse rollback if new shared-token accounts have no legacy token.
        if (DB::table('users_intern')->whereNull('registration_token_id')->exists()) {
            throw new RuntimeException('Cannot make registration_token_id NOT NULL while shared-token accounts exist.');
        }

        DB::statement('ALTER TABLE users_intern ALTER COLUMN registration_token_id SET NOT NULL');
    }
};
