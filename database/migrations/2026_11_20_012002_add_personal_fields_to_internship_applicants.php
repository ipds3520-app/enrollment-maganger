<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_applicants', function (Blueprint $table) {
            $table->string('nik', 16)->nullable();
            $table->string('school_name', 150)->nullable();
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
        });
    }

    public function down(): void
    {
        $hasProfileData = DB::table('internship_applicants')
            ->where(function ($query): void {
                $query->whereNotNull('nik')
                    ->orWhereNotNull('school_name')
                    ->orWhereNotNull('address')
                    ->orWhereNotNull('birth_date')
                    ->orWhereNotNull('gender');
            })
            ->exists();

        if ($hasProfileData) {
            throw new RuntimeException('Cannot drop participant profile fields while profile data exists.');
        }

        Schema::table('internship_applicants', function (Blueprint $table) {
            $table->dropColumn(['nik', 'school_name', 'address', 'birth_date', 'gender']);
        });
    }
};
