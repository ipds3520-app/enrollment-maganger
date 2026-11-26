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
            $table->unsignedBigInteger('auth_version')->default(1);
        });
    }

    public function down(): void
    {
        if (DB::table('users_intern')->where('auth_version', '!=', 1)->exists()) {
            throw new RuntimeException('Cannot remove Intern authentication versions after session revocation has been used.');
        }

        Schema::table('users_intern', function (Blueprint $table): void {
            $table->dropColumn('auth_version');
        });
    }
};
