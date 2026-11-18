<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Supabase transaction-mode pooler compatibility, matching existing migrations. */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('internship_requests', function (Blueprint $table): void {
            $table->string('proposal_path', 255)->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('internship_requests', function (Blueprint $table): void {
            $table->dropColumn('proposal_path');
        });
    }
};
